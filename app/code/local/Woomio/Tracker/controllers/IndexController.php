<?php
class Woomio_Tracker_IndexController extends Mage_Core_Controller_Front_Action{
    public function IndexAction() {
	    		
		$AllowedIP 			= gethostbyname('ping.woomio.com');
		$GetParams 			= Mage::app()->getRequest()->getParams();
		$woomioTable		= Mage::getSingleton("core/resource")->getTableName('woomio');
		$response 			= array();
		
        //if(true){
		if($_SERVER['REMOTE_ADDR'] == $AllowedIP){
			$response['status'] = "success";
			$response['status_message'] = "IP allowed";
			if(!$GetParams['hrs']){
				$HRS = 1;
			}else{
				$HRS = $GetParams['hrs'];
			}
			if(!$GetParams['wacs']){
				$WACSID = 0;
			}else{
				$WACSID = $GetParams['wacs'];
			}
			if(!$GetParams['id']){
				$_ID = 0;
			}else{
				$_ID = $GetParams['id'];
			}
			switch($GetParams['type']){
				case 'orders':
					
					$_conn 		= Mage::getSingleton('core/resource')->getConnection('core_read');
					$fromDate 	= date('Y-m-d H:i:s', strtotime('now -'.$HRS.' hours'));
					$toDate 	= date('Y-m-d H:i:s', strtotime('now'));
					if($_ID){
						$Order 	= Mage::getModel('sales/order')->load($_ID);

						$response['orders'][$Order->getId()]['order'] 							= $Order->getData();
						$response['orders'][$Order->getId()]['customer'] 						= $Order->getBillingAddress()->getData();
						$response['orders'][$Order->getId()]['customer']['fullname'] 			= $Order->getCustomerName();
						$response['orders'][$Order->getId()]['customer']['fullstreet'] 			= $Order->getBillingAddress()->getStreetFull();
						$response['orders'][$Order->getId()]['wacsid'] = $woomioRow['wacsid'];
					}else{
						$Orders 	= Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('created_at', array('from'=>$fromDate, 'to'=>$toDate));
						foreach($Orders as $Order){
							if($WACSID){
								$woomioRow = $_conn->fetchRow('SELECT wacsid FROM '.$woomioTable.' WHERE orderid = '.$Order->getId()); 
								if($woomioRow['wacsid']){
									$response['orders'][$Order->getId()] 								= $Order->getData();
								    foreach($Order->getAllItems() as $Item):
								        $response['orders'][$Order->getId()]['items'][] = array('id' => $Item->getProductId(), 'sku' => $Item->getSku(), 'name' => $Item->getName());
								    endforeach;								
									$response['orders'][$Order->getId()]['customer'] 					= $Order->getBillingAddress()->getData();
									$response['orders'][$Order->getId()]['customer']['fullname'] 		= $Order->getCustomerName();
									$response['orders'][$Order->getId()]['customer']['fullstreet'] 		= $Order->getBillingAddress()->getStreetFull();
									$response['orders'][$Order->getId()]['wacsid'] = $woomioRow['wacsid'];
								}
							}else{
								$woomioRow = $_conn->fetchRow('SELECT wacsid FROM '.$woomioTable.' WHERE orderid = '.$Order->getId());
								$response['orders'][$Order->getId()] = $Order->getData();
							    foreach($Order->getAllItems() as $Item):
							        $response['orders'][$Order->getId()]['items'][] = array('id' => $Item->getProductId(), 'sku' => $Item->getSku(), 'name' => $Item->getName());
							    endforeach;
								$response['orders'][$Order->getId()]['customer'] = $Order->getBillingAddress()->getData();
								$response['orders'][$Order->getId()]['customer']['fullname'] = $Order->getCustomerName();
								$response['orders'][$Order->getId()]['customer']['fullstreet'] = $Order->getBillingAddress()->getStreetFull();
								if($woomioRow['wacsid']){
									$response['orders'][$Order->getId()]['wacsid'] = $woomioRow['wacsid'];
								}else{
									$response['orders'][$Order->getId()]['wacsid'] = 0;
								}
							}
						}
					}
					
				break;
				// #2
				case 'customers':
					if($_ID){
						$Address 					= Mage::getModel('sales/order_address')->load($_ID);
						$response['customers'][] 	= $Address->getData();
					}else{
						$Addresses 					= Mage::getModel('sales/order_address')->getCollection()->addFieldToSelect('*')->addFieldToFilter('address_type', 'billing');
						foreach($Addresses as $Address){
							$response['customers'][$Address->getId()] 	= $Address->getData();
						}
					}
				break;
				case 'products':
					if($_ID){
						$Products 					= Mage::getModel('catalog/product')->load($_ID);
						$response['products'][]		= $Products->getData();
					}else{
						$Products 					= Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*');
						if($HRS == 'all'){
							
						}else{
							$startDate = date('Y-m-d', strtotime('now -'.$HRS.' hours'));
							$finishDate = date('Y-m-d', strtotime('now'));
							$Products->addAttributeToFilter(array(array('attribute' => 'created_at','from' => $startDate,'to' => $finishDate)));					
						}
	
						foreach($Products as $Product){
							$response['products'][$Product->getId()] = $Product->getData();
                            
                            // Categories
                            $Categories = $Product->getCategoryCollection()->addAttributeToSelect('name');
                            foreach ($Categories as $Category) {
                                $response['products'][$Product->getId()]["categories"] .= $Category -> getName() . "|";
                            }
						}
						
					}
				break;
			}
		}else{
			$response['status'] = "error";
			$response['status_message'] = "IP NOT allowed";
		}
		
		if($GetParams['debug'] == 'true'){
			echo "<pre>";
			var_dump($response);
			echo "</pre>";
			Mage::getModel('core/cookie')->set('wacsid', '999');
		}else{
			echo json_encode($response);
		}
		
    }
}
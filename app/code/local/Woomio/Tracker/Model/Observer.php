<?php
class Woomio_Tracker_Model_Observer
{

	public function setWascid(Varien_Event_Observer $observer)
	{
		$Order 		= $observer->getEvent()->getOrder();
		$OrderId 	= $Order->getId();
		
		$WascID 	= Mage::getModel('core/cookie')->get('wacsid');
		if(!$WascID){ $WascID = 0; }
		
		$Resource 	= Mage::getSingleton('core/resource');
		$Connection = Mage::getSingleton('core/resource')->getConnection('core_write');
		$WoomioTable = Mage::getSingleton("core/resource")->getTableName('woomio');
		
		$InsertSQL = "INSERT INTO `".$WoomioTable."` (`orderid`,`wacsid`) VALUES ('".$OrderId."','".$WascID."')";
		$Connection = Mage::getSingleton('core/resource')->getConnection('core_write');
		$Connection->query($InsertSQL);
		
        $CallRegisterHitUrl ="http://www.woomio.com/api/analyticsr/RegisterHit?esid=".$WascID."&url="."http://$_SERVER[HTTP_HOST]/checkout/onepage/success/"."&r=1&ct=&ur=";
        $CallbackRegisterHit = file_get_contents($CallRegisterHitUrl);
        
		$CallbackUrl = "https://www.woomio.com/endpoints/purchase?sid=".$WascID."&oid=".$OrderId."&ot=".$Order->getGrandTotal()."&url=0&oc=".$Order->getBaseCurrencyCode()."&email=".$Order->getCustomerEmail();
		$Callback = file_get_contents($CallbackUrl);
	}
}

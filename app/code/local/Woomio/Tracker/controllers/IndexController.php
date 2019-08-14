<?php
function indexcontroller_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
    echo 'An error occurred in the woomio plugin. ' . $errno . ': ' . $errstr;
    return true;
}

class Woomio_Tracker_IndexController extends Mage_Core_Controller_Front_Action{
    /**
     * @url orders: http://ec2-52-19-3-226.eu-west-1.compute.amazonaws.com/magento/woomio?type=orders
     */
    public function IndexAction() {
	    		
		$AllowedIP = gethostbyname('ping.woomio.com');

		$GetParams = Mage::app()->getRequest()->getParams();
		$woomioTable = Mage::getSingleton("core/resource")->getTableName('woomio');
		
		if($_SERVER['REMOTE_ADDR'] !== $AllowedIP) {
            echo "Plugin error: 403";
			die;
		}

        set_error_handler('indexcontroller_error_handler');

		$hrs = ((isset($_GET['hrs']) && is_numeric($_GET['hrs'])) ? $_GET['hrs'] : null);
        $affiliated = (isset($_GET['affiliated']) && $_GET['affiliated'] === 'true');
        $id = (isset($_GET['id']) ? $_GET['id'] : 0);
		
		switch($GetParams['type']){
			case 'orders':
				$response = new stdClass();
				$response->orders = $this->get_orders($affiliated, $id, $hrs);
				break;
			case 'customers':
				$response = new stdClass();
                $response->customers = $this->get_customers($id);
				break;
			case 'products':
				$response = array();
				if($id){
					$Products 					= Mage::getModel('catalog/product')->load($id);
					$response['products'][]		= $Products->getData();
				}
				else{
					$Products 					= Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*');
					if($hrs !== null){
						$startDate = date('Y-m-d', strtotime('now -' . $hrs . ' hours'));
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
                        unset($Product);
					}
					unset($Product);
				}
				break;
		}
		
		echo json_encode($response);

        restore_error_handler();
    }

    /**
     * /woomio?type=orders&
     */
    function get_orders($affiliated, $id, $hrs) {
    	//If no orders return empty order array
        $orders = array();

    	$db_resource = Mage::getSingleton('core/resource');
    	$read_connection = $db_resource->getConnection('core_read');

    	$table_woomio = $db_resource->getTableName('woomio');
    	$table_order = $db_resource->getTableName('sales_flat_order');

    	$now = new DateTime(null, new DateTimeZone('UTC'));

    	//Get orders, increment_id is the order id shown to shop owner and customer, entity_id is the order object id used in the database
    	$query = "SELECT entity_id, created_at, status, shipping_method, shipping_amount, shipping_tax_amount, order_currency_code, remote_ip, customer_is_guest, customer_email, increment_id AS order_id, customer_id, discount_amount, tax_amount, grand_total, shipping_address_id, billing_address_id";
    	if($affiliated === true) {
    		$query .= ", wacsid";
    	}
    	$query .= " FROM " . $table_order;
    	if($affiliated === true) {
    		$query .= ", " . $table_woomio;
    	}
    	$query .= " WHERE entity_id >= 0"; //Always true condition to allow adding the other conditionals based on parameters
    	if($affiliated === true) {
    		$query .= " AND increment_id = orderid";
    	}
    	if($id) {
    		$query .= " AND increment_id = :id";
    	}
    	if($hrs !== null) {
    		$now->sub(new DateInterval('PT' . $hrs . 'H'));
            $query .= " AND created_at >= :created_at";
    	}
    	$query .= " ORDER BY order_id;";

    	$query_binds = array();
    	if ($id && $hrs !== null) {
            $query_binds['id'] = $id;
            $query_binds['created_at'] = $now->format('Y-m-d H:i:s');
        }
        else if($id) {
            $query_binds['id'] = $id;
        }
        else if($hrs !== null) {
            $query_binds['created_at'] = $now->format('Y-m-d H:i:s');
        }
            
        $result = $read_connection->query($query, $query_binds);

        while($row = $result->fetch()) {
        	$order = new stdClass();
            $order->id = $row['order_id'];
            $order->entity_id = $row['entity_id'];
            $order->status = $row['status'];
        	$order->time = $row['created_at'];
        	$order->items = array();
        	$order->shippings = array();
        	$order->currency = $row['order_currency_code'];
        	$order->customer_order_ip = $row['remote_ip'];
        	$order->user_agent = ''; // Magento does not seem to store the user agent
        	$order->is_guest = ($row['customer_is_guest'] === '0');
        	$order->customer_id = $row['customer_id'];
        	$order->shippings[0] = new stdClass();
        	$order->shippings[0]->shipping_cost = $row['shipping_amount'];
        	$order->customer_email = $row['customer_email'];
        	$order->cart_discount = $row['discount_amount'];
        	$order->cart_discount_tax = 0; //Magento does not seem to calculate tax on discounts
        	$order->order_tax = $row['tax_amount'];
        	$order->total = $row['grand_total'];
        	if($affiliated === true) {
        		$order->wacsid = $row['wacsid'];
        	}
        	$order->billing_address = $row['billing_address_id'];
        	$order->shippings[0]->shipping_address = $row['shipping_address_id'];
        	$orders[] = $order;
        }

        $table_order_item = $db_resource->getTableName('sales_flat_order_item');
        $table_order_address = $db_resource->getTableName('sales_flat_order_address');

        $item_query = "SELECT qty_ordered, product_id, name, sku, tax_amount, row_total, row_total_incl_tax";
        $item_query .= " FROM " . $table_order_item;
        $item_query .= " WHERE order_id = :order_id AND parent_item_id IS NULL ORDER BY item_id;";
        
        $address_query = "SELECT t1.region as shipping_region, t1.postcode AS shipping_postcode, t1.lastname AS shipping_lastname, t1.street AS shipping_address, t1.city AS shipping_city, t1.firstname AS shipping_firstname, t1.middlename AS shipping_middlename, t1.company AS shipping_company, t1.country_id AS shipping_country";
        $address_query .= ", t2.region AS billing_region, t2.postcode AS billing_postcode, t2.lastname AS billing_lastname, t2.street AS billing_address, t2.city AS billing_city, t2.telephone AS billing_phone, t2.firstname AS billing_firstname, t2.middlename AS billing_middlename, t2.company AS billing_company, t2.country_id AS billing_country";
		$address_query .= " FROM " . $table_order_address . " AS t1";
		$address_query .= " INNER JOIN " . $table_order_address . " AS t2";
		$address_query .= " ON t2.entity_id = :billing_entity_id";
        $address_query .= " WHERE t1.entity_id = :shipping_entity_id;";

        foreach($orders as $order) {
        	$item_query_binds = array('order_id' => $order->entity_id);
        	//Get order items
        	$result = $read_connection->query($item_query, $item_query_binds);
        	$count = 0;
        	while($row = $result->fetch()) {
        		$order->items[$count] = new stdClass();
        		$order->items[$count]->quantity = $row['qty_ordered'];
        		$order->items[$count]->product_id = $row['product_id'];
        		$order->items[$count]->name = $row['name'];
        		$order->items[$count]->sku = $row['sku'];
        		$order->items[$count]->subtotal = $row['row_total'];
        		$order->items[$count]->tax = $row['tax_amount'];
        		$order->items[$count]->total = $row['row_total_incl_tax'];
        		$count++;
        	} 

        	//Get shipping and billing addresses
        	$address_query_binds = array(
        		'shipping_entity_id' => $order->shippings[0]->shipping_address,
        		'billing_entity_id' => $order->billing_address
        	);
        	$result = $read_connection->query($address_query, $address_query_binds);
        	while($row = $result->fetch()) {
        		$order->shippings[0]->shipping_state = $row['shipping_region'];
        		$order->shippings[0]->shipping_postcode = $row['shipping_postcode'];
        		$order->shippings[0]->shipping_last_name = "";
        		if($row['shipping_middlename'] !== null) {
        			$order->shippings[0]->shipping_last_name .= $row['shipping_middlename'] . " ";
        		}
        		$order->shippings[0]->shipping_last_name .= $row['shipping_lastname'];
        		$order->shippings[0]->shipping_address = $row['shipping_address'];
        		$order->shippings[0]->shipping_city = $row['shipping_city'];
        		$order->shippings[0]->shipping_first_name = $row['shipping_firstname'];
        		$order->shippings[0]->shipping_company = $row['shipping_company'];
        		$order->shippings[0]->shipping_country = $row['shipping_country'];

        		$order->billing_region = $row['billing_region'];
        		$order->billing_postcode = $row['billing_postcode'];
        		$order->billing_last_name = "";
        		if($row['billing_middlename'] !== null) {
        			$order->billing_last_name .= $row['billing_middlename'] . " ";
        		}
        		$order->billing_last_name .= $row['billing_lastname'];
        		$order->billing_address = $row['billing_address'];
        		$order->billing_city = $row['billing_city'];
        		$order->customer_phone = $row['billing_phone'];
        		$order->billing_first_name = $row['billing_firstname'];
        		$order->billing_company = $row['billing_company'];
        		$order->billing_country = $row['billing_country'];
        	}
        }
        unset($order);

    	return $orders;
    }

    function get_customers($id) {
        $customers = array();

        $customers_collection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('email')
            ->addAttributeToSelect('firstname')
            ->addAttributeToSelect('lastname');

        if($id) {
            $customers_collection->addAttributeToFilter('entity_id', $id);
        }

        foreach ($customers_collection as $customer) {
            $customerData = $customer->getData();
            $customer = new stdClass();
            $customer->id = $customerData['entity_id'];
            $customer->first_name = $customerData['firstname'];
            $customer->last_name = $customerData['lastname'];
            $customer->email = $customerData['email'];


            $customer_object = Mage::getModel('customer/customer')->load($customer->id);
            $customer_addresses_array = $customer_object->getAddresses();
            if(count($customer_addresses_array) > 0) {
                foreach($customer_addresses_array as $customer_address) {
                    $address_array = $customer_address->toArray();
                    var_dump($address_array);
                    echo "<br><br>";
                    $customer->billing_address = $address_array['street'];
                    $customer->billing_city = $address_array['city'];
                    $customer->billing_postcode = $address_array['postcode'];
                    if(isset($address_array['region'])) {
                        $customer->billing_region = $address_array['region'];
                    }
                    $customer->billing_country = $address_array['country_id'];
                    $customer->phone = $address_array['telephone'];
                    break;
                }
            }

            $customers[] = $customer;
        }

        return $customers;
    }
}
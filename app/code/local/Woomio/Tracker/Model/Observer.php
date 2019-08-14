<?php
class Woomio_Tracker_Model_Observer
{
	public static function w_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
		error_log('An error occurred sending purchase to woomio, and was bypassed. ' . $errno . ': ' . $errstr);
		return true;
	}

	public function registerOrder(Varien_Event_Observer $observer)
	{
		$Order 		= $observer->getEvent()->getOrder();
		$OrderId 	= $Order->getId();

		$WascID 	= Mage::getModel('core/cookie')->get('wacsid');

		//We do not log purchases that are not affiliates
		if(!$WascID) {
			return;
		}

		$Resource 	= Mage::getSingleton('core/resource');
		$Connection = Mage::getSingleton('core/resource')->getConnection('core_write');
		$WoomioTable = Mage::getSingleton("core/resource")->getTableName('woomio');

		$InsertSQL = "INSERT INTO `".$WoomioTable."` (`orderid`,`wacsid`) VALUES ('".$OrderId."','".$WascID."')";
		$Connection = Mage::getSingleton('core/resource')->getConnection('core_write');
		$Connection->query($InsertSQL);

		$url = urlencode($_SERVER['SERVER_NAME']);

		$CallbackUrl = "https://www.woomio.com/endpoints/purchase?sid=" . urlencode($WascID) . "&oid=" . urlencode($OrderId) . "&ot=" . urlencode($Order->getSubtotal()) . "&url=0&oc=" . urlencode($Order->getBaseCurrencyCode()) . "&email=" . urlencode($Order->getCustomerEmail()) . "&url=" . $url;

		//Ignore errors returned by the server
        	$context = stream_context_create(array(
            		'http' => array(
                		'ignore_errors' => true,
                		'timeout' => 10 //seconds
            		)
        	));

        	set_error_handler(array('Woomio_Tracker_Model_Observer', 'w_error_handler'));
        	@file_get_contents($CallbackUrl, false, $context);
        	restore_error_handler();

		//TODO: Figure out how to make fsockopen stable, since it is a faster connection.
		/*$parts = parse_url($CallbackUrl);

		$host = $parts['host'];

		$path = $parts['path'];
		if($parts['query'] != "") {
			$path .= "?" . $parts['query'];
		}

		set_error_handler(array('Woomio_Tracker_Model_Observer', 'w_error_handler'));
		$file_pointer = fsockopen("ssl://" . $host, 443, $errno, $errstr, 10);
		restore_error_handler();

		if(!$file_pointer) {
			error_log("Error opening socket to woomio server: " . $errstr .  "(" . $errno . ").", 0);
		}
		else {
			$out = "GET " . $path . " HTTP/1.1\r\n";
			$out .= "Host: " . $host . "\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Connection: Close\r\n\r\n";
			$fwrite = fwrite($file_pointer, $out);
			stream_set_timeout($file_pointer, 2);

			if($fwrite === false) {
				error_log("Error sending request to woomio server: Error writing to socket.", 0);
			}
			fclose($file_pointer);
		}*/
	}
}

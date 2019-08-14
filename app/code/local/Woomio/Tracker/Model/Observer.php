<?php
class Woomio_Tracker_Model_Observer
{
	const WOOMIO_API = "https://www.woomio.com/endpoints";
	//const WOOMIO_API = "https://test.woomio.com/endpoints";

	public static function w_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
		error_log('An error occurred sending purchase to woomio, and was bypassed. ' . $errno . ': ' . $errstr);
		return true;
	}

	public function registerOrder(Varien_Event_Observer $observer)
	{
		$Order 		= $observer->getPayment()->getOrder();
		$OrderId 	= $Order->getIncrementId();

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

		$CallbackUrl = self::WOOMIO_API . "/purchase?sid=" . urlencode($WascID) . "&oid=" . urlencode($OrderId) . "&ot=" . urlencode($Order->getSubtotal()) . "&url=0&oc=" . urlencode($Order->getBaseCurrencyCode()) . "&email=" . urlencode($Order->getCustomerEmail()) . "&url=" . $url;

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
	}
}

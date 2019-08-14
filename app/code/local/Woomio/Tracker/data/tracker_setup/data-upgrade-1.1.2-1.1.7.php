<?php
function w_error_handler_1102($errno, $errstr, $errfile, $errline, $errcontext) {
	error_log('An error occurred registering with woomio backend, and was bypassed. ' . $errno . ': ' . $errstr);
	return true;
}

//Check if data_key is already in config and that it is a number
$data_key = Mage::getStoreConfig('tracker/general/data_key');
if(is_numeric($data_key) === false) {
	$email = Mage::getStoreConfig('trans_email/ident_general/email');
	$domain = Mage::getStoreConfig('web/unsecure/base_url');
	$lang = substr(Mage::getStoreConfig('general/locale/code'), 0, 2);
	$name = Mage::getStoreConfig('trans_email/ident_general/name');

	error_log("Updating to woomio plugin 1.1.7 from 1.1.2. Email: " . $email . "; Domain: " . $domain . "; Lang: " . $lang . "; Name: " . $name);

	$setup_callback_url = 'https://www.woomio.com/endpoints/RetailerSignup?name=' . urlencode($name) . '&domain=' . urlencode($domain) . '&country=' . urlencode($lang) . '&email=' . urlencode($email) . '&platform=1';

	//Ignore errors returned by the server
	$context = stream_context_create(array(
		'http' => array('ignore_errors' => true)
	));

	set_error_handler('w_error_handler_1102');
	$response = @file_get_contents($setup_callback_url, false, $context);
	restore_error_handler();

	if($response !== false) {
		$configModel = new Mage_Core_Model_Config();
		//We save to default since the plugin can only be one in a multistore setup anyhow
		$configModel->saveConfig('tracker/general/data_key', $response, 'default', 0);
		//Make sure cache gets updated with the new config
		$configModel->reinit();
		Mage::app()->reinitStores();
	}
}
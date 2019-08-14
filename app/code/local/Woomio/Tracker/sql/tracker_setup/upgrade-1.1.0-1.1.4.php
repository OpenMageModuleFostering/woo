<?php
function w_error_handler_110($errno, $errstr, $errfile, $errline, $errcontext) {
	error_log('An error occurred registering with woomio backend, and was bypassed. ' . $errno . ': ' . $errstr);
	return true;
}

$installer 	= $this;
$installer->startSetup();

//Check if data_key is already in config and that it is a number
$data_key = Mage::getStoreConfig('tracker/general/data_key');
if(is_numeric($data_key) === false) {
	$sql 		= 'DROP TABLE IF EXISTS `'.$this->getTable('woomio').'`;';
	$sql		.= 'CREATE TABLE '.$this->getTable('woomio').'(orderid int not null auto_increment, wacsid varchar(100), primary key(orderid));';

	$Email 		= Mage::getStoreConfig('trans_email/ident_general/email');
	$Domain 	= Mage::getStoreConfig('web/unsecure/base_url');
	$Lang		= substr(Mage::getStoreConfig('general/locale/code'),0,2);
	$Name		= Mage::getStoreConfig('trans_email/ident_general/name');

	error_log("Updating to woomio plugin 1.1.4 from 1.1.0. Email: " . $Email . "; Domain: " . $Domain . "; Lang: " . $Lang . "; Name: " . $Name);

	$SetupCallbackUrl = 'https://www.woomio.com/endpoints/RetailerSignup?name=' . urlencode($Name) . '&domain=' . urlencode($Domain) . '&country=' . urlencode($Lang) . '&email=' . urlencode($Email) . '&platform=1';

	//Ignore errors returned by the server
	$context = stream_context_create(array(
		'http' => array('ignore_errors' => true)
	));

	set_error_handler('w_error_handler_110');
	$Response = @file_get_contents($SetupCallbackUrl, false, $context);
	restore_error_handler();

	if($Response !== false) {
		$configModel = new Mage_Core_Model_Config();
		//We save to default since the plugin can only be one in a multistore setup anyhow
		$configModel->saveConfig('tracker/general/data_key', $Response, 'default', 0);
		//Make sure cache gets updated with the new config
        	$configModel->reinit();
        	Mage::app()->reinitStores();
	}

	$installer->run($sql);
}

$installer->endSetup();

<?php
$installer 	= $this;
$installer->startSetup();
$sql		='create table '.$this->getTable('woomio').'(orderid int not null auto_increment, wacsid varchar(100), primary key(orderid));';

$Email 		= Mage::getStoreConfig('trans_email/ident_general/email');
$Domain 	= Mage::getStoreConfig('web/unsecure/base_url');
$Lang		= substr(Mage::getStoreConfig('general/locale/code'),0,2);
$Name		= Mage::getStoreConfig('general/store_information/name');

$SetupCallbackUrl = "https://www.woomio.com/api/Endpoints/RetailerSignup?name=".$Name."&domain=".$Domain."&country=".$Lang."&email=".$Email."&platform=1";
$Response = file_get_contents($$SetupCallbackUrl);

$installer->run($sql);
$installer->endSetup();
	 
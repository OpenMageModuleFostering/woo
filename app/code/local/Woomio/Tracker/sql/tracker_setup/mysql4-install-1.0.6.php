<?php
$installer 	= $this;
$installer->startSetup();
$sql		='create table '.$this->getTable('woomio').'(orderid int not null auto_increment, wacsid varchar(100), primary key(orderid));';

$Email 		= Mage::getStoreConfig('trans_email/ident_general/email');
$Domain 	= Mage::getStoreConfig('web/unsecure/base_url');
$Lang		= substr(Mage::getStoreConfig('general/locale/code'),0,2);
$Name		= Mage::getStoreConfig('trans_email/ident_general/name');

$SetupCallbackUrl = 'https://www.woomio.com/umbraco/api/Endpoints/RetailerSignup?name='.$Name.'&domain='.$Domain.'&country='.$Lang.'&email='.$Email.'&platform=1';
$Response = file_get_contents($SetupCallbackUrl);

$FilePath = $_SERVER['DOCUMENT_ROOT'] . '/app/design/frontend/base/default/layout/tracker.xml';
$Xml = file_get_contents($FilePath);
$ConfigFile = fopen($FilePath, 'w');
fwrite($ConfigFile, str_replace('.js"', '.js" data-r=' . $Response, $Xml));
fclose($ConfigFile);

$installer->run($sql);
$installer->endSetup();
	 
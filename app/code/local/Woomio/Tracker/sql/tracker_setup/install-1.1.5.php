<?php
$this->startSetup();

$woomio_table = $this->getTable('woomio');
if($this->getConnection()->isTableExists($woomio_table) !== true) {
	$sql = "CREATE TABLE " . $this->getTable('woomio') . "(orderid int not null auto_increment, wacsid varchar(100), primary key(orderid));";
	$this->run($sql);
}

$this->endSetup();

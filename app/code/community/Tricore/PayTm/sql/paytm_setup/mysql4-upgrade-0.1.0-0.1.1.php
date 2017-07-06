<?php
//script to create new table
$installer = $this;
$installer->startSetup();
$installer->run("
	ALTER TABLE {$this->getTable('paytm')}
	CHANGE COLUMN `cust_id` `cust_ord_id` VARCHAR(45),
	ADD COLUMN `paytm_ord_id` VARCHAR(45) NOT NULL AFTER `cust_ord_id`;
	");

$installer->endSetup();



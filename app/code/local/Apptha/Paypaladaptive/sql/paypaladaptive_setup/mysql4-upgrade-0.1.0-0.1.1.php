<?php

/**
 *  Upgrade New table for Apptha Paypal Adaptive version 0.1.1
 *
 * @package         Apptha PayPal Adaptive
 * @version         0.1.1
 * @since           Magento 1.5
 * @author          Apptha Team
 * @copyright       Copyright (C) 2014 Powered by Apptha
 * @license         http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @Creation Date   March 27,2014
 * @Modified By     Ramkumar M
 * @Modified Date   March 27,2014
 *
 * */

$installer = $this;

$installer->startSetup();

$installer->run("
ALTER TABLE  {$this->getTable('paypaladaptivedetails')} ADD  `payment_method` varchar(255) DEFAULT NULL;
");

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('paypaladaptivedelaychained')};    
CREATE TABLE {$this->getTable('paypaladaptivedelaychained')} (
  `paypaladaptivedelaychained_id` int(11) NOT NULL AUTO_INCREMENT,
  `increment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `receiver_id` varchar(25) DEFAULT NULL,
  `receiver_amount` decimal(12,4)  DEFAULT NULL,
  `currency_code` varchar(25) NOT NULL,
  `pay_key` varchar(255) NOT NULL, 
  `tracking_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `receiver_transaction_id` varchar(255) DEFAULT NULL,   
  `buyer_paypal_mail` varchar(255) DEFAULT NULL,  
  `transaction_status` varchar(255) DEFAULT NULL,
  `is_paid` int(11) NOT NULL,
  `executepayment_date` datetime NULL, 
  PRIMARY KEY (`paypaladaptivedelaychained_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
);

$installer->endSetup();
<?php

/**
 * In this class contains all the database manipulation functionality.
 *
 * @package         Apptha PayPal Adaptive
 * @version         0.1.1
 * @since           Magento 1.5
 * @author          Apptha Team
 * @copyright       Copyright (C) 2014 Powered by Apptha
 * @license         http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @Creation Date   January 16,2014
 * @Modified By     Ramkumar M
 * @Modified Date   March 26,2014
 *
 * */
class Apptha_Paypaladaptive_Model_Save {

    /**
     * Save payment details to paypaladaptivedetails table
     *
     * @param int $orderId order id
     * @param int $invoiceId invoice id
     * @param string $dataSellerId receiver id
     * @param decimal $dataAmount receiver amount
     * @param decimal $dataCommissionFee receiver commission
     * @param string $dataCurrencyCode currency code
     * @param string $dataPayKey PayPal pay key
     * @param string $dataGroupType receiver group type
     * @param string $dataTrackingId PayPal tracking id
     * @param decimal $grandTotal Order grand total
     * @param string $paymentMethod payment method
     */
    public function saveOrderData($orderId, $invoiceId, $dataSellerId, $dataAmount, $dataCommissionFee, $dataCurrencyCode, $dataPayKey, $dataGroupType, $dataTrackingId, $grandTotal, $paymentMethod) {

        /*
         * If checking whether seller or owner for store data 
         */
        try {
            $paymentCollection = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                    ->addFieldToFilter('seller_invoice_id', $invoiceId)
                    ->addFieldToFilter('seller_id', $dataSellerId);

            if (count($paymentCollection) >= 1) {
                try {
                    $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedetails');
                    $connection = Mage::getSingleton('core/resource')
                            ->getConnection('core_write');
                    $connection->beginTransaction();
                    $where[] = $connection->quoteInto('seller_invoice_id = ?', $invoiceId);
                    $where[] = $connection->quoteInto('seller_id = ?', $dataSellerId);
                    $connection->delete($table_name, $where);
                    $connection->commit();
                } catch (Mage_Core_Exception $e) {
                    Mage::getSingleton('checkout/session')->addError($e->getMessage());
                    return;
                }
            }

            /*
             * Assigning seller payment data 
             */
            $collections = Mage::getModel('paypaladaptive/paypaladaptivedetails');
            $collections->setSellerInvoiceId($invoiceId);
            $collections->setOrderId($orderId);
            $collections->setSellerId($dataSellerId);
            $collections->setSellerAmount($dataAmount);
            $collections->setCommissionAmount($dataCommissionFee);
            $collections->setGrandTotal($grandTotal);
            $collections->setCurrencyCode($dataCurrencyCode);
            $collections->setOwnerPaypalId(Mage::helper('paypaladaptive')->getAdminPaypalId());
            $collections->setPayKey($dataPayKey);
            $collections->setGroupType($dataGroupType);
            $collections->setTrackingId($dataTrackingId);
            $collections->setTransactionStatus('Pending');
            $collections->setPaymentMethod($paymentMethod);
            $collections->save();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            return;
        }
    }

    /**
     * Update transaction id and status in paypaladaptivedetails table
     *
     * @param string $dataPayKey PayPal pay key
     * @param string $dataTrackingId PayPal tracking id
     * @param string $receiverTransactionId receiver transaction id
     * @param string $receiverTransactionStatus receiver transaction status
     * @param string $senderEmail sender PayPal mail id
     * @param string $receiverEmail receiver PayPal mail id
     * @param string $receiverInvoiceId receiver receiver invoice id  
     */
    public function update($payKey, $trackingId, $receiverTransactionId, $receiverTransactionStatus, $senderEmail, $receiverEmail, $receiverInvoiceId) {

        $collections = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                ->addFieldToFilter('pay_key', $payKey)
                ->addFieldToFilter('tracking_id', $trackingId)
                ->addFieldToFilter('seller_id', $receiverEmail)
                ->addFieldToFilter('seller_invoice_id', $receiverInvoiceId);

        if (count($collections) >= 1) {
            try {
                /*
                 * Change transaction status first letter capital 
                 */
                $receiverTransactionStatus = str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($receiverTransactionStatus))));

                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedetails');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['seller_transaction_id'] = $receiverTransactionId;
                $fields['buyer_paypal_mail'] = $senderEmail;
                $fields['transaction_status'] = $receiverTransactionStatus;
                $where[] = $connection->quoteInto('pay_key = ?', $payKey);
                $where[] = $connection->quoteInto('tracking_id = ?', $trackingId);
                $where[] = $connection->quoteInto('seller_invoice_id = ?', $receiverInvoiceId);
                $where[] = $connection->quoteInto('seller_id = ?', $receiverEmail);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('checkout/session')->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * Refund payment action
     *
     * @param int $orderId order id
     * @param int $invoiceId invoice id
     * @param string $dataPayKey PayPal pay key
     * @param string $dataTrackingId PayPal tracking id
     * @param string $transactionId transactin id
     * @param string $encryptedRefundTransactionId encrypted refund transaction id
     * @param string $refundStatus refund status
     * @param decimal $refundNetAmount refund net amount 
     * @param decimal $refundFeeAmount refund fee amount
     * @param decimal $refundGrossAmount refund gross amount
     * @param string $refundTransactionStatus refund transaction status
     * @param string $receiverEmail receiver email
     * @param string $currencyCode currency code
     */
    public function refund($orderId, $incrementId, $payKey, $trackingId, $transactionId, $encryptedRefundTransactionId, $refundStatus, $refundNetAmount, $refundFeeAmount, $refundGrossAmount, $refundTransactionStatus, $receiverEmail, $currencyCode) {

        try {
            $payDetails = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                    ->addFieldToFilter('seller_invoice_id', $incrementId)
                    ->addFieldToFilter('pay_key', $payKey)
                    ->addFieldToFilter('tracking_id', $trackingId)
                    ->addFieldToFilter('seller_id', $receiverEmail);

            $firstRow = Mage::helper('paypaladaptive')->getFirstRowData($payDetails);

            if (!empty($firstRow['buyer_paypal_mail'])) {
                $buyerPaypalMail = $firstRow['buyer_paypal_mail'];
            } else {
                $buyerPaypalMail = '';
            }
            /*
             * Change transaction status first letter capital 
             */
            $refundStatus = str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($refundStatus))));

            /*
             * Assigning payment data 
             */
            $collections = Mage::getModel('paypaladaptive/refunddetails');
            $collections->setIncrementId($incrementId);
            $collections->setOrderId($orderId);
            $collections->setSellerPaypalId($receiverEmail);
            $collections->setPayKey($payKey);
            $collections->setTrackingId($trackingId);
            $collections->setTransactionId($transactionId);
            $collections->setEncryptedRefundTransactionId($encryptedRefundTransactionId);
            $collections->setRefundNetAmount($refundNetAmount);
            $collections->setRefundFeeAmount($refundFeeAmount);
            $collections->setRefundGrossAmount($refundGrossAmount);
            $collections->setbuyerPaypalMail($buyerPaypalMail);
            $collections->setRefundTransactionStatus($refundTransactionStatus);
            $collections->setRefundStatus($refundStatus);
            $collections->setCurrencyCode($currencyCode);

            $collections->save();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            return;
        }
    }

    /*
     * Collect seller PayPal id for refund process
     * 
     * @param int $incrementId increment id
     * @param string $sellerId seller id
     * 
     * @return string $sellerPaypalId seller PayPal id
     */

    public function sellerPaypalIdForRefund($incrementId, $sellerId) {

        $collections = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                ->addFieldToFilter('seller_invoice_id', $incrementId)
                ->addFieldToFilter('seller_id', $sellerId);

        $sellerPaypalId = '';
        $firstRow = Mage::helper('paypaladaptive')->getFirstRowData($collections);
        if (!empty($firstRow)) {
            $sellerPaypalId = $firstRow['seller_id'];
        }

        return $sellerPaypalId;
    }

    /*
     * Collect seller refund data
     * 
     * @param array $items items
     * @param string $incrementId increment id
     * @param int $flag flag
     * 
     * @return array $sellerData seller data
     */

    public function sellerDataForRefund($items, $incrementId, $flag) {

        $sellerData = array();
        /*
         * Preparing seller share 
         */
        foreach ($items as $item) {

            $sellerAmount = 0;
            $productId = $item->getProductId();

            $commissionData = Mage::getModel('paypaladaptive/commissiondetails')->getCollection()
                    ->addFieldToFilter('product_id', $productId)
                    ->addFieldToFilter('increment_id', $incrementId);
            $firstRow = Mage::helper('paypaladaptive')->getFirstRowData($commissionData);

            if (!empty($firstRow['seller_id'])) {
                $commissionValue = $firstRow['commission_value'];
                $commissionMode = $firstRow['commission_mode'];
                $sellerId = $firstRow['seller_id'];

                if ($flag == 1) {
                    $productAmount = $item->getPrice() * $item->getQtyInvoiced();
                } else {
                    $productAmount = $item->getPrice() * $item->getQty();
                }

                if ($commissionMode == 'percent') {
                    $productCommission = $productAmount * ($commissionValue / 100);
                    $sellerAmount = $productAmount - $productCommission;
                } else {
                    $productCommission = $commissionValue;
                    $sellerAmount = $productAmount - $commissionValue;
                }
                /*
                 * Calculating seller share individually
                 */
                if (array_key_exists($sellerId, $sellerData)) {
                    $sellerData[$sellerId]['amount'] = $sellerData[$sellerId]['amount'] + $sellerAmount;
                    $sellerData[$sellerId]['commission_fee'] = $sellerData[$sellerId]['commission_fee'] + $productCommission;
                } else {
                    $sellerData[$sellerId]['amount'] = $sellerAmount;
                    $sellerData[$sellerId]['commission_fee'] = $productCommission;
                    $sellerData[$sellerId]['seller_id'] = $sellerId;
                }
            }
        }
        return $sellerData;
    }

    /*
     * Save commission details to paypaladaptivecommissiondetails table
     *    
     * @param string $incrementId increment id
     * @param int $productId product id
     * @param decimal $commissionValue commission value
     * @param string $commissionMode commission mode
     * @param string $sellerId seller PayPal id
     */

    public function saveCommissionData($incrementId, $productId, $commissionValue, $commissionMode, $sellerId) {

        try {
            $commissionData = Mage::getModel('paypaladaptive/commissiondetails')->getCollection()
                    ->addFieldToFilter('product_id', $productId)
                    ->addFieldToFilter('increment_id', $incrementId);
            $firstRow = Mage::helper('paypaladaptive')->getFirstRowData($commissionData);

            if (!empty($firstRow['product_id']) && $firstRow['product_id'] == $productId) {

                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivecommissiondetails');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['commission_mode'] = $commissionMode;
                $fields['commission_value'] = $commissionValue;
                $fields['seller_id'] = $sellerId;
                $where[] = $connection->quoteInto('product_id = ?', $productId);
                $where[] = $connection->quoteInto('increment_id = ?', $incrementId);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
            } else {
                /*
                 * Assigning seller payment data
                 */
                $collections = Mage::getModel('paypaladaptive/commissiondetails');
                $collections->setProductId($productId);
                $collections->setIncrementId($incrementId);
                $collections->setCommissionMode($commissionMode);
                $collections->setCommissionValue($commissionValue);
                $collections->setSellerId($sellerId);
                $collections->save();
            }
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            return;
        }
    }

    /**
     * Update payment status as refunded
     *
     * @param int $incrementId increment id
     * @param string $payKey pay key
     * @param string $dataTrackingId PayPal tracking id
     * @param string $receiverEmail receiver PayPal id
     */
    public function changePaymentStatus($incrementId, $payKey, $trackingId, $receiverEmail) {

        $collections = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                ->addFieldToFilter('pay_key', $payKey)
                ->addFieldToFilter('tracking_id', $trackingId)
                ->addFieldToFilter('seller_id', $receiverEmail)
                ->addFieldToFilter('seller_invoice_id', $incrementId);

        if (count($collections) >= 1) {
            try {
                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedetails');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['transaction_status'] = 'Refunded';
                $where[] = $connection->quoteInto('pay_key = ?', $payKey);
                $where[] = $connection->quoteInto('tracking_id = ?', $trackingId);
                $where[] = $connection->quoteInto('seller_invoice_id = ?', $incrementId);
                $where[] = $connection->quoteInto('seller_id = ?', $receiverEmail);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * Update payment status as canceled
     *
     * @param int $paypalAdaptive invoice id
     * @param string $payKey pay key
     * @param string $dataTrackingId PayPal tracking id 
     */
    public function cancelPayment($paypalAdaptive, $payKey, $trackingId) {

        $collections = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
                ->addFieldToFilter('pay_key', $payKey)
                ->addFieldToFilter('tracking_id', $trackingId)
                ->addFieldToFilter('seller_invoice_id', $paypalAdaptive);

        if (count($collections) >= 1) {
            try {
                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedetails');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['transaction_status'] = 'Canceled';
                $where[] = $connection->quoteInto('pay_key = ?', $payKey);
                $where[] = $connection->quoteInto('tracking_id = ?', $trackingId);
                $where[] = $connection->quoteInto('seller_invoice_id = ?', $paypalAdaptive);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('checkout/session')->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * Save delayed chained payment details paypaladaptivedelaychained table
     *
     * @param int $orderId order id
     * @param int $incrementId increment id
     * @param string $dataCurrencyCode currency code
     * @param string $dataPayKey PayPal pay key 
     * @param string $dataTrackingId PayPal tracking id
     * @param string $adminEmail receiver email id
     * @param decimal $adminAmount receiver amount
     */
    public function saveDelayedOrderData($orderId, $incrementId, $dataCurrencyCode, $dataPayKey, $dataTrackingId, $adminEmail, $adminAmount) {
        /*
         * If checking whether seller or owner for store data
         */
        try {
            $paymentCollection = Mage::getModel('paypaladaptive/delaychaineddetails')->getCollection()
                    ->addFieldToFilter('increment_id', $incrementId);


            if (count($paymentCollection) >= 1) {
                try {
                    $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedelaychained');
                    $connection = Mage::getSingleton('core/resource')
                            ->getConnection('core_write');
                    $connection->beginTransaction();
                    $where[] = $connection->quoteInto('increment_id = ?', $incrementId);
                    $connection->delete($table_name, $where);
                    $connection->commit();
                } catch (Mage_Core_Exception $e) {
                    Mage::getSingleton('checkout/session')->addError($e->getMessage());
                    return;
                }
            }

            /*
             * Getting no of days to pay for secondary receivers (seller/host)
             */
            $noDaysToPay = (int) Mage::helper('paypaladaptive')->getExecutePaymentDays();
            if (empty($noDaysToPay)) {
                $noDaysToPay = 30;
            }

            /*
             * Calculating execution date for secondary receivers (seller/host)
             */
            $currentDate = date("Y-m-d H:i:s", Mage::getModel('core/date')->timestamp(time()));
            $paymentExecuteTime = strtotime(date("Y-m-d H:i:s", strtotime($currentDate)) . " +$noDaysToPay days");
            $paymentExecuteDate = date("Y-m-d", $paymentExecuteTime);

            /*
             * Assigning seller payment data
             */
            $collections = Mage::getModel('paypaladaptive/delaychaineddetails');
            $collections->setIncrementId($incrementId);
            $collections->setOrderId($orderId);
            $collections->setReceiverId($adminEmail);
            $collections->setReceiverAmount($adminAmount);
            $collections->setCurrencyCode($dataCurrencyCode);
            $collections->setPayKey($dataPayKey);
            $collections->setTrackingId($dataTrackingId);
            $collections->setExecutepaymentDate($paymentExecuteDate);
            $collections->setTransactionStatus('Pending');
            $collections->setIsPaid(0);
            $collections->save();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            return;
        }
    }

    /**
     * Update delayed chained payment transaction status 
     * 
     * @param string $dataPayKey PayPal pay key 
     * @param string $dataTrackingId PayPal tracking id
     * @param string $receiverTransactionId receiver transaction id
     * @param string $receiverTransactionStatus receiver transaction status
     * @param string $senderEmail sender PayPal id
     * @param int $incrementId increment id
     */
    public function updateDelayedChained($payKey, $trackingId, $receiverTransactionId, $receiverTransactionStatus, $senderEmail, $incrementId) {

        $collections = Mage::getModel('paypaladaptive/delaychaineddetails')->getCollection()
                ->addFieldToFilter('pay_key', $payKey)
                ->addFieldToFilter('tracking_id', $trackingId)
                ->addFieldToFilter('increment_id', $incrementId);

        if (count($collections) >= 1) {
            try {
                /*
                 * Change transaction status first letter capital 
                 */
                $receiverTransactionStatus = str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($receiverTransactionStatus))));

                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedelaychained');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['receiver_transaction_id'] = $receiverTransactionId;
                $fields['buyer_paypal_mail'] = $senderEmail;
                $fields['transaction_status'] = $receiverTransactionStatus;
                $where[] = $connection->quoteInto('pay_key = ?', $payKey);
                $where[] = $connection->quoteInto('tracking_id = ?', $trackingId);
                $where[] = $connection->quoteInto('increment_id = ?', $incrementId);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('checkout/session')->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * Update adaptive payment details by delay chained method status
     * 
     * @param string $dataPayKey PayPal pay key 
     * @param string $dataTrackingId PayPal tracking id
     * @param string $receiverTransactionId receiver transaction id
     */
    public function updateAdaptivePaymentDetails($payKey, $trackingId, $transactionId) {
        $resArray = Mage::getModel('paypaladaptive/apicall')->CallPaymentDetails($payKey, $transactionId, $trackingId);
        $ack = strtoupper($resArray["responseEnvelope.ack"]);
        if ($ack == "SUCCESS" && isset($resArray["paymentInfoList.paymentInfo(0).transactionId"]) && $resArray["paymentInfoList.paymentInfo(0).transactionId"] != '') {
            try {
                /*
                 * Update payment details such as transaction id, status, receiver mail and receiver invoice id 
                 */
                for ($inc = 0; $inc <= 5; $inc++) {

                    if (!empty($resArray["paymentInfoList.paymentInfo($inc).transactionId"])) {
                        $receiverTransactionId = $resArray["paymentInfoList.paymentInfo($inc).transactionId"];
                    } else {
                        $receiverTransactionId = '';
                    }

                    if (!empty($resArray["paymentInfoList.paymentInfo($inc).transactionStatus"])) {
                        $receiverTransactionStatus = $resArray["paymentInfoList.paymentInfo($inc).transactionStatus"];
                    } else {
                        $receiverTransactionStatus = 'Pending';
                    }

                    $senderEmail = $resArray["senderEmail"];
                    $receiverEmail = $resArray["paymentInfoList.paymentInfo($inc).receiver.email"];
                    $receiverInvoiceId = $resArray["paymentInfoList.paymentInfo($inc).receiver.invoiceId"];

                    /*
                     * Update transaction id and status in paypaladaptivedetails table
                     */
                    Mage::getModel('paypaladaptive/save')->update($payKey, $trackingId, $receiverTransactionId, $receiverTransactionStatus, $senderEmail, $receiverEmail, $receiverInvoiceId);
                }
                return;
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                return;
            }
        }
    }

    /**
     * Update  delay chained payment details
     * 
     * @param int $orderId order id
     * @param string $dataPayKey PayPal pay key 
     * @param string $dataTrackingId PayPal tracking id
     */
    public function updateDelayedChainedPaymentDetails($orderId, $payKey, $trackingId) {

        $collections = Mage::getModel('paypaladaptive/delaychaineddetails')->getCollection()
                ->addFieldToFilter('pay_key', $payKey)
                ->addFieldToFilter('tracking_id', $trackingId)
                ->addFieldToFilter('order_id', $orderId);

        if (count($collections) >= 1) {
            try {

                $table_name = Mage::getSingleton('core/resource')->getTableName('paypaladaptivedelaychained');
                $connection = Mage::getSingleton('core/resource')
                        ->getConnection('core_write');
                $connection->beginTransaction();
                $fields = array();
                $fields['is_paid'] = 1;
                $where[] = $connection->quoteInto('pay_key = ?', $payKey);
                $where[] = $connection->quoteInto('tracking_id = ?', $trackingId);
                $where[] = $connection->quoteInto('order_id = ?', $orderId);
                $connection->update($table_name, $fields, $where);
                $connection->commit();
                return;
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                return;
            }
        }
    }

}
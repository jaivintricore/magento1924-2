<?php

/**
 * In this class contains payment functinality like success, failure and cancel
 *
 * @package         Apptha PayPal Adaptive
 * @version         0.1.1
 * @since           Magento 1.5
 * @author          Apptha Team
 * @copyright       Copyright (C) 2014 Powered by Apptha
 * @license         http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @Creation Date   January 02,2014
 * @Modified By     Ramkumar M
 * @Modified Date   March 24,2014
 *
 * */
class Apptha_Paypaladaptive_AdaptiveController extends Mage_Core_Controller_Front_Action {
    /*
     * Apptha payPal adaptive payment action
     */

    public function redirectAction() {
        /*
         *  Checking whether order id available or not
         */
        $session = Mage::getSingleton('checkout/session');
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $orderId = $order->getId();
        $orderStatus = $order->getStatus();
        if (empty($orderId) || $orderStatus != 'pending') {
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("No order for processing found"));
            $this->_redirect('checkout/cart', array('_secure' => true));
            return FALSE;
        }
        /*
         * Initilize adaptive payment data    
         */
        $actionType = "PAY";
        $cancelUrl = Mage::getUrl('paypaladaptive/adaptive/cancel', array('_secure' => true));
        $returnUrl = Mage::getUrl('paypaladaptive/adaptive/return', array('_secure' => true));
        $ipnNotificationUrl = Mage::getUrl('paypaladaptive/adaptive/ipnnotification', array('_secure' => true));
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $senderEmail = "";
        $feesPayer = Mage::helper('paypaladaptive')->getFeePayer();
        $memo = "";
        $pin = "";
        $preapprovalKey = "";
        $reverseAllParallelPaymentsOnError = "";
        $trackingId = $this->generateTrackingID();

        $enabledMarplace = Mage::helper('paypaladaptive')->getModuleInstalledStatus('Apptha_Marketplace');
        $enabledAirhotels = Mage::helper('paypaladaptive')->getModuleInstalledStatus('Apptha_Airhotels');

        /*
         * Checking where marketplace enable or not  
         */
        if ($enabledMarplace == 1) {
            /*
             * Calculating receiver data
             */
            $receiverData = Mage::helper('paypaladaptive')->getMarketplaceSellerData();
        } elseif ($enabledAirhotels == 1) {
            /*
             * Calculating receiver data
             */
            $receiverData = Mage::helper('paypaladaptive')->getAirhotelsHostData();
        } else {
            /*
             * Calculating receiver data
             */
            $receiverData = Mage::helper('paypaladaptive')->getSellerData();
        }
        /*
         * If Checking whether receiver count greater than 5 or not
         */
        $receiverCount = count($receiverData);
        if ($receiverCount > 5) {
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("You have ordered more than 5 partner products"));
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }
        /*
         * Geting checkout grand total amount 
         */
        $grandTotal = round(Mage::helper('paypaladaptive')->getGrandTotal(), 2);

        /*
         * Getting receiver amount total       
         */
        $amountTotal = $this->getAmountTotal($receiverData);

        $sellerTotal = round($amountTotal, 2);

        if ($grandTotal >= $sellerTotal) {

            /*
             * Initilize receiver data
             */
            $receiverAmountArray = $receiverEmailArray = $receiverPrimaryArray = $receiverInvoiceIdArray = array();

            /*
             * Getting invoice id
             */
            $invoiceId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
            $paypalInvoiceId = $invoiceId . $trackingId;

            /*
             * Preparing receiver data
             */
            foreach ($receiverData as $data) {
                /*
                 * Getting receiver paypal id
                 */
                $receiverPaypalId = $data['seller_id'];
                $receiverAmountArray[] = round($data['amount'], 2);
                $receiverEmailArray[] = $receiverPaypalId;
                $receiverPrimaryArray[] = 'false';
                $receiverInvoiceIdArray[] = $paypalInvoiceId;
            }

            /*
             *  Getting admin paypal id
             */

            $adminEmail = $receiverEmailArray[] = Mage::helper('paypaladaptive')->getAdminPaypalId();
            $receiverInvoiceIdArray[] = $paypalInvoiceId;
            /*
             * Getting payment method
             */
            $paymentMethod = Mage::helper('paypaladaptive')->getPaymentMethod();
            /*
             * Assign delayed chained method
             */
            if ($paymentMethod == 'delayed_chained' && $receiverCount >= 1) {
                $actionType = "PAY_PRIMARY";
            }
            /*
             * If no seller product available for checkout. Setting receiverPrimaryArray empty     
             */
            if ($receiverCount < 1) {
                $receiverPrimaryArray = array();
                /*
                 * Assigning store owner paypal id & amount
                 */
                $receiverAmountArray[] = round($grandTotal, 2);
            } elseif ($paymentMethod == 'parallel') {
                $receiverPrimaryArray[] = 'false';
                /*
                 * Assigning store owner paypal id & amount
                 */
                $receiverAmountArray[] = round($grandTotal - $sellerTotal, 2);
            } else {
                $receiverPrimaryArray[] = 'true';
                /*
                 * Assigning store owner paypal id & amount
                 */
                $adminAmount = $receiverAmountArray[] = round($grandTotal, 2);
            }
        } else {

            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Please contact admin partner amount is greater than total amount"));
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }

        $resArray = Mage::getModel('paypaladaptive/apicall')->CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId
        );

        $ack = strtoupper($resArray["responseEnvelope.ack"]);

        if ($ack == "SUCCESS") {
            $cmd = "cmd=_ap-payment&paykey=" . urldecode($resArray["payKey"]);
            /*
             * Assigning session valur for paykey , tracking id and order id
             */
            $session = Mage::getSingleton('checkout/session');
            $session->setPaypalAdaptiveTrackingId($trackingId);
            $session->setPaypalAdaptivePayKey(urldecode($resArray["payKey"]));
            $session->setPaypalAdaptiveRealOrderId($invoiceId);
            $session->setPaypalAdaptivePaymentMethod($paymentMethod);
            /*
             * Storing seller payment details to paypaladaptivedetails table 
             */
            foreach ($receiverData as $data) {
                /*
                 * Initilizing payment data for save 
                 */
                $dataSellerId = $data['seller_id'];
                $dataAmount = round($data['amount'], 2);
                $dataCommissionFee = round($data['commission_fee'], 2);
                $dataCurrencyCode = $currencyCode;
                $dataPayKey = $resArray["payKey"];
                $dataGroupType = 'seller';
                $dataTrackingId = $trackingId;

                /*
                 * Calling save function for storing seller payment data
                 */
                Mage::getModel('paypaladaptive/save')->saveOrderData($orderId, $invoiceId, $dataSellerId, $dataAmount, $dataCommissionFee, $dataCurrencyCode, $dataPayKey, $dataGroupType, $dataTrackingId, $grandTotal, $paymentMethod);
            }

            /*
             * Initilizing payment data for save 
             */
            $dataSellerId = Mage::helper('paypaladaptive')->getAdminPaypalId();
            $dataCommissionFee = 0;
            $dataCurrencyCode = $currencyCode;
            $dataPayKey = $resArray["payKey"];
            $dataGroupType = 'admin';
            $dataTrackingId = $trackingId;
            $dataAmount = $grandTotal - $sellerTotal;

            /*
             * Calling save function for storing owner payment data      
             */
            Mage::getModel('paypaladaptive/save')->saveOrderData($orderId, $invoiceId, $dataSellerId, $dataAmount, $dataCommissionFee, $dataCurrencyCode, $dataPayKey, $dataGroupType, $dataTrackingId, $grandTotal, $paymentMethod);

            if ($paymentMethod == 'delayed_chained' && $receiverCount >= 1) {
                $session->setPaypalAdaptiveDelayedChainedMethod(1);
                /*
                 * Calling save function for storing delayed payment     
                 */
                Mage::getModel('paypaladaptive/save')->saveDelayedOrderData($orderId, $invoiceId, $dataCurrencyCode, $dataPayKey, $dataTrackingId, $adminEmail, $adminAmount);
            } else {
                $session->setPaypalAdaptiveDelayedChainedMethod(0);
            }

            /*
             * Redirectr to Paypal site
             */
            $this->RedirectToPayPal($cmd);
            return;
        } else {
            $errorMsg = urldecode($resArray["error(0).message"]);
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Pay API call failed."));
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Error Message:") . ' ' . $errorMsg);
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }
    }

    /*
     * Payment success function
     */
    public function returnAction() {   
    	$this->_redirect('checkout/onepage/success', array('_secure' => true));
    	return;
    }

    /*
     * PayPal ipn notification action
     */
    public function ipnnotificationAction() {  
   	/*
   	 * Getting pay key and tracking id
   	 */
    $payKey = $_POST['pay_key'];
   	$trackingId = $_POST['tracking_id'];
   	$transactionId = '';

   	$paymentCollection = Mage::getModel('paypaladaptive/paypaladaptivedetails')->getCollection()
   	->addFieldToFilter('pay_key', $payKey)
   	->addFieldToFilter('tracking_id', $trackingId)->getFirstItem();   
   	$paypalAdaptive = $paymentCollection->getSellerInvoiceId();
   	
   	$delayedPaymentCollection = Mage::getModel('paypaladaptive/delaychaineddetails')->getCollection()
   	->addFieldToFilter('increment_id', $paypalAdaptive);
   	
   	if(count($paymentCollection) >= 1){
   	/*
   	 * Make the Payment Details call using PayPal API
   	*/
   	$resArray = Mage::getModel('paypaladaptive/apicall')->CallPaymentDetails($payKey, $transactionId, $trackingId);
   	
   	$ack = strtoupper($resArray["responseEnvelope.ack"]);
    
   	
   	if ($ack == "SUCCESS" && isset($resArray["paymentInfoList.paymentInfo(0).transactionId"]) && $resArray["paymentInfoList.paymentInfo(0).transactionId"] != '' || $ack == "SUCCESS" && count($delayedPaymentCollection)) {
   	  		 	
   		try {   	
   			$order = Mage::getModel('sales/order');
   			$order->loadByIncrementId($paypalAdaptive);   			
   		       if (count($delayedPaymentCollection)) {
                    for ($inc = 0; $inc <= 5; $inc++) {
                        if (!empty($resArray["paymentInfoList.paymentInfo($inc).transactionId"])) {
                            $transactionIdData = $resArray["paymentInfoList.paymentInfo($inc).transactionId"];
                            $transactionStatusData = $resArray["paymentInfoList.paymentInfo($inc).transactionStatus"];
                            break;
                        }
                    }
                } else {
                    $transactionIdData = $resArray["paymentInfoList.paymentInfo(0).transactionId"];
                }
   			 	
   			$order->setLastTransId($transactionIdData)->save();   	
   			if ($order->canInvoice()) {
            	if(Mage::helper('paypaladaptive')->getModuleInstalledStatus('Apptha_Marketplace') == 1 ){
                $items = $order->getAllItems ();
                $itemCount = 0;
                $sellerProduct = array();
                foreach ( $items as $item ) {
                	$products = Mage::helper ( 'marketplace/marketplace' )->getProductInfo ( $item->getProductId () );
                	$orderEmailData [$itemCount] ['seller_id'] = $products->getSellerId ();
                	$orderEmailData [$itemCount] ['product_qty'] = $item->getQtyOrdered ();
                	$orderEmailData [$itemCount] ['product_id'] = $item->getProductId ();
                	$sellerProduct[$products->getSellerId ()][$item->getProductId ()]	= $item->getQtyOrdered ();
                	$itemCount = $itemCount + 1;
                }
                $sellerIds = array ();
                foreach ( $orderEmailData as $data ) {
                	if (! in_array ( $data ['seller_id'], $sellerIds )) {
                		$sellerIds [] = $data ['seller_id'];
                	}
                }
                foreach ( $sellerIds as $id ) {
                	$itemsarray = $itemsArr = array ();
	                foreach ( $order->getAllItems () as $item ) {
	                	$productsCol = Mage::helper ( 'marketplace/marketplace' )->getProductInfo ( $item->getProductId () );
	                	$itemId = $item->getItemId ();
	                	if($productsCol->getSellerId () == $id){ 
	                		$itemsarray [$itemId] = $sellerProduct[$id][$item->getProductId ()];
	                		$itemsArr [] = $itemId;
	                	}else{
	                		$itemsarray [$itemId] = 0;
	                	}
				   }
				   /**
				    * Generate invoice for shippment.
				    */
				   Mage::getModel ( 'sales/order_invoice_api' )->create ( $order->getIncrementId (), $itemsarray, '', 1, 1 );
				   Mage::getModel ( 'marketplace/order' )->updateSellerOrderItemsBasedOnSellerItems ( $itemsArr, $order->getEntityId(), 1 );
				   }
             }else{
             	$invoice = $order->prepareInvoice();
                $invoice->register()->pay();
                $invoice->getOrder()->setIsInProcess(true);
                $status = Mage::helper('paypaladaptive')->getOrderSuccessStatus();
                $invoice->getOrder()->setData('state', $status);
                $invoice->getOrder()->setStatus($status);
                $history = $invoice->getOrder()->addStatusHistoryComment('Partial amount of captured automatically.', true);
                $history->setIsCustomerNotified(true);
                $invoice->sendEmail(true, '');
                		
                Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
                $invoice->save();
                }
                /*
                 * Saving payment success details
                 */
                for ($inc = 0; $inc <= 5; $inc++) {

                if (isset($resArray["paymentInfoList.paymentInfo($inc).transactionId"])) {
                	$receiverTransactionId = $resArray["paymentInfoList.paymentInfo($inc).transactionId"];
                } else {
                	$receiverTransactionId = '';
                }

                if (isset($resArray["paymentInfoList.paymentInfo($inc).transactionStatus"])) {
                	$receiverTransactionStatus = $resArray["paymentInfoList.paymentInfo($inc).transactionStatus"];
                } else {
                	$receiverTransactionStatus = 'Pending';
                }

                $senderEmail = $resArray["senderEmail"];
                $receiverEmail = $resArray["paymentInfoList.paymentInfo($inc).receiver.email"];
                $receiverInvoiceId = $resArray["paymentInfoList.paymentInfo($inc).receiver.invoiceId"];
                /*
                 * Updating transaction id and status
                 */
                Mage::getModel('paypaladaptive/save')->update($payKey, $trackingId, $receiverTransactionId, $receiverTransactionStatus, $senderEmail, $receiverEmail, $receiverInvoiceId);
           }

           if ($paymentMethod == 'delayed_chained') {
           /*
            * Updating delayed chained method transaction id and status
            */
            Mage::getModel('paypaladaptive/save')->updateDelayedChained($payKey, $trackingId, $transactionIdData, $transactionStatusData, $senderEmail, $paypalAdaptive);
            } 	
   		}
   	} catch (Mage_Core_Exception $e) {
   	Mage::log($e->getMessage());
   		}
   	}
   	}   	
    }

    /*
     * Order cancel action 
     */

    public function cancelAction() {

        try {
            $session = Mage::getSingleton('checkout/session');
            $paypalAdaptive = $session->getPaypalAdaptiveRealOrderId();
            $payKey = $session->getPaypalAdaptivePayKey();
            $trackingId = $session->getPaypalAdaptiveTrackingId();

            if (empty($paypalAdaptive)) {
                Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("No order for processing found"));
                $this->_redirect('checkout/cart', array('_secure' => true));
                return;
            }
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($paypalAdaptive);
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Payment Canceled."));

            /*
             * Changing payment status
             */
            Mage::getModel('paypaladaptive/save')->cancelPayment($paypalAdaptive, $payKey, $trackingId);

            $session->unsPaypalAdaptivePayKey();
            $session->unsPaypalAdaptiveTrackingId();
            $session->unsPaypalAdaptiveRealOrderId();

            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Unable to cancel Paypal Adaptive Checkout."));
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError(Mage::helper('paypaladaptive')->__("Unable to cancel Paypal Adaptive Checkout."));
            $this->_redirect('checkout/cart', array('_secure' => true));
            return;
        }
    }

    /*
     * Calculate sum of receiver amount
     * 
     * @param array $receiverData receiver data
     * @return decimal $amountTotal total amount
     */

    public function getAmountTotal($receiverData) {

        $amountTotal = 0;
        foreach ($receiverData as $data) {
            $amountTotal = $amountTotal + $data['amount'];
        }
        return $amountTotal;
    }

    /*
     * Generate key
     * 
     * @return string $char key
     */

    public function generateCharacter() {
        $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);
        return $char;
    }

    /*
     * Generate tracking id
     * 
     * @return string $GUID tracking id
     */

    public function generateTrackingID() {
        $GUID = $this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter();
        $GUID .=$this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter();
        $GUID .=$this->generateCharacter() . $this->generateCharacter() . $this->generateCharacter();
        return $GUID;
    }

    /*
     * Redirect to paypal.com here    
     */

    public function RedirectToPayPal($cmd) {
        $mode = Mage::helper('paypaladaptive')->getPaymentMode();
        $payPalURL = "";
        if ($mode == 1) {
            $payPalURL = "https://www.sandbox.paypal.com/webscr?" . $cmd;
        } else {
            $payPalURL = "https://www.paypal.com/webscr?" . $cmd;
        }
        Mage::app()->getResponse()->setRedirect($payPalURL);
        return FALSE;
    }

}
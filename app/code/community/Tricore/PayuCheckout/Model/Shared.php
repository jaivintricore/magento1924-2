<?php

class Tricore_PayuCheckout_Model_Shared extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'payucheckout_shared';
     
    protected $_isGateway                   = false;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = false;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canCreateBillingAgreement   = true;
    protected $_canReviewPayment            = true;
    
    protected $_formBlockType = 'payucheckout/shared_form';
    protected $_paymentMethod = 'shared';
    protected $_order = null;
    
    
 
	public function getCheckoutRedirectUrl() {
         return Mage::getUrl('payucheckout/shared/redirectpayu');
    }
    
    public function assignData($data) {
        $this->getInfoInstance()->setAdditionalData(serialize($data));
    }
    
    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        return $this->getCheckout()->getQuote();
    }
    
    public function getCustomer() {
        return Mage::getSingleton('customer/session')->getCustomer();
    }
    
    public function getFormFields() {
		
		$coFields = array();
		$quote = Mage::getModel('checkout/session')->getQuote();
		$quoteData= $quote->getData();
		$grandtotal = $quoteData['grand_total'];
		
		//Currency Conversion 
		$baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();//USD get store base currency code
		$currentCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();//get store current currency code
		$payumoneyCurrency='INR'; //Payumoney Curreny code
		
		//Convert order amount into rupee 
		if($currentCurrency!=$payumoneyCurrency) {
			
			$rate=Mage::app()->getStore()->getCurrentCurrencyRate();
			$value=$grandtotal/$rate;
			$convert_amount = Mage::helper('directory')->currencyConvert($value,$baseCurrency,$payumoneyCurrency);
			$payum_amount=number_format($convert_amount, 2, '.', '');
			$payumoney_amount = round($payum_amount,0);
		}
		else {
			
			$convert_amount=$grandtotal;
			$payum_amount=number_format($convert_amount, 2, '.', '');
			$payumoney_amount = round($payum_amount,0);
		}
		
		$email=$quoteData['customer_email'];
		$orderid=Mage::getSingleton('checkout/session')->getQuote()->reserveOrderId()->reserved_order_id;
		Mage::getSingleton('checkout/session')->getQuote()->reserveOrderId()->save();
		$key = Mage::getStoreConfig('payment/payucheckout_shared/key');
        $salt = Mage::getStoreConfig('payment/payucheckout_shared/salt');
        $debug_mode = Mage::getStoreConfig('payment/payucheckout_shared/debug_mode');
        
        $txnid = $orderid;
        
        $productinfo = 'Product Information';
        
        $coFields['key'] = $key;
        $coFields['txnid'] = $txnid;
        $coFields['udf2'] = $txnid;
        $coFields['amount'] = $payumoney_amount;
        $coFields['email'] = $email;
        $coFields['firstname'] = $quoteData['customer_firstname'];
        $coFields['productinfo'] = $productinfo;
        $coFields['website'] = Mage::getBaseUrl();
        $coFields['surl'] = Mage::getBaseUrl() . 'payucheckout/shared/success/';
        $coFields['furl'] = Mage::getBaseUrl() . 'payucheckout/shared/failure/';
        $coFields['curl'] = Mage::getBaseUrl() . 'payucheckout/shared/canceled/';
        $coFields['service_provider'] = 'payu_paisa';
        
        $debugId = '';
         
        if ($debug_mode == 1) {

            $requestInfo = $key . '|' . $coFields['txnid'] . '|' . $coFields['amount'] . '|' .
                    $productinfo . '|' . $coFields['firstname'] . '|' . $coFields['email'] . '|' . $debugId . '||||||||||' . $salt;
            $debug = Mage::getModel('payucheckout/api_debug')
                    ->setRequestBody($requestInfo)
                    ->save();

            $debugId = $debug->getId();

            $coFields['udf1'] = $debugId;
            $coFields['Hash'] = hash('sha512', $key . '|' . $coFields['txnid'] . '|' . $coFields['amount'] . '|' .
                    $productinfo . '|' . $coFields['firstname'] . '|' . $coFields['email'] . '|' . $debugId . '|' . $coFields['udf2'] . '|||||||||' . $salt);
        } else {
            $coFields['Hash'] = strtolower(hash('sha512', $key . '|' . $coFields['txnid'] . '|' . $coFields['amount'] . '|' .
                            $productinfo . '|' . $coFields['firstname'] . '|' . $coFields['email'] . '||' . $coFields['udf2'] . '|||||||||' . $salt));
        }
       
        return $coFields;		
		
	}
	
	/**
     * Get url of Payu payment
     *
     * @return string
     */
    public function getPayuCheckoutSharedUrl() {
		
        $mode = Mage::getStoreConfig('payment/payucheckout_shared/demo_mode');

        $url = 'https://test.payu.in/_payment.php';

        if ($mode == '') {
            $url = 'https://secure.payu.in/_payment.php';
        }

        return $url;
    }
    
	/**
     * Get Order Transaction id 
     */
	protected function _getParentTransactionId(Varien_Object $payment) {
        return $payment->getParentTransactionId() ? $payment->getParentTransactionId() : $payment->getLastTransId();
    }
    
    /*
     * Refund Functionality
     */
	public function refund(Varien_Object $payment, $amount) {   
		
        $captureTxnId = $this->_getParentTransactionId($payment);
        $key = Mage::getStoreConfig('payment/payucheckout_shared/key');
		$mode = Mage::getStoreConfig('payment/payucheckout_shared/demo_mode');
        $refundamound = $amount;
       
        //Currency Conversion 
		$baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();//USD get store base currency code
		$currentCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();//get store current currency code
		$payumoneyCurrency='INR'; //Payumoney Curreny code
		
		//Convert order amount into rupee 
		if($currentCurrency!=$payumoneyCurrency) {
			
			$rate=Mage::app()->getStore()->getCurrentCurrencyRate();
			$value=$refundamound/$rate;
			$convert_amount = Mage::helper('directory')->currencyConvert($value,$baseCurrency,$payumoneyCurrency);
			$payum_amount=number_format($convert_amount, 2, '.', '');
			$payumoney_amount = round($payum_amount,0);
		} else {
			
			$convert_amount=$refundamound;
			$payum_amount=number_format($convert_amount, 2, '.', '');
			$payumoney_amount = round($payum_amount,0);
		}
			
		$refund_data =array(
			'merchantKey'=>$key,
			'paymentId'=>$captureTxnId,
			'refundAmount'=>$payumoney_amount
		); 
		
		$response=$this->setRefundApi($refund_data);
		
        if ($captureTxnId) {
			$order = $payment->getOrder();	
			$order->getIncrementId();
			
			$order_id = Mage::getModel('sales/order')->load($order->getIncrementId(), 'increment_id');
			
			
			if (!$order_id->getId()) {
				$this->_fault('order_not_exists');
			}
			if (!$order_id->canCreditmemo()) {
				$this->_fault('cannot_create_creditmemo');
			}
			$data = array(); 
			$service = Mage::getModel('sales/service_order', $order_id);
			$creditmemo = $service->prepareCreditmemo($data);

			// refund to Store Credit
			if ($refundToStoreCreditAmount) {
				// check if refund to Store Credit is available
				if ($order_id->getCustomerIsGuest()) {
					$this->_fault('cannot_refund_to_storecredit');
				}
				$refundToStoreCreditAmount = max( 0, min($creditmemo->getBaseCustomerBalanceReturnMax(), $refundToStoreCreditAmount));
				if ($refundToStoreCreditAmount) {
					$refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice($refundToStoreCreditAmount);
					$creditmemo->setBaseCustomerBalanceTotalRefunded($refundToStoreCreditAmount);
					$refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice(
						$refundToStoreCreditAmount*$order_id->getStoreToOrderRate()
					);
					// this field can be used by customer balance observer
					$creditmemo->setBsCustomerBalTotalRefunded($refundToStoreCreditAmount);
					// setting flag to make actual refund to customer balance after credit memo save
					$creditmemo->setCustomerBalanceRefundFlag(true);
				}
			}
			$ptxn = $captureTxnId;
			$refid = 123456789 ;
			//$refid = $response['RefundId']
			$payment = $order->getPayment();
			$payment->setTransactionId($refid)
					->setParentTransactionId($captureTxnId)
					->setShouldCloseParentTransaction(true)
					->setIsTransactionClosed(1);
			$creditmemo->setPaymentRefundDisallowed(true)->register();
			// add comment to creditmemo
			if (!empty($comment)) {
				$creditmemo->addComment($comment, $notifyCustomer);
			}

			return $this;
		} else {
			$this->_getSession()->addError($this->__('Cannot save the credit memo.'));
			return $this;
		}						 
		
    }
    
    /**
	 * Call PayUMoney Refund Api
	 */
	//~ public function setRefundApi($refund_data){
		//~ 
		//~ $mode = Mage::getStoreConfig('payment/payucheckout_shared/demo_mode');
        //~ $url = 'https://www.payumoney.com/payment/merchant/refundPayment?';
        //~ if ($mode == '') {
            //~ $url = 'https://www.payumoney.com/payment/merchant/refundPayment?';
        //~ }
		//~ $refundurl = $url;
		//~ $http = new Varien_Http_Adapter_Curl();
		//~ $config = array('timeout' => 30,'header'=>false); 
		//~ $http->setConfig($config);
		//~ $http->write(Zend_Http_Client::POST, $refundurl, '1.1', array(), $refund_data);
		//~ $server_output = $http->read();
		//~ $http->close();
		//~ return $server_output;
	//~ }
	//~ 
	//~ 
	protected function _debug($debugData) {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }
    		
}

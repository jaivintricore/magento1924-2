<?php
class Tricore_PayTm_Model_Cc extends Mage_Payment_Model_Method_Abstract {
		
	//Unique internal payment method identifier
	protected $_code = 'paytm_cc';
	protected $_isGateway               = false;
	protected $_canAuthorize            = true;
	protected $_canCapture              = true;
	protected $_canRefund				= true;
	protected $_canRefundInvoicePartial     = true;
	protected $_canVoid                 = false;
	protected $_canUseInternal          = false;
	protected $_canUseCheckout          = true;
	protected $_canUseForMultishipping  = false;
	protected $_paymentMethod			= 'cc';
	protected $_defaultLocale			= 'en';
	protected $_liveUrl	= NULL;
	protected $_formBlockType = 'paytm/form';
	protected $_infoBlockType = 'paytm/info';
	protected $_order;

	/**
     * Return order object
     */
	public function getOrder() {
		if (!$this->_order) {
			$this->_order = $this->getInfoInstance();
		}
		return $this->_order;
    }

	public function getPaymentMethodType() {
        return $this->_paymentMethod;
    }

	/**
     * Return checkout quote
     */
    public function getQuote() {
        return $this->getCheckout()->getQuote();
	}

	/**
     * Return checkout session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

	/**
     * Onepage Checkout Success method
     * Redirect to paytm website with data
     */
    public function getCheckoutRedirectUrl() {
         return Mage::getUrl('paytm/processing/redirectpaytm');
    }

	/**
     * Get Paytm Transaction Production/Live URL
     */
  	public function getUrl() {
			if(Mage::getStoreConfig('payment/paytm_cc/mode')==1)
			$this->_liveUrl = Mage::helper('paytm/Data')->PAYTM_PAYMENT_URL_PROD;
			else
			$this->_liveUrl = Mage::helper('paytm/Data')->PAYTM_PAYMENT_URL_TEST;
			return $this->_liveUrl;
    }

	/**
     * Get Paytm Refund  Production/Live URL
     */
	public function getrefundUrl() {
			if(Mage::getStoreConfig('payment/paytm_cc/mode')==1)
			$this->_refundUrl = Mage::helper('paytm/Data')->PAYTM_REFUND_URL_PROD;
			else
			$this->_refundUrl = Mage::helper('paytm/Data')->PAYTM_REFUND_URL_TEST;
			return $this->_refundUrl;
    } 

	/**
     * Prepare Parameter value for paytm API 
     */
    public function getParam() {
		
		$quote = Mage::getModel('checkout/session')->getQuote();
		$quoteData= $quote->getData();
		$grandTotal1=$quoteData['grand_total'];
		$emailid=$quote->getCustomerEmail();
		
		//Currency Conversion 
		$baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();//USD get store base currency code
		$currentCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();//get store current currency code
		$paytmCurrency='INR'; //Paytm Curreny code
		
		//Convert order amount into rupee 
		if($currentCurrency!=$paytmCurrency){
			$rate=Mage::app()->getStore()->getCurrentCurrencyRate();
			$value=$grandTotal1/$rate;
			$convert_amount = Mage::helper('directory')->currencyConvert($value,$baseCurrency,$paytmCurrency);
			$paytm_amount=number_format($convert_amount, 2, '.', '');
		}
		else
		{
			$convert_amount=$grandTotal1;
			$paytm_amount=number_format($convert_amount, 2, '.', '');
		}
		
		$reserved_orderid=Mage::getSingleton('checkout/session')->getQuote()->reserveOrderId()->reserved_order_id;
		$pay_send_ord_id = $reserved_orderid."_".time();
		Mage::getSingleton('checkout/session')->getQuote()->reserveOrderId()->save();
		
		$const = (string)Mage::getConfig()->getNode('global/crypt/key');
        $mer = Mage::helper('paytm')->decrypt_e($this->getConfigData('inst_key'),$const);
        $merid = Mage::helper('paytm')->decrypt_e($this->getConfigData('inst_id'),$const);
        $website = $this->getConfigData('website');
        $industry_type = $this->getConfigData('industrytype');
        $callbackUrl = rtrim(Mage::getUrl('paytm/processing/response',array('_nosid'=>true)),'/');
        
        $order = Mage::getSingleton('sales/order');
        $order->load($lastOrderId);
        $_totalData = $order->getData();
        $email = $_totalData['customer_email'];
		$custid=Mage::getSingleton('customer/session')->getCustomer()->getId();
		if(empty($custid)){
                $custid = $emailid;
        }
        

        $params =   array(
                    'MID' =>    $merid,                 
                    'TXN_AMOUNT' => $paytm_amount,
                    'CHANNEL_ID' => "WEB",
					'INDUSTRY_TYPE_ID' => $industry_type,
					'WEBSITE' => $website,
					'CUST_ID' => $custid,
					'ORDER_ID'  =>  $pay_send_ord_id,                      
					'EMAIL'=> $emailid,
					'MOBILE_NO' => preg_replace('#[^0-9]{0,13}#is','',$Phone),
					'CALLBACK_URL' => $callbackUrl
				);
			$checksum = Mage::helper('paytm')->getChecksumFromArray($params, $mer);//generate checksum
			$params['CHECKSUMHASH'] = $checksum;
		return $params;    
	}
    
	/**
     * Paytm Refund Process
     */
	public function refund(Varien_Object $payment, $amount) {
		
		$tablePrefix = (string) Mage::getConfig()->getTablePrefix(); 
		$captureTxnId = $this->_getParentTransactionId($payment);	
		
		$order = $payment->getOrder();
		$orderId = $order->getIncrementId();
		$refundAmound = $amount;
		
		$baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();//get store base currency code
		$currentCurrency = Mage::app()->getStore()->getCurrentCurrencyCode();//get store currency code
		$paytmCurrency='INR';
		
			if($currentCurrency!=$paytmCurrency){
				$rate=Mage::app()->getStore()->getCurrentCurrencyRate();		
				$value=$refundAmound/$rate;
				$convert_amount = Mage::helper('directory')->currencyConvert($value,$baseCurrency,$paytmCurrency);
				$paytm_amount=number_format($convert_amount, 2, '.', '');
			}
			else
			{
				$convert_amount=$refundAmound;
				$paytm_amount=number_format($convert_amount, 2, '.', '');
			}

		$txntype = 'REFUND';
		$const = (string)Mage::getConfig()->getNode('global/crypt/key');
		$mer = Mage::helper('paytm')->decrypt_e($this->getConfigData('inst_key'),$const);
		$merid = Mage::helper('paytm')->decrypt_e($this->getConfigData('inst_id'),$const); 
		
		$connection = Mage::getSingleton('core/resource')->getConnection('paytm');
		$result = $connection->fetchAll("SELECT paytm_ord_id FROM ".$tablePrefix."paytm WHERE cust_ord_id = '".$orderId."' ");
	
		$paytm_id=$result['0']['paytm_ord_id'];
		$chk=array(
			"MID" => $merid,
			"ORDERID" => $paytm_id,
			"TXNTYPE"=>$txntype,
			"REFUNDAMOUNT" => $paytm_amount,
			"TXNID" => $captureTxnId,
			);
		
		$checksum1 = Mage::helper('paytm')->getChecksumFromArray($chk, $mer);
		
		$refund_variables = Array(
			"TXNID" => $captureTxnId,
			"ORDERID" => $paytm_id,
			"MID" => $merid,
			"TXNTYPE"=>$txntype,
			"REFUNDAMOUNT"=>$paytm_amount,
			"CHECKSUM"=>$checksum1
		);
		
		$jason_array='JsonData='.json_encode($refund_variables);
		$response=$this->refundApi($jason_array);
	
		$resp_code = $response['RESPCODE'];
		if ( $resp_code == 10 ) {
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
			$payment = $order->getPayment();
			$payment->setTransactionId($response['REFUNDID'])
					->setParentTransactionId($ptxn)
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
	 * Call PayTm Refund Api
	 */
	public function refundApi($jason_array) {
		
		$refundurl = $this->getrefundUrl();
		$ref = curl_init();	
				curl_setopt($ref, CURLOPT_URL, $refundurl);
				curl_setopt($ref, CURLOPT_HEADER, false);
				curl_setopt($ref, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ref, CURLOPT_POST, true);
				curl_setopt($ref, CURLOPT_RETURNTRANSFER, true);                                                                     
				curl_setopt($ref, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
				curl_setopt($ref, CURLOPT_POSTFIELDS, $jason_array);                                                                       
				curl_setopt($ref, CURLOPT_HTTPHEADER, array(                                                                          
							'Content-Type: application/json; charset=utf-8', 
							'Authorization: Bearer '.$access_token,
							'Content-Length:'.strlen($jason_array))      
					);                                                                                                      
				$refundresult = curl_exec($ref);
				curl_close($ref);
				$refundarray = json_decode($refundresult,true);
				return $refundarray;
	}
    
	/**
     * Get Order Transaction id 
     */
	protected function _getParentTransactionId(Varien_Object $payment) {
        return $payment->getParentTransactionId() ? $payment->getParentTransactionId() : $payment->getLastTransId();
    }
	
	protected function _debug($debugData) {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }
}

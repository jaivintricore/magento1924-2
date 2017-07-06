<?php
/**
 * PayTm Processing controller
 */
class Tricore_PayTm_ProcessingController extends Mage_Core_Controller_Front_Action {

    protected $_successBlockType = 'paytm/success';
    protected $_order = NULL;
    protected $_paymentInst = NULL;
    public $isvalid;
	public $transactionID = NULL;
	public $captureAmount = NULL;
	protected $_checkoutmodel='paytm/cc_checkout';
	
	protected function _construct() {
        parent::_construct();
    }

	/**
	 * Get singleton of Checkout Session Model 
	 */
    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

	/**
	 * Return quote object
	 */
    private function _getQuote() {
        if (!$this->_quote) {
            $this->_quote = $this->_getCheckout()->getQuote();
        }
        return $this->_quote;
    }
    
    /**
	 * Return Checkout model object
	 */
    protected function _initCheckout() {
        $quote = $this->_getQuote();
        $this->_checkout = Mage::getSingleton($this->_checkoutmodel, array('quote'  => $quote,));
        return $this->_checkout;
    }

	/**
	 * Connect to Paytm Api
	 */
    public function redirectpaytmAction() {
		$this->loadLayout();
		$this->renderLayout();
		return;
    }

	/**
	 * Return from paytm with paytm post variable
	 */
    public function responseAction() {
		$quoteId = $this->_getCheckout()->getQuote()->getId();
		$param=array();
		//save paytm response post variable
		$paytmresponse=$this->getRequest()->getPost();

		try {
			foreach($paytmresponse as $key=>$value) {
					$param[$key] = $paytmresponse[$key];
				}
			$isValidChecksum = false;
			$txnstatus = false;
			$txnretry = false;
			$mer_encrypted = Mage::getStoreConfig('payment/paytm_cc/inst_key');
			$const = (string)Mage::getConfig()->getNode('global/crypt/key');
			$mer_decrypted= Mage::helper('paytm')->decrypt_e($mer_encrypted,$const);
		
			if(isset($paytmresponse['CHECKSUMHASH'])) {
				$return = Mage::helper('paytm')->verifychecksum_e($param, $mer_decrypted, $paytmresponse['CHECKSUMHASH']);
				if($return == "TRUE")
				$isValidChecksum = true;
			}

			if($paytmresponse['STATUS'] == "TXN_SUCCESS") {
				$txnstatus = true;
			}

			if($paytmresponse['RESPCODE'] == "269") {
				$txnretry = true;
			}
			
			if($txnstatus && $isValidChecksum) {
				$this->_forward('PlaceOrder');
				return; 
			}else{
				if($txnretry){
					$session = $this->_getCheckout();
					$session->addError(Mage::helper('paytm')->__('Try Again'));
					$this->_redirect('checkout/cart');    
				}else{
					$session = $this->_getCheckout();
					$session->addError(Mage::helper('paytm')->__('The order has failed.'));
					$this->_redirect('checkout/cart');    
				}
			}
		}
		catch (Mage_Core_Exception $e) {
			echo $e->getMessage(); 
		}
    }

	/**
     *  Checking PAYTM POST variables.
     */
    protected function _checkReturnedPost() {
		if (!$this->getRequest()->isPost())
			Mage::throwException('Wrong request type.');
		
		$request = $this->getRequest()->getPost();
		
		if (empty($request))
			Mage::throwException('Request doesn\'t contain POST elements.');

		if (empty($request['ORDERID']))
			Mage::throwException('Missing or invalid order ID');
		$this->_order = Mage::getModel('sales/order')->loadByIncrementId($request['ORDERID']);
	
		if (!$this->_order->getId())
			Mage::throwException('Order not found');
		return $request;
    }


	/**
	 * placeOrder Function save the order to magento after successful payment
     */
	public function placeOrderAction() {
        try {
        		$request =$this->getRequest()->getPost();
        		$res_ord_id = $request['ORDERID'];
        		$reserved_orderid=Mage::getSingleton('checkout/session')->getQuote()->reserveOrderId()->reserved_order_id;
        		$data = array ('cust_ord_id' => $reserved_orderid, 
						'pay_ord_id' => $res_ord_id
						);

				$model = Mage::getModel('paytm/paytm'); //for eg. Mage::getModel('catalog/product'); 
				$model->setCustOrdId($reserved_orderid)
					  ->setPaytmOrdId($res_ord_id)
					  ->save();
				$this->_initCheckout();
                $this->_checkout->place($request);
                $session = $this->_getCheckout();
                $session->clearHelperData();
                
                $quoteId = $this->_getQuote()->getId();
                $session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);
                
                // an order may be created
                $order = $this->_checkout->getOrder();
                
                if ($order) {
                    $session->setLastOrderId($order->getId())->setLastRealOrderId($order->getIncrementId());
                }

                $order_mail = new Mage_Sales_Model_Order();
                $incrementId =$session->getLastRealOrderId();
                $order_mail->loadByIncrementId($incrementId);
                $order_mail->sendNewOrderEmail();
                $orderid=$order->getIncrementId();

                $this->_redirect('checkout/onepage/success');
                return;
            } 
   		catch (Mage_Core_Exception $e) {
			echo $e->getMessage(); 
		}  
    }
}

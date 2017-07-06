<?php 
class Tricore_PayuCheckout_SharedController extends Mage_Core_Controller_Front_Action
{
   
    protected $_redirectBlockType = 'payucheckout/shared_redirect';
    protected $_paymentInst = NULL;
    protected $_checkout = null;
    protected $_order = NULL;
    protected $_config = null;
    
    protected $_checkoutType ='payucheckout/shared_checkout';
	
	protected function _construct() {
        parent::_construct();
    }
    protected function _getCheckout(){
        return Mage::getSingleton('checkout/session');
    }

	public function redirectpayuAction(){
		$this->_initCheckout();
		
		$this->loadLayout();
		$this->renderLayout();
		return;
    }
    
    
    public function successAction(){
        $response = $this->getRequest()->getPost();
		$this->getResponseOperation($response);
    }
	
	
	public function failureAction(){
       
	   $arrParams = $this->getRequest()->getPost();
	   $this->getResponseOperation($arrParams);
       $this->_getCheckout()->clear();
	   $this->_redirect('checkout/onepage/failure');
    }

    public function canceledAction(){
	    $arrParams = $this->getRequest()->getParams();
		$this->getResponseOperation($arrParams);
		$this->_getCheckout()->clear();
		$this->loadLayout();
        $this->renderLayout();
    }
    
    private function _getQuote(){
		if (!$this->_quote){
			$this->_quote = $this->_getCheckoutSession()->getQuote();
		}
		return $this->_quote;
	}
	

       
	protected function _getCheckoutSession(){
		return Mage::getSingleton('checkout/session');
	}

        
	protected function _initCheckout(){
		$quote = $this->_getQuote();
		if (!$quote->hasItems() || $quote->getHasError()) 
			{
				$this->getResponse()->setHeader('HTTP/1.1','403 Forbidden');
				Mage::throwException(Mage::helper('PayTm')->__('Unable to initialize  Checkout.'));
			}

			$this->_checkout = Mage::getSingleton($this->_checkoutType, array(
											'quote'  => $quote));	
											
			//print_r($this->_checkout);exit;																
			return $this->_checkout;
	}
	
	public function getResponseOperation($response){
		
        $debug_mode = Mage::getStoreConfig('payment/payucheckout_shared/debug_mode');
        $key = Mage::getStoreConfig('payment/payucheckout_shared/key');
        $salt = Mage::getStoreConfig('payment/payucheckout_shared/salt');
        
        if (isset($response['status'])) {
            $txnid = $response['txnid'];
            $orderid = $response['udf2'];
            if ($response['status'] == 'success') {

				$status = $response['status'];
                $amount = $response['amount'];
                $productinfo = $response['productinfo'];
                $firstname = $response['firstname'];
                $email = $response['email'];
                $keyString = '';
                $Udf1 = $response['udf1'];
                $Udf2 = $response['udf2'];
                $Udf3 = $response['udf3'];
                $Udf4 = $response['udf4'];
                $Udf5 = $response['udf5'];
                $Udf6 = $response['udf6'];
                $Udf7 = $response['udf7'];
                $Udf8 = $response['udf8'];
                $Udf9 = $response['udf9'];
                $Udf10 = $response['udf10'];
                if ($debug_mode == 1) {
                    $keyString = $key . '|' . $txnid . '|' . $amount . '|' . $productinfo . '|' . $firstname . '|' . $email . '|' . $Udf1 . '|' . $Udf2 . '|' . $Udf3 . '|' . $Udf4 . '|' . $Udf5 . '|' . $Udf6 . '|' . $Udf7 . '|' . $Udf8 . '|' . $Udf9 . '|' . $Udf10;
                } else {
                    $keyString = $key . '|' . $txnid . '|' . $amount . '|' . $productinfo . '|' . $firstname . '|' . $email . '|' . $Udf1 . '|' . $Udf2 . '|' . $Udf3 . '|' . $Udf4 . '|' . $Udf5 . '|' . $Udf6 . '|' . $Udf7 . '|' . $Udf8 . '|' . $Udf9 . '|' . $Udf10;
                }

                $keyArray = explode("|", $keyString);
                $reverseKeyArray = array_reverse($keyArray);
                $reverseKeyString = implode("|", $reverseKeyArray);
                $saltString = $salt . '|' . $status . '|' . $reverseKeyString;
                $sentHashString = strtolower(hash('sha512', $saltString));
                $responseHashString = $_REQUEST['hash'];
                
                
                if ($sentHashString == $responseHashString) {
					
					$this->_initCheckout();
					
					$myModel = Mage::getSingleton('payucheckout/checkout');
					
					$this->_checkout->place($response);

					$session = $this->_getCheckoutSession();
					$session->clearHelperData();

					// "last successful quote"
					$quoteId = $this->_getQuote()->getId();
					$session->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

					//an order may be created
					$order = $this->_checkout->getOrder();
					
					if ($order) {
					$session->setLastOrderId($order->getId())
						->setLastRealOrderId($order->getIncrementId());
					}
					
					$this->_redirect('checkout/onepage/success');
					
                    
                } else {

                    //$order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
                    $this->_redirect('checkout/onepage/failure');
                }
                
            }
            if ($response['status'] == 'failure') {

				//Enable/Disable Fail Transaction log	
				if(Mage::getStoreConfig('payment/payucheckout_shared/debuglog')){
								$Apicall=Mage::getModel('payucheckout/shared')->getFormFields();
								$FailTransaction=array("[ApiCall]"=>$Apicall,"[RESPONSE]"=>$response);
								Mage::log($FailTransaction,null,'Fail_Transaction.log');
				}
				
               $this->_redirect('checkout/onepage/failure');

			} else if ($response['status'] == 'pending') {
                $this->_redirect('checkout/onepage/failure');
            }
            
        } else {
            $this->_redirect('checkout/onepage/failure');
        }       
	}
}
    
    

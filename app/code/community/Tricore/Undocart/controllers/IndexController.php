<?php
class Tricore_Undocart_IndexController extends Mage_Core_Controller_Front_Action
{
	
	/**
	 * Getting Configuration Message from XML
	 */
    const XML_PATH_ERROORMSG = 'undocart_options/section_two/error_msg';
    
	
	/**
     * Action list where need check enabled cookie
     *
     * @var array
     */
	protected $_cookieCheckActions = array('add');

    /**
     * Retrieve shopping cart model object
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }

    /**
     * Get checkout session model instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }
    
    /**
     * Get undo session data from model observer event trigger method 
     * 
     * Add product back to shopping cart action
     *
     * @throws Exception
     */    
	public function addAction()
	{
		$action = array();
		
		//creating undocart Model object 
		$undocart = Mage::getModel('tricore/undocart');
		
		//Checking undocart session variable @Cartdata
		if(Mage::getSingleton('core/session')->getCartdata()){
			$cart = $this->_getCart();
				
			try {	
				/**
				* Intializing Undo cart data 
				*/					
				$cartdata = Mage::getSingleton('core/session')->getCartdata();
				
				foreach( $cartdata as $data ) {
					$type = $data['type'];

					$params = $data['info_buyRequest'];
					
					//Intailizing product
					$product = Mage::getModel('catalog/product')
					->setStoreId(Mage::app()->getStore()->getId())
					->load($params['product']);

					$cart->addProduct($product,$params);
				}

				$cart->save();
				$this->_getSession()->setCartWasUpdated(true);	
				
				$action[] = $cartdata[0]['action'];
				$response_msg = $undocart->getSucessMsg($action);
				$message = str_replace("{product_name}",$cartdata[0]['name'], $response_msg);
				
				//response Message	
				$this->_getSession()->addSuccess($message);
					 
				//unset undo sessions variable @Cartdata 
				Mage::getSingleton('core/session')->unsCartdata();
				
				//redirect back to cart
				$this->_redirect('checkout/cart');

			} catch (Exception $e) {
				//Admin configuation error message
				$errormsg_config = Mage::getStoreConfig(self::XML_PATH_ERROORMSG);
				
				//default error message
				$message='Cannot added back item to shopping cart';				
				if($errormsg_config) {
					$message = $errormsg_config;	
				}

				$this->_getSession()->addException($e, $this->__($message));
				Mage::logException($e);
				Mage::getSingleton('core/session')->unsCartdata();
                //redirect to cart 
				$this->_redirect('checkout/cart');
			} 

		} else {
			//redirect back to cart
			$this->_redirect('checkout/cart');   
		} 
	}
}     
?>

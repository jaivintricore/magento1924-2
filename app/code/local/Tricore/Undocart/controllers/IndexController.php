<?php
class Tricore_Undocart_IndexController extends Mage_Core_Controller_Front_Action
{
	
	/**
	 * Getting Configuration Message from XML
	 */
    const XML_PATH_ADDMSG = 'undocart_options/section_two/addcart_msg';
    const XML_PATH_ADDCARTMSG = 'undocart_options/section_two/add_emptycart_msg';
	
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
        //Checking undocart session variable @Cartdata
		if(Mage::getSingleton('core/session')->getCartdata()){
			$cart   = $this->_getCart();

			try {
				
				/**
			     * Intializing Undo cart data 
			     */					
				$cartdata=Mage::getSingleton('core/session')->getCartdata();
								 
					foreach( $cartdata as $data ) {
						$type=$data['type'];
						$name=$data['name'];
						$params['product']=$data['id']; 
                            //checking product type and setting corresponding attribute values
							if($type=='downloadable'){
								$params['links']=$data['links'];

							}elseif($type=='bundle'){
								$params['bundle_option']=$data['bundle_option'];
								$params['bundle_option_qty']=$data['bundle_option_qty'];

							}elseif($type=='configurable'){
								$params['super_attribute']=$data['super_attribute'];

							}elseif($type=='grouped'){
								$params['super_group']=$data['super_group'];
							}
                 
								if (isset($data['qty'])) {
									$filter = new Zend_Filter_LocalizedToNormalized(
									array('locale' => Mage::app()->getLocale()->getLocaleCode())
									);
									$params['qty'] = $filter->filter($data['qty']);
								}									
                            //Getting product custom_option
							$params['options']=$data['options'];
							//Intailizing product
							$product = Mage::getModel('catalog/product')
								->setStoreId(Mage::app()->getStore()->getId())
								->load($data['id']);

							$cart->addProduct($product, $params);
					}
										
					$cart->save();
					$this->_getSession()->setCartWasUpdated(true);
						//Checking observer method by name parameter passing from observer
						if(isset($cartdata[0]['name'])) {
							//for single product remove
							//Admin configuation messages for single product
							$addmsg_config=Mage::getStoreConfig(self::XML_PATH_ADDMSG);
							$addmsg='{product_name} Added Sucessfully';
							if($addmsg_config){
								$addmsg=$addmsg_config;	
						    }	
							$message = str_replace("{product_name}",$name, $addmsg);         	
									
						}else{
							//empty all cart item
							//Admin configuation messages for empty cart
							$add_cartmsg_config=Mage::getStoreConfig(self::XML_PATH_ADDCARTMSG);
							$message='Cart item(s) Added Sucessfully';
							if($add_cartmsg_config){
								$message=$add_cartmsg_config;
							}			
						}	
						
					$this->_getSession()->addSuccess($this->__($message));     
					//unset undo sessions variable @@Cartdata 
					Mage::getSingleton('core/session')->unsCartdata();
                    //redirect back to cart
					$this->_redirect('checkout/cart');

			} catch (Exception $e) {
				   $errormsg=Mage::getStoreConfig('undocart_options/section_two/error_msg');				
					if($errormsg!='') {
					   $message=$errormsg;	
					}else{
					   $message='Cannot added back item to shopping cart';
					}
								  	
				$this->_getSession()->addException($e, $this->__($message));
				Mage::logException($e);
				Mage::getSingleton('core/session')->unsCartdata();
                
				$this->_redirect('checkout/cart');
			} 
			
		} else {
			//redirect back to cart
			$this->_redirect('checkout/cart');   
		} 
	}
}     
?>

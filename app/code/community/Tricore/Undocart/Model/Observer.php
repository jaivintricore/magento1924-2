<?php
class Tricore_Undocart_Model_Observer 
{ 
	/**
     * method for empty cart event Trigger
     * 
     * Retrieve shopping cart model object
     * 
     * getting current cart items
     * 
     * Creating array of cart items
     * 
     * Stroing it own session and get it controller Action 
     */
      public function salesOrderAfterSave(Varien_Event_Observer $observer)
    {
		$order = $observer->getOrder();
		//print_r($order->getData());exit;
		$oldstatus=$order->getOrigData('status');
		//echo "oldstatus".$oldstatus;
		$Newstatus=$order->getData('status');
		//echo "Newstatus".$Newstatus;
		if($oldstatus != $Newstatus){
			$forterStatusData = [];
			$forterStatusData["orderId"] 				= $order->getData('increment_id');
			$forterStatusData['eventTime'] 	= intval(microtime(true) * 1000);
			$forterStatusData['updatedStatus'] 	= $order->getData('state');
			$forterStatusData['updatedMerchantStatus'] 	= $order->getData('status');
			//print_r($forterStatusData);
			//exit;
		}
		//echo "Out";
		//exit;
    }
     
	public function removecartitem($observer)
	{  
		//creating undocart Model object 
		$undocart = Mage::getModel('tricore/undocart');
		
		//checking module status from admin configuaration
	    $status = $undocart->getModuleStatus();
	    
		if($status)
		{
			//frontend update_cart_action @params
			$post = Mage::app()->getRequest()->getPost('update_cart_action');
			//Only for empty_cart action 
			if ($post == 'empty_cart') {
				$undocart->setUndoCartData();
				
				$remove_config = $undocart->getConfigMsg($action);
				$msg = $remove_config['message'].'<a href="'.$undocart->getBaseUrl().'undocart/index/add/"> '.$remove_config['undotext'].'</a></div>';
				//return undo cart response link 
				Mage::getSingleton('core/session')->getMessages(true);
				Mage::getSingleton('core/session')->addSuccess(Mage::helper('customer')->__($msg));
			}	    
		}
		
	}
	
	/**
     * method for removing single item from cart on event Trigger
     * 
     * Retrieve shopping cart model object
     * 
     * getting current remove item
     * 
     * Stroing it own session and get it controller Action
     */ 
	public function removeitem($observer)
	{
		$params = array();
		$productOptions = array();
		$action = array();
		
		//creating undocart Model object 
		$undocart = Mage::getModel('tricore/undocart');

		//checking module status from admin configuration setting
		$status = $undocart->getModuleStatus();
		
		if($status)
		{	
			$post = Mage::app()->getRequest()->getPost('update_cart_action');

			//For Multiple items
			$cartData = Mage::app()->getRequest()->getParam('cart');
			
			if(isset($cartData)){	
				$params = $undocart->getRequestQty($cartData);
			}
			
			if(count($params)>1) {			 
				$action[] = 'multiple'; 
				
			}else{//for Single item		
						
				$action[] = 'single'; 	
				//Getting observer object  
				$item = $observer->getQuoteItem();
			    $name = $item->getName();
				$params[] = $item->getItemId();
				
			}
			$undocart->setUndoCartData($params,$action);
			
			$remove_config = $undocart->getConfigMsg($action);
				
			//replacing {product_name} with orginal product name
			$remove_msg = str_replace("{product_name}",$name, $remove_config['message']);
			$msg = $remove_msg.'<a href="'.$undocart->getBaseUrl().'undocart/index/add/"> '.$remove_config['undotext'].'</a></div>'; 
						
			//return undo cart response link 
			Mage::getSingleton('core/session')->getMessages(true);			
			Mage::getSingleton('core/session')->addSuccess(Mage::helper('customer')->__($msg));
		} 		
	}				
		
			    
}  

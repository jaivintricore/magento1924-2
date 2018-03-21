<?php
class Tricore_Undocart_Model_Undocart
{
	const XML_PATH_UNDOCART_STATUS = 'undocart_options/section_one/enabledisable';
	
	const XML_PATH_REMOVEMSG = 'undocart_options/section_two/remove_msg';
    const XML_PATH_UNDOTEXTMSG =  'undocart_options/section_two/undotext_msg';
    const XML_PATH_ADDMSG = 'undocart_options/section_two/addcart_msg';
    
    const XML_PATH_MULTI_REMOVEMSG = 'undocart_options/section_two/multiremove_msg';
    const XML_PATH_MULTI_UNDOTEXTMSG = 'undocart_options/section_two/multi_undotext_msg';
    const XML_PATH_MULTI_ADDMSG = 'undocart_options/section_two/addmulti_msg';
    
    const XML_PATH_EMPTYCARTMSG = 'undocart_options/section_two/emptycart_msg';
    const XML_PATH_CART_UNDOTEXTMSG = 'undocart_options/section_two/cart_undotext_msg';
    const XML_PATH_CART_ADDMSG = 'undocart_options/section_two/add_emptycart_msg'; 

    /**
     * Method for Observer Undocart
     * 
     * return array of undo_items 
     */
	
	public function setUndoCartData($params = array(),$action = array())
	{
						
		if(Mage::getSingleton('core/session')->getCartdata()) {	 
			Mage::getSingleton('core/session')->unsCartdata(); 	  
		}
		
		$cart_items = array();
		
		//getting current cart item from shopping cart model object
		$cartItems = Mage::getModel("checkout/cart")->getItems();
		
		foreach($cartItems as $item) {					
			$productOptions = array();
			$type = $item->getProductType();
			$item_id = $item->getItemId();	
			$product = $item->getProduct();
			$id = $item->getProductId();
			$qty = $item->getQty();
						
			//getting product option
			$productOptions = $product->getTypeInstance(true)->getOrderOptions($product);
		
			$productOptions['type'] = $type;
			$productOptions['item_id'] = $item_id;
			$productOptions['name'] = $item->getName();
			$productOptions['info_buyRequest']['qty'] = $qty;					
			 
			if ($type=='bundle') {
				$bundled_product = new Mage_Catalog_Model_Product();		
				$bundled_product->load($productOptions['info_buyRequest']['product']);

				$selectionCollection = $bundled_product->getTypeInstance(true)->getSelectionsCollection(
				$bundled_product->getTypeInstance(true)->getOptionsIds($bundled_product), $bundled_product);

				//Getting all bundle item for product 
				$bundled_items = array();
				
				foreach($selectionCollection as $option) {
					$bundled_items[] = $option->product_id;
				}
				
				$productOptions['info_buyRequest']['qty'] = $qty;

			}elseif ($type=='configurable'){
				$config_arr = array();

				$config_id = $productOptions['info_buyRequest']['product'];						
				if (!in_array($config_id, $config_arr)) {
					$productOptions['info_buyRequest']['qty'] = $qty;
					$config_arr[] = $config_id;
				}
			}elseif ($type=='grouped'){ 
				$group_array = array(); 
				$group = array();

				//re-intializing $productOptions['info_buyRequest'] for group product required attribute
				$group_id = $productOptions['info_buyRequest']['super_product_config']['product_id'];
				$group['product'] = $group_id;
				$group_array[$id] = $qty;
				$group['super_group'] = $group_array;				
				$productOptions['info_buyRequest'] = $group;
				$productOptions['info_buyRequest']['qty'] = $qty;
			} else {
				//checking if product belong to either config or bundle products
				if(in_array($id,$config_arr) || in_array($productOptions['info_buyRequest']['product'],$config_arr)
				|| in_array($id,$bundled_items) || in_array($productOptions['info_buyRequest']['product'],$bundled_items) ) {
					//return empty array									
					$productOptions = array();
				}else{
					$productOptions['info_buyRequest']['qty'] = $qty;
				}								
			}
				
			//checking for multiple or single item
			if(count($params)>0 && !in_array($productOptions['item_id'],$params) ) {					
				$productOptions = array();	
			}
					   
			//creating array of all shopping cart products
			if(count($productOptions)>0) {
				
	            //Checking for action	
				if(count($action)>0){					
					$productOptions['action'] = $action[0];	
				}						
				array_push($cart_items,$productOptions);    
			}
		
		}
		
		Mage::getSingleton('core/session')->setCartdata($cart_items);
		return $cart_items;
	}
	
	/**
	 * Checking Module Status from Admin Configuration
	 * 
	 * return true or false 
	 */ 
	public function getModuleStatus()
	{
	   $result = Mage::getStoreConfig(self::XML_PATH_UNDOCART_STATUS);
	   return $result;	
	}
	
	/**
	 * For Multiple items remove request
	 * 
	 * return array of item which have Qty =0 
	 */ 
	public function getRequestQty($cartData)
	{
		$result = array();
		
		foreach ($cartData as $key => $value ) {   
		//checking for only zero quantity
			if ($value['qty']=='0') {				
				$result[] = $key;	
			}
		}
		return $result;
	}
	
	/**
	 * Getting Magento Base url
	 * 
	 * return baseurl 
	 */ 
	public function getBaseUrl()
	{
		
	  $result = Mage::getUrl('',array('_secure' => Mage::App()->getStore()->isCurrentlySecure()));
	  return $result; 	
	}
	
	/**
	 * Getting undo Admin configuration settings for remove,undo text options
	 * 
	 * return array of configuration based on observer action
	 * 
	 * Default for Empty cart action  
	 */ 
	
	public function getConfigMsg($action = array())
	{
		$result = array();
		
		if($action[0]=='single'){
			$message = Mage::getStoreConfig(self::XML_PATH_REMOVEMSG);
			$undotext_config = Mage::getStoreConfig(self::XML_PATH_UNDOTEXTMSG); 
			
			$result['undotext'] = "Undo?";
			
			
		}elseif($action[0]=='multiple'){
			$message = Mage::getStoreConfig(self::XML_PATH_MULTI_REMOVEMSG);
			$undotext_config = Mage::getStoreConfig(self::XML_PATH_MULTI_UNDOTEXTMSG); 
			
			$result['undotext'] = "Undo All?";
			 	
			  		
		}else{
			
			$message = Mage::getStoreConfig(self::XML_PATH_EMPTYCARTMSG);
			$undotext_config = Mage::getStoreConfig(self::XML_PATH_CART_UNDOTEXTMSG);  
			
			$result['undotext'] = "Undo All?";	   	
		}
		
		if($undotext_config){
			$result['undotext'] = $undotext_config;  	
		}
		
		$result['message'] = $message;
		
	    return $result;
	}
	
	/**
	 * Getting undo Admin configuration settings for
	 * Controller add Action
	 * 
	 * return sucess Message
	 */ 
	public function getSucessMsg($action = array())
	{
		
	    if($action[0]=='single'){
			$message = Mage::getStoreConfig(self::XML_PATH_ADDMSG);	
			 
		}elseif($action[0]=='multiple'){
			$message = Mage::getStoreConfig(self::XML_PATH_MULTI_ADDMSG);		
			
		}else{
			$message = Mage::getStoreConfig(self::XML_PATH_CART_ADDMSG);	 	
		}
		
	    return $message;
		
	}
  
}
?>

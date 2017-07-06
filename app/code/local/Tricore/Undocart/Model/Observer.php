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
     const XML_PATH_UNDOCART_STATUS = 'undocart_options/section_one/enabledisable';
     const XML_PATH_EMPTYCARTMSG = 'undocart_options/section_two/emptycart_msg';
     const XML_PATH_UNDOTEXTMSG =  'undocart_options/section_two/undotext_msg';
     const XML_PATH_REMOVEMSG = 'undocart_options/section_two/undocart_options/section_two/remove_msg';
     
	
	public function getcartitem($observer)
	{   
		//checking module status from admin configuaration
	    $status= Mage::getStoreConfig(self::XML_PATH_UNDOCART_STATUS);
		if($status)
		{
			//frontend update_cart_action @params
			$post = Mage::app()->getRequest()->getPost('update_cart_action');
			//Only for empty_cart action 
			if ($post == 'empty_cart') {
				//removing old session if exits
				if(Mage::getSingleton('core/session')->getCartdata()) {	 
				   Mage::getSingleton('core/session')->unsCartdata();   
				}

				$cart_items=array();
				//getting current cart item from shopping cart model object
				$cartItems = Mage::getModel("checkout/cart")->getItems();
				foreach($cartItems as $item) {
					$undo_arr=array();

					$type=$item->getProductType();
					$options=$item->getProductOptions();			
					$product  = $item->getProduct();
                    //getting product option
					$productOptions = $product->getTypeInstance(true)->getOrderOptions($product);
					
					$id = $item->getProductId();
					$undo_arr['id']  = $id;   
					$undo_arr['type']=$type;
					$undo_arr['qty']=$item->getQty();
                     
					if($type=='downloadable') {   
						$undo_arr['links']=$productOptions['info_buyRequest']['links'];
						$undo_arr['options']=$productOptions['info_buyRequest']['options']; 

					} elseif ($type=='bundle') {					  
						$bundled_product = new Mage_Catalog_Model_Product();		
						$bundled_product->load($undo_arr['id']);

						$selectionCollection = $bundled_product->getTypeInstance(true)->getSelectionsCollection(
						$bundled_product->getTypeInstance(true)->getOptionsIds($bundled_product), $bundled_product);
                        //Getting all bundle item for product 
						$bundled_items = array();

							foreach($selectionCollection as $option) {
								$bundled_items[] = $option->product_id;
							}
						$undo_arr['bundle_option']=$productOptions['info_buyRequest']['bundle_option'];  
						$undo_arr['bundle_option_qty']=$productOptions['info_buyRequest']['bundle_option_qty']; 
						$undo_arr['options']=$productOptions['info_buyRequest']['options'];

					} elseif ($type=='configurable') {
						$_sku=$item->getSku();
						$_catalog = Mage::getModel('catalog/product');
						//getting configure product id by sku
						$config_id = $_catalog->getIdBySku($_sku);  
						$undo_arr['super_attribute']=$productOptions['info_buyRequest']['super_attribute'];
						$undo_arr['options']=$productOptions['info_buyRequest']['options'];

					} elseif ($type=='grouped') { 				   
						$group_array=array(); 
						 
					    $group_product_id =$productOptions['info_buyRequest']['super_product_config']['product_id'];
						$undo_arr['id']=$group_product_id; 
						$group_array[$id]=$undo_arr['qty'];
						$undo_arr['super_group']=$group_array;			   
						$undo_arr['options']=$productOptions['info_buyRequest']['options']; 

					} else {
						//skip if product belong to bundle or configurable   
						if($item->getProductId()!=$config_id && !in_array($item->getProductId(),$bundled_items)) {						   					   
							$undo_arr['options']=$productOptions['info_buyRequest']['options'];	
						}else{							
							$undo_arr=array();	
						}								   
					}
                    //creating array of all shopping cart products
					if(count($undo_arr)>0) {
						array_push($cart_items,$undo_arr);    
					}          
				}
                //storing it own session variable
				Mage::getSingleton('core/session')->setCartdata($cart_items); 
         
				$base_url = Mage::getUrl('',array('_secure' => Mage::App()->getStore()->isCurrentlySecure()));
				//Getting admin configuration setting message
				$emptycart_msg_config= Mage::getStoreConfig(self::XML_PATH_EMPTYCARTMSG);
				$undotext_config=Mage::getStoreConfig(self::XML_PATH_UNDOTEXTMSG);
				
				$undotext = "Undo?";
                if($undotext_config){
					$undotext = $undotext_config;
				}
				
				$emptycart_msg = "All Item(s) Removed";
				if($emptycart_msg_config){
					$emptycart_msg = $emptycart_msg_config;
				}
				
				$msg = $emptycart_msg.'<a href="'.$base_url.'undocart/index/add/"> '.$undotext.'</a></div>';
				
				//return undo cart response link 
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
		$cart_items=array();
		$undo_arr=array();
		//checking module status from admin configuration setting
		$status= Mage::getStoreConfig(self::XML_PATH_UNDOCART_STATUS);
		if($status)
		{
			//remove prevoius session if already exits
			if(Mage::getSingleton('core/session')->getCartdata()) {
				Mage::getSingleton('core/session')->unsCartdata();  	 
			}
			//Getting observer object  
			$item = $observer->getQuoteItem();
			$id=$item->getProductId();	
			$product  = $item->getProduct();		
			/**
			 * Intializing @undo array for product back to cart
			 */
			$undo_arr['name']  = $item->getName();
			$undo_arr['qty']= (int)$item->getQty();
			$undo_arr['id']=$id;		
			//getting product option	     
			$productOptions = $product->getTypeInstance(true)->getOrderOptions($product);
			$undo_arr['options']=$productOptions['info_buyRequest']['options']; 
			//getting product type and required attribute
			$product_type=$item->getProductType();
			$undo_arr['type']=$product_type;
				         
			if($product_type=='downloadable') { 			   
				$undo_arr['links']=$productOptions['info_buyRequest']['links'];

			}elseif($product_type=='bundle') { 
				$undo_arr['bundle_option']=$productOptions['info_buyRequest']['bundle_option'];  
				$undo_arr['bundle_option_qty']=$productOptions['info_buyRequest']['bundle_option_qty'];              

			}elseif($product_type=='configurable') {
				$undo_arr['super_attribute']=$productOptions['info_buyRequest']['super_attribute']; 
				
			}elseif($product_type=='grouped') { 
				$group_array=array(); 
			 
				$group_product_id =$productOptions['info_buyRequest']['super_product_config']['product_id'];
				$undo_arr['id']=$group_product_id; 
				$group_array[$id]=$undo_arr['qty'];
				$undo_arr['super_group']=$group_array;			   
				$undo_arr['options']=$productOptions['info_buyRequest']['options'];  
			}
			
			if(count($undo_arr)>0){
				array_push($cart_items,$undo_arr); 	 						 
			}			
			//intialize undo cart sessions
			Mage::getSingleton('core/session')->setCartdata($cart_items);
			$base_url = Mage::getUrl('',array('_secure' => Mage::App()->getStore()->isCurrentlySecure()));
			//Getting admin configuration setting message
			$removecart_msg_config=Mage::getStoreConfig(self::XML_PATH_REMOVEMSG);
			$undotext_config=Mage::getStoreConfig(self::XML_PATH_UNDOTEXTMSG);
			//checking undo text exits in configuration otherwise default
			$undotext = "Undo?";
			if($undotext_msg){
			    $undotext=$undotext;
			}
			
			$removecart_msg = "{product_name} remove";
			//checking remove cart configuration message otherwise default
			if($removecart_msg_config){
			    $removecart_msg=$removecart_msg_config;
			}
			//replacing {product_name} with orginal product name
			$removecart_msg = str_replace("{product_name}",$undo_arr['name'], $removecart_msg);	
			$msg = $removecart_msg.'<a href="'.$base_url.'undocart/index/add/"> '.$undotext.'</a></div>'; 
					    
			//return undo cart response link 			
			Mage::getSingleton('core/session')->addSuccess(Mage::helper('customer')->__($msg));  		
		}		
	}	    
}  

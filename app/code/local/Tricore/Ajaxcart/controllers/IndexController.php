<?php

class Tricore_Ajaxcart_IndexController extends Mage_Core_Controller_Front_Action {

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
     * error check for qty in stock or not
     */
    public function ErrorMsg() {
        $cartItems = Mage::getSingleton('checkout/cart')->getQuote()->getAllVisibleItems(); 
        $checkoutSession = Mage::getSingleton('checkout/session');
        foreach ($cartItems as $item) {
            if ($checkoutSession) {
                $baseMessages = $item->getMessage(false);
                if ($baseMessages) {
                    foreach ($baseMessages as $message) {
                        $messages[] = array(
                            'text' => $message,
                            'type' => $item->getHasError() ? 'error' : 'notice'
                            );
                    }
                }

                /*
                 * @var $collection Mage_Core_Model_Message_Collection
                 */ 
                $collection = $checkoutSession->getQuoteItemMessages($item->getId(), true);
                if ($collection) {
                    $additionalMessages = $collection->getItems();
                    foreach ($additionalMessages as $message) {
                        /* @var $message Mage_Core_Model_Message_Abstract */
                        $messages[] = array(
                            'text' => $message->getCode(),
                            'type' => ($message->getType() == Mage_Core_Model_Message::ERROR) ? 'error' : 'notice'
                            );
                    }
                }
            }
        }
        $errMsgCount = count($messages, COUNT_RECURSIVE);


            /*
             * update checkout/session to show checkout button if no error
             */ 
        if(!$errMsgCount){ $checkoutSession->getQuote()->setHasError(FALSE); }
    }

	/*
	* Method for delete item from cart sidebar and from checkout page
	*/ 
	public function deleteCartAction() {
		
		$id = (int)$this->getRequest()->getParam('id');
		$response = array();
		
		if($id) {
			try {
				//$item = $this->_getCart()->getQuote()->getItemById($id);
				$this->_getCart()->removeItem($id)->save();
				//Mage::dispatchEvent('sales_quote_remove_item', array('quote_item' => $item));
			} catch(Exception $e) {
				Mage::logException($e);
				$response['status'] = 'ERROR';
				$response['message'] = $this->__('Cannot remove the item.');
			}
		}

		$this->ErrorMsg();
		$this->loadLayout();
		$block = $this->getLayout()->getBlock('checkout.cart')->toHtml();
		$response['checkout'] = $block;

		/*
		* check if theme contains minicart block
		*/ 
		if($minicart = $this->getMinicartBlock()) {
			$response['minicart'] = $minicart;
		} elseif($toplink = $this->getToplinkBlock()) { 
			/*
			* check if theme contains top links block
			*/ 
			$response['toplink'] = $toplink;
		}
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
	}

    public function deleteAllAction() {
        $response = array();
        try {
            Mage::getSingleton('checkout/cart')->truncate()->save();
        } catch(Exception $e) {
            Mage::logException($e);
            $response['status'] = 'ERROR';
            $response['message'] = $this->__('Cannot remove the item.');
        }
        $block = $this->loadLayout()->getLayout()->getBlock('checkout.cart')->toHtml();
        $response['checkout'] = $block;

            /*
             * check if theme contains minicart block
             */ 
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { 

            /*
             * check if theme contains top links block
             */ 
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function updateAction() {

        $id = (int)$this->getRequest()->getParam('id');
        $qty = $this->getRequest()->getParam('qty');
        $response = array();
        if($id) {
            try {
                $cart = $this->_getCart();
                if (isset($qty)) {
                    $filter = new Zend_Filter_LocalizedToNormalized(
                        array('locale' => Mage::app()->getLocale()->getLocaleCode())
                        );
                    $qty = $filter->filter($qty);
                }

                $quoteItem = $cart->getQuote()->getItemById($id);
                if (!$quoteItem) {
                    Mage::throwException($this->__('Quote item is not found.'));
                }
                if ($qty == 0) {
                    $cart->removeItem($id);
                } else {
                    $quoteItem->setQty($qty)->save();
                }
                $this->_getCart()->save();
            } catch (Exception $e) {
                Mage::logException($e);
                $response['success'] = 0;
                $response['error'] = $this->__('Can not save item.');
            }

        }

            /*
             * error msg function for check error or not
             */ 
        $this->ErrorMsg();

        $block = $this->loadLayout()->getLayout()->getBlock('checkout.cart')->toHtml();
        $response['checkout'] = $block;

            /*
             * check if theme contains minicart block
             */ 
        if($minicart = $this->getMinicartBlock()) {
            $response['minicart'] = $minicart;
        } elseif($toplink = $this->getToplinkBlock()) { 

            /*
             * check if theme contains top links block
             */ 
            $response['toplink'] = $toplink;
        }
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    /**
     * Create block for rwd template
     */
    private function getMinicartBlock() {
        return ($minicart_block = $this->getLayout()->getBlock('minicart_head')) ? $minicart_block->toHtml() : null;
    }

    private function getToplinkBlock() {
        return ($toplink = $this->getLayout()->getBlock('top.links')) ? $toplink->toHtml() : null;
    }
}

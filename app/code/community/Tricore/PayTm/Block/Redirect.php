<?php
class Tricore_PayTm_Block_Redirect extends Mage_Core_Block_Template {
    
    /**
     * Return checkout session instance 
     */
    protected function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

	/**
	 * Get the url from paytm.com 
	 */
    public function getFormAction() {
      return Mage::getModel('paytm/cc')->getUrl();
    }

	/**
	 * Get the form data which use for api call 
	 */
    public function getFormData() {
       return Mage::getModel('paytm/cc')->getParam();
    }
    
}

<?php
class Tricore_PayTm_Model_Paytm extends Mage_Core_Model_Abstract {
    
    public function _construct() {
        parent::_construct();
        $this->_init('paytm/paytm', 'paytm_id');
    }
}

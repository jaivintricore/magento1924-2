<?php
 
class Tricore_PayTm_Model_Mysql4_Paytm extends Mage_Core_Model_Mysql4_Abstract {
	
    public function _construct() {   
        $this->_init('paytm/paytm');
    }
}

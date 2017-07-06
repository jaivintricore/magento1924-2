<?php
 
class Tricore_PayTm_Model_Mysql4_Paytm_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

    public function _construct() {
        parent::__construct();
        $this->_init('paytm/paytm');
    }
}

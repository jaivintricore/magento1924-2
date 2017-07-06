<?php
class Tricore_PayuCheckout_Block_Info extends Mage_Payment_Block_Info {
    
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('payucheckout/info.phtml');
    }

    public function getMethodCode() {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    public function toPdf() {
        $this->setTemplate('payucheckout/pdf/info.phtml');
        return $this->toHtml();
    }
}

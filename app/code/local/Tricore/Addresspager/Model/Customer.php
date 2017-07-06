<?php

class Tricore_Addresspager_Model_Customer extends Mage_Customer_Model_Customer
{
    /**
     * Customer addresses collection
     *
     * @return Mage_Customer_Model_Entity_Address_Collection
     */
    public function getAddressesCollection()
    {
        if ($this->_addressesCollection === null) {
			/* Below code added by tricore.dev11 Date:04-08-2016 */
			if(Mage::app()->getStore()->isAdmin() && Mage::app()->getRequest()->getControllerName() == "customer" && (Mage::app()->getRequest()->getActionName() == "edit" || Mage::app()->getRequest()->getActionName() == "save"))
			{
				$limit = 10;
				$curr_page = 1;											
				if(Mage::app()->getRequest()->getParam('address_p'))
				{
					$curr_page = Mage::app()->getRequest()->getParam('address_p');
				}
				if(Mage::app()->getRequest()->getParam('address_limit'))
				{
					$limit = Mage::app()->getRequest()->getParam('address_limit');
				}
				
				$this->_addressesCollection = $this->getAddressCollection()
					->setCustomerFilter($this)
					->addAttributeToSelect('*')->setOrder('firstname','ASC')->setPageSize($limit)->setCurPage($curr_page);
				foreach ($this->_addressesCollection as $address) {
					$address->setCustomer($this);
				}
			}
			else
			{
				$this->_addressesCollection = $this->getAddressCollection()
					->setCustomerFilter($this)
					->setOrder('firstname','ASC')
					->addAttributeToSelect('*');
				foreach ($this->_addressesCollection as $address) {
					$address->setCustomer($this);
				}
			}
			/* Above code added by tricore.dev11 Date:04-08-2016 */
        }
		
        return $this->_addressesCollection;
    }
}

<?php
class Tricore_Addresspager_Block_Address_Book extends Mage_Core_Block_Template
{
    protected $_collection;

    protected function _prepareLayout()
    {
		$this->getLayout()->getBlock('head')->setTitle(Mage::helper('customer')->__('Address Book'));   
		$limit = 10;
		$curr_page = 1;
		if(Mage::app()->getRequest()->getParam('p')){
			$curr_page = Mage::app()->getRequest()->getParam('p');
		}
		if(Mage::app()->getRequest()->getParam('limit')){
			$limit = Mage::app()->getRequest()->getParam('limit');
		}
		$primatyIds = $this->getCustomer()->getPrimaryAddressIds();
		if(!empty($primatyIds)){
			$this->_collection = $this->getCustomer()->getAddressCollection()
                ->setCustomerFilter($this->getCustomer())
                ->addAttributeToFilter("entity_id",array("nin"=>$primatyIds))
                ->addAttributeToSelect('*')->setPageSize($limit)->setCurPage($curr_page);
		}
		else {
			$this->_collection = $this->getCustomer()->getAddressCollection()
                ->setCustomerFilter($this->getCustomer())                
                ->addAttributeToSelect('*')->setPageSize($limit)->setCurPage($curr_page);
		}
        $pager = $this->getLayout()->createBlock('page/html_pager','custom.pager');
		$pager->setCollection($this->_collection);			
		$this->setChild('pager', $pager);
        return parent::_prepareLayout();
        
        
    }

    public function getAddAddressUrl()
    {
        return $this->getUrl('customer/address/new', array('_secure'=>true));
    }

    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {
            return $this->getRefererUrl();
        }
        return $this->getUrl('customer/account/', array('_secure'=>true));
    }

    public function getDeleteUrl()
    {
        return $this->getUrl('customer/address/delete');
    }

    public function getAddressEditUrl($address)
    {
        return $this->getUrl('customer/address/edit', array('_secure'=>true, 'id'=>$address->getId()));
    }

    public function getPrimaryBillingAddress()
    {
        return $this->getCustomer()->getPrimaryBillingAddress();
    }

    public function getPrimaryShippingAddress()
    {
        return $this->getCustomer()->getPrimaryShippingAddress();
    }

    public function hasPrimaryAddress()
    {
        return $this->getPrimaryBillingAddress() || $this->getPrimaryShippingAddress();
    }

    public function getAdditionalAddresses()
    {
        $addresses = $this->getCustomer()->getAdditionalAddresses();
        return empty($addresses) ? false : $addresses;
    }

    public function getAddressHtml($address)
    {
        return $address->format('html');
        //return $address->toString($address->getHtmlFormat());
    }

    public function getCustomer()
    {
        $customer = $this->getData('customer');
        if (is_null($customer)) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $this->setData('customer', $customer);
        }
        return $customer;
    }
    public function getPagerHtml()
    {
        return $this->getChildHtml('pager');
    }
    public function getCollection()
    {
        return $this->_collection;
    }
}

<?php
/**
 * PayUmoney Checkout Model
 */
class Tricore_PayuCheckout_Model_Shared_Checkout
{

	public function __construct($params = array()) {
		if (isset($params['quote']) && $params['quote'] instanceof Mage_Sales_Model_Quote) {
			$this->_quote = $params['quote'];
		} else {
			throw new Exception('Quote instance is required.');
		}
		$this->_customerSession = isset($params['session']) && $params['session'] instanceof Mage_Customer_Model_Session
			? $params['session'] : Mage::getSingleton('customer/session');
    }
    
	/**
     * Save the order after successful Payment
     */
    public function place($response) {

		if ($shippingMethodCode) {
			$this->updateShippingMethod($shippingMethodCode);
		}

		$isNewCustomer = false;
		//check customer checkout method
		switch ($this->getCheckoutMethod()) {
			case Mage_Checkout_Model_Type_Onepage::METHOD_GUEST:
				$this->_prepareGuestQuote();
				break;
			case Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER:
				$this->_prepareNewCustomerQuote();
				$isNewCustomer = true;
				break;
			default:
				$this->_prepareCustomerQuote();
				break;
		}

		$this->_ignoreAddressValidation();
		$this->_quote->collectTotals();
		$service = Mage::getModel('sales/service_quote', $this->_quote);
		$service->submitAll();
		$this->_quote->save();

		if ($isNewCustomer){
			try {
				$this->_involveNewCustomer();
			}
			catch (Exception $e) {
				Mage::logException($e);
			}
		}

		$this->_recurringPaymentProfiles = $service->getRecurringPaymentProfiles();
		$order = $service->getOrder();
		if (!$order) {
			return;
		}
		
		$res_amt=$response['amount'];//rupee
		$payumoneyCurrencyCode="INR";
		$baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();//get store base currency code
		$allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies();
		$rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
		$price=$res_amt/$rates[$payumoneyCurrencyCode];
		$amount=number_format($price, 2, '.', '');
		
		//save order transaction detail with paytm transaction id
		$ptxn = 'N/A';
        $payment = $order->getPayment();
		$payment->setTransactionId($response['mihpayid'])
				->setCurrencyCode($order->getBaseCurrencyCode())
				->setPreparedMessage("PayU Money Order Sucess")
				->setParentTransactionId($ptxn)
				->setShouldCloseParentTransaction(true)
				->setIsTransactionClosed(0)
				->registerCaptureNotification($amount);          
				//->registerCaptureNotification($order->getBaseGrandTotal());          
		$order->sendNewOrderEmail();
		$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

		$order->save();
		
		$this->updateInventory($order_id);
		
        $this->_order = $order;
	}
		
	
	public function updateInventory($order_id) {

        $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        $items = $order->getAllItems();
        foreach ($items as $itemId => $item) {
            $ordered_quantity = $item->getQtyToInvoice();
            $sku = $item->getSku();
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId())->getQty();

            $updated_inventory = $qtyStock + $ordered_quantity;

            $stockData = $product->getStockItem();
            $stockData->setData('qty', $updated_inventory);
            $stockData->save();
        }
    }

	/**
     * Get Customer Checkout method
     */
    public function getCheckoutMethod() {

		if ($this->getCustomerSession()->isLoggedIn()) {
			return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
		}

		if (!$this->_quote->getCheckoutMethod()) {
			if (Mage::helper('checkout')->isAllowedGuestCheckout($this->_quote)) {
				$this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
			} else {
				$this->_quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER);
			}
		}
		return $this->_quote->getCheckoutMethod();
    }
    
	/**
     * Prepare new Quote for new register Customer
     */
    public function _prepareNewCustomerQuote() {
		
		$quote      = $this->_quote;
		$billing    = $quote->getBillingAddress();
		$shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();
		$customerId = $this->_lookupCustomerId();
		if ($customerId) {
			$this->getCustomerSession()->loginById($customerId);
			return $this->_prepareCustomerQuote();
		}
		$customer = $quote->getCustomer();
		$customerBilling = $billing->exportCustomerAddress();
		$customer->addAddress($customerBilling);
		$billing->setCustomerAddress($customerBilling);
		$customerBilling->setIsDefaultBilling(true);
		if ($shipping && !$shipping->getSameAsBilling()) {
			$customerShipping = $shipping->exportCustomerAddress();
			$customer->addAddress($customerShipping);
			$shipping->setCustomerAddress($customerShipping);
			$customerShipping->setIsDefaultShipping(true);
		} elseif ($shipping) {
			$customerBilling->setIsDefaultShipping(true);
		}
		
		if ($quote->getCustomerDob() && !$billing->getCustomerDob()) {
			$billing->setCustomerDob($quote->getCustomerDob());
		}

		if ($quote->getCustomerTaxvat() && !$billing->getCustomerTaxvat()) {
			$billing->setCustomerTaxvat($quote->getCustomerTaxvat());
		}

		if ($quote->getCustomerGender() && !$billing->getCustomerGender()) {
			$billing->setCustomerGender($quote->getCustomerGender());
		}

		Mage::helper('core')->copyFieldset('checkout_onepage_billing', 'to_customer', $billing, $customer);
		$customer->setEmail($quote->getCustomerEmail());
		$customer->setPrefix($quote->getCustomerPrefix());
		$customer->setFirstname($quote->getCustomerFirstname());
		$customer->setMiddlename($quote->getCustomerMiddlename());
		$customer->setLastname($quote->getCustomerLastname());
		$customer->setSuffix($quote->getCustomerSuffix());
		$customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
		$customer->setPasswordHash($customer->hashPassword($customer->getPassword()));
		$customer->save();
		$quote->setCustomer($customer);

		return $this;
    }

    /**
     * Prepare quote for customer order submit
     */
    protected function _prepareCustomerQuote() {
		$quote      = $this->_quote;
		$billing    = $quote->getBillingAddress();
		$shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

		$customer = $this->getCustomerSession()->getCustomer();
		if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) 
		{
			$customerBilling = $billing->exportCustomerAddress();
			$customer->addAddress($customerBilling);
			$billing->setCustomerAddress($customerBilling);
		}
		if ($shipping && ((!$shipping->getCustomerId() && !$shipping->getSameAsBilling())
			|| (!$shipping->getSameAsBilling() && $shipping->getSaveInAddressBook()))) 
		{
			$customerShipping = $shipping->exportCustomerAddress();
			$customer->addAddress($customerShipping);
			$shipping->setCustomerAddress($customerShipping);
		}

		if (isset($customerBilling) && !$customer->getDefaultBilling()) 
		{
			$customerBilling->setIsDefaultBilling(true);
		}
		
		if ($shipping && isset($customerBilling) && !$customer->getDefaultShipping() && $shipping->getSameAsBilling()) 
		{
			$customerBilling->setIsDefaultShipping(true);
		} 
		elseif ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) 
		{
			$customerShipping->setIsDefaultShipping(true);
		}
		$quote->setCustomer($customer);

		return $this;
    }

    /**
     * Involve new customer to system
     */
	protected function _involveNewCustomer() {
		$customer = $this->_quote->getCustomer();
		$this->getCustomerSession()->loginById($customer->getId());
		return $this;
	}
	
	/**
     * Prepare quote for guest user
     */
    protected function _prepareGuestQuote() {
		$quote = $this->_quote;
		$quote->setCustomerId(null)
			->setCustomerEmail($quote->getBillingAddress()->getEmail())
			->setCustomerIsGuest(true)
			->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
		return $this;
    }

    private function _ignoreAddressValidation() {
		$this->_quote->getBillingAddress()->setShouldIgnoreValidation(true);
		if (!$this->_quote->getIsVirtual()) {
			$this->_quote->getShippingAddress()->setShouldIgnoreValidation(true);
			if (!$this->_config->requireBillingAddress && !$this->_quote->getBillingAddress()->getEmail()) {
				$this->_quote->getBillingAddress()->setSameAsBilling(1);
			}
		}
    }
    
	/**
     * Get new customer id
     */
    protected function _lookupCustomerId() {
		return Mage::getModel('customer/customer')
			->setWebsiteId(Mage::app()->getWebsite()->getId())
			->loadByEmail($this->_quote->getCustomerEmail())
			->getId();
    }

	/**
     * Return order object
     */
    public function getOrder() {
		return $this->_order;
    }
    
	/**
     * Get Customer session detail
     */
    public function getCustomerSession() {
		return $this->_customerSession;
    }
}

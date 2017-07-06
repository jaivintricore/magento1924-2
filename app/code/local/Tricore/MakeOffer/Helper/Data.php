<?php
class Tricore_MakeOffer_Helper_Data extends Mage_Core_Helper_Abstract
{
  /*
  *Get store config setting of button name
  */
  public function getOfferButton() {
    return Mage::getStoreConfig('makeoffer/button_name/button', Mage::app()->getStore());
  }

  /*
  *Get store config setting of user selection(login or for all)
  */
  public function getUserConfig() {
    return Mage::getStoreConfig('makeoffer/user/login_user', Mage::app()->getStore());
  }

  /*
  *Get comma seprated mail from store config setting
  */
  public function getCopyMail() {
    return Mage::getStoreConfig('makeoffer/email/copy_mail', Mage::app()->getStore());
  }

  /*
  *Get form heading from store config setting
  */
  public function getFormHead() {
    return Mage::getStoreConfig('makeoffer/button_name/form_heading', Mage::app()->getStore());
  }

  /*
  * Get the url  secure or unsecure
  */
  public function getFormAction()
  {
    return Mage::getUrl('makeoffer/index/sendemail',array('_secure' => Mage::App()->getStore()->isCurrentlySecure()));
  }
} 
?>

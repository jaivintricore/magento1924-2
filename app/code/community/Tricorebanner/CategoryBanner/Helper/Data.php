<?php
/**
 * Helper of Tricorebanner_CategoryBanner package
 */
class Tricorebanner_CategoryBanner_Helper_Data extends Mage_Core_Helper_Abstract {
    /**
     * Detect if we can display banner on current page
     * @return boolean
     */
    public function canShowBanner() {
        $result = false;

        $routeName = Mage::app()->getRequest()->getRouteName();
        $identifier = Mage::getSingleton('cms/page')->getIdentifier();
        $actionName = Mage::app()->getFrontController()->getAction()->getFullActionName();

        $categoryBannerStatus = Mage::getStoreConfigFlag('categorysalesbanner/enablecategorybanner/categorybanner_enable', Mage::app()->getStore()->getStoreId());
        $displayBannerHome = Mage::getStoreConfigFlag('categorysalesbanner/enablecategorybanner/displayBannerHome');
        $displayBannerCartPage = Mage::getStoreConfigFlag('categorysalesbanner/enablecategorybanner/display_banner_cart_page');
        $excludedCategories = array_map('trim', explode(',', Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/exclude_category')));

        $currentCat = Mage::registry('current_category');
        $currentCatId = null;
        if ($currentCat) {
            $currentCatId = $currentCat->getId();
        }

        if (
            ($displayBannerHome == false && $routeName == 'cms' && $identifier == 'home') 
            || ($displayBannerCartPage == false && $actionName == 'checkout_cart_index') 
        ) {
            $categoryBannerStatus = false;
        }

        if ($categoryBannerStatus && !in_array($currentCatId, $excludedCategories)) {
            $result = true;
        }

        return $result;
    }

    /**
     * Collect data to display category banner
     *
     * @return array
     */
    public function getCategoryBannerData() {
        $result = array();
        $actionName = Mage::app()->getFrontController()->getAction()->getFullActionName();

        $result['media_path'] = Mage::getBaseUrl('media') . 'images/';
        $result['cat_img_url'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categorybannerimage');
        $result['cat_img_url_mobile'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categorybannerimagemobile');
        $result['cat_img_text'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categoryimagetext');
        $result['cat_img_link'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categoryimagelink');
        $result['cat_img_link_popup'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categoryimagelinkdesktop');
        $result['cat_img_link_popup_mapping_cordinates'] = Mage::getStoreConfig('categorysalesbanner/enablecategorybanner/categoryimagelinkcssdesktop');
        $result['sales_timer_status'] = Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_enable');
        $result['sales_timer_date'] = array_map('trim', explode('/', Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_expire')));
        $result['sales_timer_time'] = array_map('trim', explode(',', Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_time')));
        $result['timer_pos'] = Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_position_style');
        $result['timer_text'] = Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_text_style');
		$result['display_days'] = Mage::getStoreConfigFlag('categorysalesbanner/categorytimer_enable/display_days_timer');

        if($result['product_img_link'] && $actionName == 'catalog_product_view'){
            $result['cat_img_link'] = $result['product_img_link'];
        }
        if (Mage::app()->getStore()->isCurrentlySecure()) {
            $result['base_url'] = Mage::getUrl('',array('_secure'=>true));
        }
        else{
            $result['base_url'] = Mage::getBaseUrl();
        }
        if(Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/product_image_timer') == false && $actionName == 'catalog_product_view')
        {
            $result['sales_timer_status'] = false;
        }

        $tzone = Mage::getStoreConfig('categorysalesbanner/categorytimer_enable/timer_timezone');
        if (!$tzone) {
            $tzone = "America/Los_Angeles";
        }
        $result['tz'] = $this->getTimezoneOffset($tzone)/3600;

        return $result;
    }

    /**
     * Get timezone offset
     *
     * @param  string $remote_tz
     * @param  string $origin_tz
     * @return mixed
     */
    private function getTimezoneOffset($remote_tz, $origin_tz = null) {
        if($origin_tz === null) {
            if(!is_string($origin_tz = date_default_timezone_get())) {
                return false; // A UTC timestamp was returned -- bail out!
            }
        }

        $origin_dtz = new DateTimeZone($origin_tz);
        $remote_dtz = new DateTimeZone($remote_tz);
        $origin_dt = new DateTime("now", $origin_dtz);
        $remote_dt = new DateTime("now", $remote_dtz);
        $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);

        return $offset;
    }
}

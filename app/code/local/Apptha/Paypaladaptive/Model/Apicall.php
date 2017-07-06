<?php

/**
 * Apptha
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.apptha.com/LICENSE.txt
 *
 * ==============================================================
 *                 MAGENTO EDITION USAGE NOTICE
 * ==============================================================
 * This package designed for Magento COMMUNITY edition
 * Apptha does not guarantee correct work of this extension
 * on any other Magento edition except Magento COMMUNITY edition.
 * Apptha does not provide extension support in case of
 * incorrect edition usage.
 * ==============================================================
 *
 * @category    Apptha
 * @package     Apptha_PayPaladaptive
 * @version     0.1.2
 * @author      Apptha Team <developers@contus.in>
 * @copyright   Copyright (c) 2014 Apptha. (http://www.apptha.com)
 * @license     http://www.apptha.com/LICENSE.txt
 * 
 */

/**
 * In this class contains the PayPal Api call functions
 */

class Apptha_Paypaladaptive_Model_Apicall {
    /**
     * Pay call to PayPal 
     * 
     * @param string $methodName call method
     * @param string $nvpStr NVPRequest
     * 
     * @return array PayPal response
     */

    function hashCall($methodName, $nvpStr) {
        /**
         * Set the curl parameters     
         */
        $ApiUserName = Mage::helper('paypaladaptive')->getApiUserName();
        $ApiPassword = Mage::helper('paypaladaptive')->getApiPassword();
        $ApiSignature = Mage::helper('paypaladaptive')->getApiSignature();
        $ApiAppID = Mage::helper('paypaladaptive')->getAppID();
        $mode = Mage::helper('paypaladaptive')->getPaymentMode();

        if ($mode == 1) {
            $ApiEndpoint = "https://svcs.sandbox.paypal.com/AdaptivePayments";
            $ApiEndpoint .= "/" . $methodName;
        } else {
            $ApiEndpoint = "https://svcs.paypal.com/AdaptivePayments";
            $ApiEndpoint .= "/" . $methodName;
        }

        try {

            $curl = new Varien_Http_Adapter_Curl();
            /**
             * See DetailLevelCode in the WSDL 
             */
            $detailLevel = urlencode("ReturnAll");

            /**
             * For valid enumerations
             * This should be the standard RFC 
             */
            $errorLanguage = urlencode("en_US");

            /**
             * NVPRequest for submitting to server
             */
            $nvpreq = "requestEnvelope.errorLanguage=$errorLanguage&requestEnvelope";
            $nvpreq .= "detailLevel=$detailLevel&$nvpStr";

            /**
             * The below line for SSL 
             */
            //$config = array('timeout' => 60,'verifypeer' => true,'verifyhost' => 2);

            $config = array('timeout' => 60, 'verifypeer' => FALSE, 'verifyhost' => FALSE);
            $curl->setConfig($config);

            /**
             * Set the curl parameters
             */
            $curl->addOption('CURLOPT_VERBOSE', 1);

            $header = array(
                'X-PAYPAL-REQUEST-DATA-FORMAT: NV',
                'X-PAYPAL-RESPONSE-DATA-FORMAT: NV',
                'X-PAYPAL-SECURITY-USERID: ' . $ApiUserName,
                'X-PAYPAL-SECURITY-PASSWORD: ' . $ApiPassword,
                'X-PAYPAL-SECURITY-SIGNATURE: ' . $ApiSignature,
                'X-PAYPAL-SERVICE-VERSION: 1.3.0',
                'X-PAYPAL-APPLICATION-ID: ' . $ApiAppID
            );

            $curl->write(Zend_Http_Client::POST, $ApiEndpoint, $http_ver = '1.1', $header, $nvpreq);

            $data = $curl->read();

            $errNo = $curl->getErrno();

            if ($errNo == 60) {
                $cacert = Mage::getBaseDir('lib') . '/paypaladaptive/cacert.pem';
                $curl->addOption('CURLOPT_CAINFO', $cacert);
                $data = $curl->read();
            }

            if ($curl->getErrno()) {
                /**
                 * Execute the Error handling module to display errors
                 */
                Mage::getSingleton('checkout/session')->addError($curl->getError());
                return;
            } else {
                /**
                 * Convert NVPResponse to an Associative Array  
                 */
                $nvpResArray = $this->deformatNVP($data);
                /**
                 * Close curl
                 */
                $curl->close();
            }
            /**
             * Return Response data
             */
            return $nvpResArray;
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            return;
        }
    }

    /**
     * Prepares the parameters for the PaymentDetails API Call    
     * 
     * @param string $payKey PayPal pay key
     * @param string $transactionId PayPal transaction id
     * @param string $trackingId Paypal tracking id
     * 
     * @return array PayPal response
     */

    public function CallPaymentDetails($payKey, $transactionId, $trackingId) {

        /**
         * Collection the information to make the PaymentDetails call        
         */
        $nvpstr = "";
        if ("" != $payKey) {
            $nvpstr = "payKey=" . urlencode($payKey);
        } elseif ("" != $transactionId) {
            $nvpstr = "transactionId=" . urlencode($transactionId);
        } elseif ("" != $trackingId) {
            $nvpstr = "trackingId=" . urlencode($trackingId);
        }
        /**
         * Make the PaymentDetails call to PayPal
         */
        $resArray = $this->hashCall("PaymentDetails", $nvpstr);
        return $resArray;
    }

    /**
     * This function will take NVPString and convert it to an Associative Array   
     * 
     * @param string $nvpstr request     
     * @return array decoded request 
     */

    public function deformatNVP($nvpstr) {
        $intial = 0;
        $nvpArray = array();

        while (strlen($nvpstr)) {
            $keypos = strpos($nvpstr, '=');
            $valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);
            $keyval = substr($nvpstr, $intial, $keypos);
            $valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);
            $nvpArray[urldecode($keyval)] = urldecode($valval);
            $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
        }
        return $nvpArray;
    }

    /**
     * Collect the information to make the Pay call
     * 
     * @param string $actionType action type
     * @param string $cancelUrl cancel url
     * @param string $returnUrl return url
     * @param string $currencyCode currency code
     * @param array $receiverEmailArray receiver email 
     * @param array $receiverAmountArray receiver amount
     * @param array $receiverPrimaryArray receiver primary value
     * @param array $receiverInvoiceIdArray receiver invoice
     * @param string $feesPayer fees payer type
     * @param string $ipnNotificationUrl url
     * @param string $memo memo
     * @param string $pin pin
     * @param string $preapprovalKey preapproval key
     * @param string $reverseAllParallelPaymentsOnError error type
     * @param string $senderEmail ender email
     * @param string $trackingId PayPayl tracking id
     * @return array PayPal response 
     */

    public function CallPay($actionType, $cancelUrl, $returnUrl, $currencyCode, $receiverEmailArray, $receiverAmountArray, $receiverPrimaryArray, $receiverInvoiceIdArray, $feesPayer, $ipnNotificationUrl, $memo, $pin, $preapprovalKey, $reverseAllParallelPaymentsOnError, $senderEmail, $trackingId) {

        $memo = $pin = $preapprovalKey = $senderEmail = '';
        $nvpstr = "actionType=" . urlencode($actionType) . "&currencyCode=";
        $nvpstr .= urlencode($currencyCode) . "&returnUrl=";
        $nvpstr .= urlencode($returnUrl) . "&cancelUrl=" . urlencode($cancelUrl);

        if (0 != count($receiverAmountArray)) {
            $nvpstr .= $this->receiverAmountData($receiverAmountArray, $nvpstr);
        }

        if (0 != count($receiverEmailArray)) {
            $nvpstr .= $this->receiverEmailData($receiverEmailArray, $nvpstr);
        }

        if (0 != count($receiverPrimaryArray)) {
            $nvpstr .= $this->receiverPrimaryData($receiverPrimaryArray, $nvpstr);
        }

        if (0 != count($receiverInvoiceIdArray)) {
            $nvpstr .= $this->receiverInvoiceIdData($receiverInvoiceIdArray, $nvpstr);
        }

        /**
         * Optional fields for pay call
         */
        if ("" != $feesPayer) {
            $nvpstr .= "&feesPayer=" . urlencode($feesPayer);
        }
        if ("" != $ipnNotificationUrl) {
            $nvpstr .= "&ipnNotificationUrl=" . urlencode($ipnNotificationUrl);
        }

        if ("" != $reverseAllParallelPaymentsOnError) {
            $nvpstr .= "&reverseAllParallelPaymentsOnError=";
            $nvpstr .= urlencode($reverseAllParallelPaymentsOnError);
        }

        if ("" != $trackingId) {
            $nvpstr .= "&trackingId=" . urlencode($trackingId);
        }
        /**
         * Make the Pay call to PayPal 
         */
        $resArray = $this->hashCall("Pay", $nvpstr);
        return $resArray;
    }

    /**
     * Prepares the parameters for the Refund API Call   
     * 
     * @param string $payKey PayPal pay key
     * @param string $transactionId transaction id
     * @param array $receiverEmailArray receiver email 
     * @param array $receiverAmountArray receiver amount
     * @param array $currencyCode currency code
     * @return array PayPal response 
     */

    function CallRefund($payKey, $transactionId, $trackingId, $receiverEmailArray, $receiverAmountArray, $currencyCode) {

        $nvpstr = "currencyCode=";
        $nvpstr .= urlencode($currencyCode);

        if ("" != $payKey) {
            $nvpstr .= "&payKey=" . urlencode($payKey);
            if (0 != count($receiverEmailArray)) {
                $nvpstr .= $this->receiverEmailData($receiverEmailArray, $nvpstr);
            }
            if (0 != count($receiverAmountArray)) {
                $nvpstr .= $this->receiverAmountData($receiverAmountArray, $nvpstr);
            }
        } elseif ("" != $trackingId) {
            $nvpstr .= "&trackingId=" . urlencode($trackingId);
            if (0 != count($receiverEmailArray)) {
                $nvpstr .= $this->receiverEmailData($receiverEmailArray, $nvpstr);
            }
            if (0 != count($receiverAmountArray)) {
                $nvpstr .= $this->receiverAmountData($receiverAmountArray, $nvpstr);
            }
        } elseif ("" != $transactionId) {
            $nvpstr .= "&transactionId=" . urlencode($transactionId);
            if (0 != count($receiverEmailArray)) {
                $nvpstr .= $this->receiverEmailData($receiverEmailArray, $nvpstr);
            }
            if (0 != count($receiverAmountArray)) {
                $nvpstr .= $this->receiverAmountData($receiverAmountArray, $nvpstr);
            }
        }
        /**
         * Make the Refund call to PayPal 
         */
        $resArray = $this->hashCall("Refund", $nvpstr);

        return $resArray;
    }

    /**
     * Prepare nvpstr request by receiver amount 
     * 
     * @param array $receiverAmountArray receiver amount
     * @param string $nvpstr request
     * @return string nvpstr request
     */

    public function receiverAmountData($receiverAmountArray, $nvpstr) {
        reset($receiverAmountArray);
        while (list($key, $value) = each($receiverAmountArray)) {
            if ("" != $value) {
                $nvpstr .= "&receiverList.receiver(" . $key . ").amount=" . urlencode($value);
            }
        }
        return $nvpstr;
    }

    /**
     * Prepare nvpstr request by receiver email 
     * 
     * @param array $receiverEmailArray receiver amount
     * @param string $nvpstr request
     * @return string nvpstr request
     */

    public function receiverEmailData($receiverEmailArray, $nvpstr) {
        reset($receiverEmailArray);
        while (list($key, $value) = each($receiverEmailArray)) {
            if ("" != $value) {
                $nvpstr .= "&receiverList.receiver(" . $key . ").email=" . urlencode($value);
            }
        }
        return $nvpstr;
    }

    /**
     * Prepare nvpstr request by receiver primary 
     * 
     * @param array $receiverPrimaryArray receiver amount
     * @param string $nvpstr request
     * @return string nvpstr request
     */

    public function receiverPrimaryData($receiverPrimaryArray, $nvpstr) {
        reset($receiverPrimaryArray);
        while (list($key, $value) = each($receiverPrimaryArray)) {
            if ("" != $value) {
                $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").primary=" .
                        urlencode($value);
            }
        }
        return $nvpstr;
    }

    /**
     * Prepare nvpstr request by receiver invoice 
     * 
     * @param array $receiverInvoiceIdArray receiver amount
     * @param string $nvpstr request
     * @return string nvpstr request
     */

    public function receiverInvoiceIdData($receiverInvoiceIdArray, $nvpstr) {
        reset($receiverInvoiceIdArray);
        while (list($key, $value) = each($receiverInvoiceIdArray)) {
            if ("" != $value) {
                $nvpstr = $nvpstr . "&receiverList.receiver(" . $key . ").invoiceId=" .
                        urlencode($value);
            }
        }
        return $nvpstr;
    }

    /**
     *  Make the Execute Payment to secondary receivers  
     * 
     * @param $payKey PayPal pay key
     * @param $trackingId PayPal tracking id
     * @param $transactionId PayPal transaction id
     * @return array PayPal response
     */

    public function executePayment($payKey, $trackingId, $transactionId) {

        $nvpstr = "";
        if ("" != $payKey) {
            $nvpstr = "payKey=" . urlencode($payKey);
        } elseif ("" != $transactionId) {
            $nvpstr = "transactionId=" . urlencode($transactionId);
        } elseif ("" != $trackingId) {
            $nvpstr = "trackingId=" . urlencode($trackingId);
        }

        $resArray = $this->hashCall("ExecutePayment", $nvpstr);
        return $resArray;
    }
    
    /**
     *  Collect the information to set payment option (the referrercode - bncode)
     *
     *  @return array PayPal response
     */
    
    public function setPaymentOption($payKey){
    
    	$bnCode = "Contus_SP";
    	$nvpstr = "";
    	$nvpstr = "payKey=" . urlencode($payKey);
    	 
    	$nvpstr = $nvpstr . "&senderOptions[0].referrerCode=" .
    			urlencode($bnCode);
    	 
    	/**
    	 * Set the payment options call to PayPal
    	*/
    	$resArray = $this->hashCall("SetPaymentOptions", $nvpstr);
    	return $resArray;
    }

}
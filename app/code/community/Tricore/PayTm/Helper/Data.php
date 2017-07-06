<?php
//contains utility functions for encryption decrytion
class Tricore_PayTm_Helper_Data extends Mage_Payment_Helper_Data {
	
	public $PAYTM_PAYMENT_URL_PROD = "https://secure.paytm.in/oltp-web/processTransaction";
	public $STATUS_QUERY_URL_PROD = "https://secure.paytm.in/oltp/HANDLER_INTERNAL/TXNSTATUS";

	public $PAYTM_PAYMENT_URL_TEST = "https://pguat.paytm.com/oltp-web/processTransaction";
	public $STATUS_QUERY_URL_TEST = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/TXNSTATUS";
	
	public $PAYTM_REFUND_URL_PROD = "https://secure.paytm.in/oltp/HANDLER_INTERNAL/REFUND";
	public $PAYTM_REFUND_URL_TEST = "https://pguat.paytm.com/oltp/HANDLER_INTERNAL/REFUND";

	
	function pkcs5_pad_e($text, $blocksize) {
		$pad = $blocksize - (strlen($text) % $blocksize);
		return $text . str_repeat(chr($pad), $pad);
	}

	/**
     * Encrypt the merchant key 
     */
	function encrypt_e($input, $ky){
		$key = $ky;
		$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, 'cbc');
		$input = Mage::helper('paytm')->pkcs5_pad_e($input, $size);
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
		$iv = "@@@@&&&&####$$$$";
		mcrypt_generic_init($td, $key, $iv);
		$data = mcrypt_generic($td, $input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$data = base64_encode($data);
		return $data;
	}
	
	function pkcs5_unpad_e($text) {
		$pad = ord($text{strlen($text) - 1});
		if ($pad > strlen($text))
			return false;
		return substr($text, 0, -1 * $pad);
	}
	
	/**
     * Decrypt the merchant key 
     */
	function decrypt_e($crypt, $ky) {
		$crypt = base64_decode($crypt);
		$key = $ky;
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', '');
		$iv = "@@@@&&&&####$$$$";
		mcrypt_generic_init($td, $key, $iv);
		$decrypted_data = mdecrypt_generic($td, $crypt);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$decrypted_data = Mage::helper('paytm')->pkcs5_unpad_e($decrypted_data);
		$decrypted_data = rtrim($decrypted_data);
		return $decrypted_data;
	}

	function generateSalt_e($length) {
		$random = "";
		srand((double) microtime() * 1000000);

		$data = "AbcDE123IJKLMN67QRSTUVWXYZ";
		$data .= "aBCdefghijklmn123opq45rs67tuv89wxyz";
		$data .= "0FGH45OP89";

		for ($i = 0; $i < $length; $i++) {
			$random .= substr($data, (rand() % (strlen($data))), 1);
		}

		return $random;
	}

	function checkString_e($value) {
		$myvalue = ltrim($value);
		$myvalue = rtrim($myvalue);
		if ($myvalue == 'null')
			$myvalue = '';
		return $myvalue;
	}

	function getChecksumFromArray($arrayList, $key) {
		ksort($arrayList);
		$str = Mage::helper('paytm')->getArray2Str($arrayList);
		$salt = Mage::helper('paytm')->generateSalt_e(4);
		$finalString = $str . "|" . $salt;
		$hash = hash("sha256", $finalString);
		$hashString = $hash . $salt;
		$checksum = Mage::helper('paytm')->encrypt_e($hashString, $key);
		return $checksum;
	}

	/**
     * Verify Checksum
     */
	function verifychecksum_e($arrayList, $key, $checksumvalue) {
		$arrayList = Mage::helper('paytm')->removeCheckSumParam($arrayList);
		ksort($arrayList);
		$str = Mage::helper('paytm')->getArray2Str($arrayList);
		$paytm_hash = Mage::helper('paytm')->decrypt_e($checksumvalue, $key);
		$salt = substr($paytm_hash, -4);

		$finalString = $str . "|" . $salt;

		$website_hash = hash("sha256", $finalString);
		$website_hash .= $salt;

		$validFlag = "FALSE";
		if ($website_hash == $paytm_hash) {
			$validFlag = "TRUE";
		} else {
			$validFlag = "FALSE";
		}
		return $validFlag;
	}

	function getArray2Str($arrayList) {
		$paramStr = "";
		$flag = 1;
		foreach ($arrayList as $key => $value) {
			if ($flag) {
				$paramStr .= Mage::helper('paytm')->checkString_e($value);
				$flag = 0;
			} else {
				$paramStr .= "|" . Mage::helper('paytm')->checkString_e($value);
			}
		}
		return $paramStr;
	}

	function removeCheckSumParam($arrayList) {
		if (isset($arrayList["CHECKSUMHASH"])) {
			unset($arrayList["CHECKSUMHASH"]);
		}
		return $arrayList;
	}
}

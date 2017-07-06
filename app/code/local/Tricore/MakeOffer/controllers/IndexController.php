<?php

class Tricore_MakeOffer_IndexController extends Mage_Core_Controller_Front_Action
{

	/*
	* this is sendemail action for sending mail to customer
	*/
	public function sendemailAction()
	{ 

		/*
		*get the post data using params
		*/
		$params = $this->getRequest()->getParams();
		$emailTemplate  = Mage::getModel('core/email_template')
		->loadDefault('makeoffer_email_template');
		
		$emailTemplateVariables['Email'] = $params['email'];
		$mutimail = Mage::helper('makeoffer')->getCopyMail();
		$mymailArray = explode(',', $mutimail);
		
		
        /*
        *This veriable get the store config mail selection of makeoffer
        */
        $custommail = Mage::getStoreConfig('makeoffer/email/email_sender');
        $sender_name = Mage::getStoreConfig('trans_email/ident_'.$custommail.'/name');
        $fromEmail = Mage::getStoreConfig('trans_email/ident_'.$custommail.'/email');
        
        $emailTemplate->setSenderName($sender_name);
        $emailTemplate->setSenderEmail($fromEmail);       
        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
        $submittedform = $this->getRequest()->getPost(formdata);
        try{
        	$emailTemplate->send($params['email'], $emailTemplateVariables);	
        	$emailTemplate->send($mymailArray, $emailTemplateVariables);
        }catch(Exception $ex) {
        	Mage::getSingleton('core/session')->addError('Unable to send email');
        }

        /*
        *this code is use to set phtml data for ajax response
        */
        $block = $this->getLayout()->createBlock('core/template')->setTemplate('makeoffer/thankyou.phtml');
        $this->getResponse()->setBody($block->toHtml());
    }
    
}

?>

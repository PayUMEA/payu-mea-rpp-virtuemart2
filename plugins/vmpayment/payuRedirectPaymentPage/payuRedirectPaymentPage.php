<?php
/**
 * payuredirectPaymentPage.php
 * 
 * Copyright (c) 2012 PayU Payment Solutions (Pty) Ltd
 *
 * LICENSE:
 * 
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 * 
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 * 
 * Portions of this file contain code Copyright (C) 2004-2008 soeren - All rights reserved.
 * 
 * @author      Warren Roman
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version     1.0
 */

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVMPaymentPayuRedirectPaymentPage extends vmPSPlugin 
{
    // Instance of class
    public static $_this = false;

    function __construct(& $subject, $config) 
    {
    	parent::__construct($subject, $config);
    
    	$this->_loggable = true;
    	$this->tableFields = array_keys($this->getTableSQLFields());
    
    	$varsToPush = array(            
            'payuRedirectPaymentPage_systemToCall_production' => array('', 'int'),
            'payuRedirectPaymentPage_safekey' => array('', 'char'),
            'payuRedirectPaymentPage_username' => array('', 'char'),
            'payuRedirectPaymentPage_password' => array('', 'char'),
            
            'payuRedirectPaymentPage_transactionType' => array('PAYMENT', 'char'),
            'payuRedirectPaymentPage_paymentMethod' => array('CREDITCARD', 'char'),
            'payuRedirectPaymentPage_selectedCurrency' => array('ZA', 'char'),
            
            'payuRedirectPaymentPage_defaultOrderNumberPrepend' => array('', 'char'),
            'payuRedirectPaymentPage_returnURL' => array('', 'char'),
            'payuRedirectPaymentPage_cancelURL' => array('', 'char'),
            'payment_logos' => array('payu.png', 'char'),
            'status_pending' => array('', 'char'),
    	    'status_success' => array('', 'char'),
    	    'status_canceled' => array('', 'char'),
            'debug' => array('0', 'int'),
    	);  
        
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    function _getPaymentResponseHtml($payuData, $payment_name, $showContinueShopping = false) 
    {
        $html = '<table>' . "\n";
        foreach ($payuData as $key => $value) 
        {    	    
            $html .= $this->getHtmlRowBE($key, $value);    	    
    	}    
    	$html .= '</table>' . "\n";
    
        if($showContinueShopping === true) {
            $html .= '<br /><br /><br />';
            $html .= '<br /><br /><br />Click <a href="'.JRoute::_ ('index.php?option=com_virtuemart&view=cart').'">here</a> to continue shopping';
        }
        
    	return $html;
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {       
        return true;
    }
    
    protected function getVmPluginCreateTableSQL() 
    {
	   return $this->createTableSQL('Payment PayU Table');
    }

    function getTableSQLFields() 
    {
    	$SQLfields = array(
    	    'id' => ' tinyint(1) unsigned NOT NULL AUTO_INCREMENT ',
    	    'virtuemart_order_id' => ' int(11) UNSIGNED DEFAULT NULL',
    	    'order_number' => ' char(32) DEFAULT NULL',
    	    'virtuemart_paymentmethod_id' => ' mediumint(1) UNSIGNED DEFAULT NULL',
    	    'payment_name' => ' char(255) NOT NULL DEFAULT \'\' ',
    	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
    	    'payment_currency' => 'char(3) ',    	    
    	    'payuRedirectPaymentPage_setTransactionResponse' => ' varchar(255) DEFAULT NULL  ',
            'payuRedirectPaymentPage_setTransactionPaymentDate' => ' char(28) DEFAULT NULL  ',
    	    'payuRedirectPaymentPage_getTransactionResponse' => ' varchar(255)  DEFAULT NULL ',
            'payuRedirectPaymentPage_getTransactionPaymentDate' => ' char(28) DEFAULT NULL  ',
            'transaction_status' => ' char(28) DEFAULT NULL  ',
    	);
    	
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {
        
        require_once('library.payu/inc.joomla/config.virtuemart2.php');       
        require_once('library.payu/classes/class.PayuRedirectPaymentPage.php');   
        
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) 
        {
    	    return null;
        }
	
        if (!$this->selectedThisElement($method->payment_element)) 
        {
	       return false;
	    }
	
        $session = JFactory::getSession();
    	$return_context = $session->getId();
    	$this->_debug = $method->debug;
    	$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
    
    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    	if (!class_exists('VirtueMartModelCurrency'))
    	    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
    
    	//$usr = & JFactory::getUser();
    	$new_status = '';
    
    	$usrBT = $order['details']['BT'];
    	$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
        
        if ($method->payuRedirectPaymentPage_systemToCall_production) {            
			$authDetails = array(
                'safekey' => $method->payuRedirectPaymentPage_safekey,
                'username' => $method->payuRedirectPaymentPage_username,
                'password' => $method->payuRedirectPaymentPage_password,                
				'production' => true
            );   
        }
        else {
			$authDetails = array(
                'safekey' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_safekey'],
                'username' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_username'],
                'password' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_password'],
				'production' => false
                
            );                     
        }
        
        $MerchantReference = $order['details']['BT']->order_number;
        $virtuemart_order_id = $order['details']['BT']->virtuemart_order_id;
        
        // Creating Set Transaction Array
        $setTransactionSoapDataArray = array();
        $setTransactionSoapDataArray['TransactionType'] = $method->payuRedirectPaymentPage_transactionType;        
        
        // Creating Basket Array
        $basketArray = array();
        $basketArray['amountInCents'] = (int)$order['details']['BT']->order_total*100;
        $basketArray['description'] = $method->payuRedirectPaymentPage_defaultOrderNumberPrepend.$MerchantReference;
        $basketArray['currencyCode'] = $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_supportedCurrencies'];
        $setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('Basket' => $basketArray ));
        //$basketArray = null; unset($basketArray);
        
        // Creating Customer Array
        $customerSubmitArray = array();
        $customerSubmitArray['firstName'] = $order['details']['BT']->first_name;
        $customerSubmitArray['lastName'] = $order['details']['BT']->last_name;
        $customerSubmitArray['mobile'] = $order['details']['BT']->phone_1;
        $customerSubmitArray['email'] = $order['details']['BT']->email;
        $setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('Customer' => $customerSubmitArray ));
        $customerSubmitArray = null; unset($customerSubmitArray);
        
        //Creating Additional Information Array
        $additionalInformationArray = array();
        $additionalInformationArray['supportedPaymentMethods'] = $method->payuRedirectPaymentPage_paymentMethod;
        $additionalInformationArray['returnUrl'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id."&o_id={$order['details']['BT']->order_number}");        
        $additionalInformationArray['cancelUrl'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);
        //$additionalInformationArray['notifyUrl'] = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);        
        $additionalInformationArray['merchantReference'] = $MerchantReference;
        $setTransactionSoapDataArray = array_merge($setTransactionSoapDataArray, array('AdditionalInformation' => $additionalInformationArray ));
        $additionalInformationArray = null; unset($additionalInformationArray);
        
        //Creating a constructor array for RPP instantiation        
        $constructorArray = array();        
        $constructorArray['safeKey'] = $authDetails['safekey']; 
        $constructorArray['username'] = $authDetails['username'];
        $constructorArray['password'] = $authDetails['password'];
        $constructorArray['production'] = $authDetails['production'];
        $constructorArray['logEnable'] = false;              
        $constructorArray['extendedDebugEnable'] = true;          
        
        $thisErrorMessage = "Unable to contact payment gateway";
        $app = JFactory::getApplication ();
        
		try {
            $payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
            $setTransactionResponse = $payuRppInstance->doSetTransactionSoapCall($setTransactionSoapDataArray);
            
            if(isset($setTransactionResponse['redirectPaymentPageUrl'])) {                
                if (!class_exists('VirtueMartModelOrders'))
                    require_once( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    	    
                $message = 'Redirected to PayU for payment'."\r\n";            
                $message .= 'PayU Reference: ' . $setTransactionResponse['soapResponse']['payUReference'] . "\r\n";
                
                $order = array();
                $order['comments'] = $message;
                $order['virtuemart_order_id'] = $virtuemart_order_id;
                $order['customer_notified'] = 0;
                $order['order_status'] = $method->status_pending;
                $modelOrder = new VirtueMartModelOrders();
                $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);                
                
                if(isset($thisErrorMessage)) {
                    unset($thisErrorMessage);
                }
                
            }      
            else {
                $thisErrorMessage = "Invalid response from payment gateway";
            }
        }
        catch(Exception $e) {
            $thisErrorMessage = $e->getMessage();
        }       
        
        $redirectUrl = "";
        $redirectText = false;
        $soapResponse = "";
        if(!isset($thisErrorMessage)) {
            $redirectUrl = $setTransactionResponse['redirectPaymentPageUrl'];
            $soapResponse = $payuRppInstance->soapClientInstance->__getLastResponse();
        }
        else {
            $redirectUrl = JRoute::_ ('index.php?option=com_virtuemart&view=cart');
            $redirectText = JText::_ ("<span style='color:red'>Error: ".$thisErrorMessage."</span>");
            $soapResponse = "ERROR: ".$thisErrorMessage;
        }
        
        // Prepare data that should be stored in the database
    	$dbValues['order_number'] = $virtuemart_order_id;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
    	$dbValues['payment_name'] = $this->renderPluginName($method, $order);    	
        $dbValues['payment_order_total'] = $basketArray['amountInCents'];
        $dbValues['payment_currency'] = $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_supportedCurrencies'];                
        $dbValues['payuRedirectPaymentPage_setTransactionResponse'] = $soapResponse;        
        $dbValues['payuRedirectPaymentPage_setTransactionPaymentDate'] = date('Y-m-d H:i:s');
        $dbValues['payuRedirectPaymentPage_getTransactionResponse'] = '';
        $dbValues['payuRedirectPaymentPage_getTransactionPaymentDate'] = '';        
    	$this->storePSPluginInternalData($dbValues);
        
        $app->redirect ($redirectUrl, $redirectText);
        return false;
            	
    	// 	2 = don't delete the cart, don't send email and don't redirect
    	//return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html, $new_status);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) 
    {   
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) 
        {
    	    return null; // Another method was selected, do nothing
    	}
    	if (!$this->selectedThisElement($method->payment_element)) 
        {
    	    return false;
    	}
   	    
        $this->getPaymentCurrency($method);
    	$paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived(&$html) {
        
        require_once('library.payu/inc.joomla/config.virtuemart2.php');       
        require_once('library.payu/classes/class.PayuRedirectPaymentPage.php'); 
        
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
        $payuGetRequestData = JRequest::get('get');
        
        $vendorId = 0;
        
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
    	    return null; 
    	}
    	
        if (!$this->selectedThisElement($method->payment_element)) {
    	    return false;
    	}
        
        $errorMessage = "Invalid Gateway Reponse";   

		if(isset($payuGetRequestData['PayUReference']) && !empty($payuGetRequestData['PayUReference'])) {

            if ($method->payuRedirectPaymentPage_systemToCall_production) {            
				$authDetails = array(
					'safekey' => $method->payuRedirectPaymentPage_safekey,
					'username' => $method->payuRedirectPaymentPage_username,
					'password' => $method->payuRedirectPaymentPage_password,                
					'production' => true
				);   
			}
			else {
				$authDetails = array(
					'safekey' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_safekey'],
					'username' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_username'],
					'password' => $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_password'],
					'production' => false					
				);                     
			}
            //Creating get transaction soap data array
            $getTransactionSoapDataArray = array();                
            $getTransactionSoapDataArray['AdditionalInformation']['payUReference'] = $payuGetRequestData['PayUReference'];        

            $constructorArray = array();        
            $constructorArray['safeKey'] = $authDetails['safekey'];
            $constructorArray['username'] = $authDetails['username'];
            $constructorArray['password'] = $authDetails['password'];
            $constructorArray['production'] = $authDetails['production'];                
            $constructorArray['logEnable'] = false;
            $constructorArray['extendedDebugEnable'] = true;

            $transactionState = 'failure';
            
            try {
                $payuRppInstance = new PayuRedirectPaymentPage($constructorArray);
                $getTransactionResponse = $payuRppInstance->doGetTransactionSoapCall($getTransactionSoapDataArray); 
				
                $errorMessage = $getTransactionResponse['soapResponse']['displayMessage'];
                
                //Checking the response from the SOAP call to see if successfull
                if(isset($getTransactionResponse['soapResponse']['successful']) && ($getTransactionResponse['soapResponse']['successful']  === true)) {                    
                    if(isset($getTransactionResponse['soapResponse']['transactionType']) && (strtolower($getTransactionResponse['soapResponse']['transactionType']) == 'payment') ) {                    
                        if(isset($getTransactionResponse['soapResponse']['resultCode']) && (strtolower($getTransactionResponse['soapResponse']['resultCode']) == '00') ) {
                            $transactionState = "paymentSuccessfull";                                                        
                        }
                    }                    
                }
                else {
                    $errorMessage = $getTransactionResponse['soapResponse']['displayMessage'];
                }
            }
            catch(Exception $e) {
                $errorMessage = $e->getMessage();            
            } 
            
            //var_dump($getTransactionResponse);
            $orderNumber = $getTransactionResponse['soapResponse']['merchantReference'];
            $paymentDataArray = array();
            $showContinueShopping = false;
            if($transactionState == "paymentSuccessfull") {
                $new_status = $method->status_success;
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_payment_confirmation'] = '<span style="color:green">Payment Successful</span>';
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_order_number'] = $orderNumber;
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_payu_reference'] =$getTransactionResponse['soapResponse']['payUReference'];
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_gateway_reference'] = $getTransactionResponse['soapResponse']['paymentMethodsUsed']['gatewayReference'];
            }    
            else if($transactionState == "failure")
            {
                $new_status = $method->status_canceled;
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_payment_confirmation'] = '<span style="color:red">Payment Declined</span>';
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_order_number'] = $orderNumber;
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_payu_reference'] = $getTransactionResponse['soapResponse']['payUReference'];                
                $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_display_message'] = $getTransactionResponse['soapResponse']['displayMessage'];
                $showContinueShopping = true;
            }
            
            //var_dump($paymentDataArray);
            
            if (!class_exists('VirtueMartModelOrders')) {                
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
            }
           
            if($getTransactionResponse['soapResponse']['merchantReference']) {                
                $soapResponse = $payuRppInstance->soapClientInstance->__getLastResponse();
                $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($getTransactionResponse['soapResponse']['merchantReference']);                
                
                $db =& JFactory::getDBO();
                $query = "SELECT * FROM #__virtuemart_orders WHERE virtuemart_order_id =".$virtuemart_order_id;
                $db->setQuery($query);
                $payment = $db->loadObject();                
                $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);                
                
                // Prepare data that should be stored in t0he database
                $dbValues = array();
                $dbValues['virtuemart_order_id'] = $virtuemart_order_id;
                $dbValues['order_number'] = $getTransactionResponse['soapResponse']['merchantReference'];
                $dbValues['virtuemart_paymentmethod_id'] = $payment->virtuemart_paymentmethod_id;
                $dbValues['payment_name'] = $this->renderPluginName($method);   	
                $dbValues['payment_order_total'] = $getTransactionResponse['soapResponse']['basket']['amountInCents'];
                $dbValues['payment_currency'] = $payuVirtueMartConfig['PayuRedirectPaymentPage']['payuRedirectPaymentPage_supportedCurrencies'];                
                $dbValues['payuRedirectPaymentPage_setTransactionResponse'] = '';
                $dbValues['payuRedirectPaymentPage_setTransactionPaymentDate'] = '';
                $dbValues['payuRedirectPaymentPage_getTransactionResponse'] = $soapResponse;
                $dbValues['payuRedirectPaymentPage_getTransactionPaymentDate'] = date('Y-m-d H:i:s'); 
                $this->storePSPluginInternalData($dbValues);
                
                $payment_name = $this->renderPluginName($method);
                $html = $this->_getPaymentResponseHtml($paymentDataArray, $payment_name,$showContinueShopping);
                
                if (!class_exists('VirtueMartModelOrders'))
                        require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
                
                $session = JFactory::getSession();
                $return_context = $session->getId();
                
                $modelOrder = new VirtueMartModelOrders();
                if ($virtuemart_order_id && ($transactionState == "paymentSuccessfull") ){
                    
                    $order = array();
                    $order['order_status'] = $new_status;
                    $order['virtuemart_order_id'] = $virtuemart_order_id;
                    $order['customer_notified'] = 1;                    
                    $message = 'Payment Successful:'."\r\n";
                    $message .= 'Order Id: ' . $getTransactionResponse['soapResponse']['merchantReference'] . "\r\n";
                    $message .= 'PayU Reference: ' . $getTransactionResponse['soapResponse']['payUReference'] . "\r\n";                    
                    $message .= 'Gateway Reference: ' . $paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_gateway_reference'] . "\r\n";
                    $order['comments'] = JTExt::sprintf($message);
                    
                    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                    $this->emptyCart($return_context);
                }
                else {
                    $order = array();
                    $order['order_status'] = $new_status;
                    $order['virtuemart_order_id'] = $virtuemart_order_id;
                    $order['customer_notified'] = 0;
                    $message = 'Payment Declined'."\r\n";            
                    $message .= 'PayU Reference: ' . $getTransactionResponse['soapResponse']['payUReference'] . "\r\n";
                    $message .= 'Error message:' . $errorMessage. "\r\n";
                    $order['comments'] = JTExt::sprintf($message);
                    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);                    
                }
            }            
        }
		else {
			$paymentDataArray['PAYUREDIRECTPAYMENTPAGE_callback_display_message'] = '<span style="color:red">Invalid Response from Payment Gateway. Please contact Merchant</span>';
			$html = $this->_getPaymentResponseHtml($paymentDataArray, $payment_name);
		}
		
        return true;
    }

    function plgVmOnUserPaymentCancel() 
    {
    	if (!class_exists('VirtueMartModelOrders'))
    	    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    
    	$order_number = JRequest::getVar('on');
    	if (!$order_number)
    	    return false;
    	
        $db = JFactory::getDBO();
    	$query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename. " WHERE  `order_number`= '" . $order_number . "'";
    
    	$db->setQuery($query);
    	$virtuemart_order_id = $db->loadResult();
    
    	if (!$virtuemart_order_id) 
        {
    	    return null;
    	}
    	
        $this->handlePaymentUserCancel($virtuemart_order_id);
    	return true;
    }

    /*
     *   plgVmOnPaymentNotification() - This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
     * Return:
     * Parameters:
     *  None
     *  @author Valerie Isaksen
     */
    function plgVmOnPaymentNotification() 
    {
    	return true;
    }

    /**
     * Display stored payment data for an order
     * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) 
    {
        return '';
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author ValÃ©rie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) 
    {
	   return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author ValÃ©rie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) 
    {
	   return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) 
    {
	   return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) 
    {
	   return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) 
    {
	   return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) 
    {
	   $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) 
    {
	   return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk

      public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
      return null;
      }
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) 
    {
	   return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) 
    {
	   return $this->setOnTablePluginParams($name, $id, $table);
    }
}

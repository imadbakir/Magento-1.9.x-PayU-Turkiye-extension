<?php
header('Content-Type: text/html; charset=utf-8');
ini_set("mbstring.func_overload", 0);

class Payu_PayuCheckout_Model_Shared extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'payucheckout_shared';

    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;

    protected $_formBlockType = 'payucheckout/shared_form';
    protected $_paymentMethod = 'shared';


    protected $_order;


    public function cleanString($string)
    {

        $string_step1 = strip_tags($string);
        $string_step2 = nl2br($string_step1);
        $string_step3 = str_replace("<br />", "<br>", $string_step2);
        $cleaned_string = str_replace("\"", " inch", $string_step3);
        return $cleaned_string;
    }


    /**
     * Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }


    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {

        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }


    public function getCustomerId()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/customer_id');
    }

    public function getAccepteCurrency()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/currency');
    }


    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('payucheckout/shared/redirect');
    }

    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
        $mode = Mage::getStoreConfig('payment/payucheckout_shared/demo_mode');

        $billing = $this->getOrder()->getBillingAddress();
        $coFields = array();
        $items = $this->getOrder()->getAllItems();
        //$items = $this->getQuote()->getAllItems();
        //$tax_info = $this->getOrder()->getFullTaxInfo();

        $ORDER_PNAME = array();
        $ORDER_PGROUP = array();
        $ORDER_PCODE = array();
        $ORDER_PINFO = array();
        $ORDER_PRICE = array();
        $ORDER_QTY = array();
        $ORDER_VAT = array();
        $ORDER_PRICE_TYPE = array();
        if ($items) {
            $i = 0;
            foreach ($items as $item) {
                if ($item->getParentItem()) {
                    continue;
                }
                $ORDER_PNAME[$i] = $this->cleanString($item->getName());

                $_priceIncludingTax = Mage::helper('tax')->getPrice($item, $item->getPrice());
                $ORDER_PRICE[$i] = number_format($_priceIncludingTax, 2, '.', '');
                $ORDER_QTY[$i] = $item->getQtyOrdered();

                //$a = number_format(($_priceIncludingTax - $item->getPrice()), 2, '.', '');

                $taxPercent = $item->getTaxPercent();
                $taxPercent = number_format($taxPercent, 2, '.', '');

                //$a = $tax_info[$i]['percent'];;
                //$a = $taxPercent;

                $ORDER_VAT[$i] = $taxPercent;
                $ORDER_PRICE_TYPE[$i] = 'GROSS';
                $ORDER_PCODE[$i] = $this->cleanString($item->getSku());
                $ORDER_PINFO[$i] = $this->cleanString($item->getDescription());

                /*
                $coFields['c_prod_'.$i]            = $this->cleanString($item->getSku());
                $coFields['c_name_'.$i]            = $this->cleanString($item->getName());
                $coFields['c_description_'.$i]     = $this->cleanString($item->getDescription());
                $coFields['c_price_'.$i]           = number_format($item->getPrice(), 2, '.', '');
                */
                $i++;

            }
        }
        //print_r($ORDER_VAT);
        //print_r($ORDER_PRICE);exit;

        $turkish = array("ı", "ğ", "ü", "ş", "ö", "ç");//turkish letters
        $english = array("i", "g", "u", "s", "o", "c");//english cooridinators letters
        $request = '';
        foreach ($coFields as $k => $v) {
            $request .= '<' . $k . '>' . $v . '</' . $k . '>';
        }

        $key = Mage::getStoreConfig('payment/payucheckout_shared/key');
        $salt = Mage::getStoreConfig('payment/payucheckout_shared/salt');
        $debug_mode = Mage::getStoreConfig('payment/payucheckout_shared/debug_mode');

        $orderId = $this->getOrder()->getRealOrderId();
        // $txnid = $orderId+370000;

        $txnid = $orderId;

        $coFields['MERCHANT'] = $key;
        $coFields['ORDER_REF'] = $txnid;
        $coFields['ORDER_DATE'] = date("Y-m-d H:i:s");

        //$coFields['ORDER_PNAME'] = preg_replace('/\s+/', ' ',str_replace($turkish, $english, $ORDER_PNAME));
        $coFields['ORDER_PNAME'] = $ORDER_PNAME;
        $coFields['ORDER_PRICE'] = $ORDER_PRICE;
        $coFields['ORDER_PINFO'] = $ORDER_PINFO;
        $coFields['ORDER_PCODE'] = $ORDER_PCODE;

        $coFields['ORDER_PRICE_TYPE'] = $ORDER_PRICE_TYPE;
        $coFields['ORDER_QTY'] = $ORDER_QTY;
        $coFields['ORDER_VAT'] = $ORDER_VAT;
        $coFields['ORDER_SHIPPING'] = number_format($this->getOrder()->getBaseShippingAmount(), 0, '', '');
        $coFields['PRICES_CURRENCY'] = 'TRY';
        $coFields['DISCOUNT'] = 0;
        $coFields['INSTALLMENT_OPTIONS'] = '';

        $coFields['BILL_FNAME'] = $billing->getFirstname();
        $coFields['BILL_LNAME'] = $billing->getLastname();
        $coFields['DESTINATION_CITY'] = $billing->getCity();
        $coFields['DESTINATION_STATE'] = $billing->getRegion();
        $coFields['DESTINATION_COUNTRY'] = $billing->getCountry();
        $coFields['Zipcode'] = $billing->getPostcode();
        $coFields['BILL_EMAIL'] = $this->getOrder()->getCustomerEmail();
        $coFields['BILL_PHONE'] = $billing->getTelephone();


        $coFields['DEBUG'] = 0;
        $coFields['LANGUAGE'] = 'TR';
        $coFields['AUTOMODE'] = '1';
        if (strpos(Mage::getBaseUrl(), 'https') !== false) {
            $coFields['BACK_REF'] = Mage::getBaseUrl() . 'payucheckout/shared/success/id/' . $this->getOrder()->getRealOrderId() . '/';
        } else {
            $coFields['BACK_REF'] = str_replace('http', 'https', Mage::getBaseUrl()) . 'payucheckout/shared/success/id/' . $this->getOrder()->getRealOrderId() . '/';
        }
        $coFields['furl'] = Mage::getBaseUrl() . 'payucheckout/shared/failure/';
        $coFields['curl'] = Mage::getBaseUrl() . 'payucheckout/shared/canceled/id/' . $this->getOrder()->getRealOrderId();


        $coFields['PAY_METHOD'] = 'CCVISAMC';
        $debugId = '';

        if ($debug_mode == 1) {

            $requestInfo = $key . '|' . $coFields['ORDER_REF'] . '|' . $coFields['ORDER_PRICE'] . '|' .
                $coFields['ORDER_PINFO'] . '|' . $coFields['BILL_FNAME'] . '|' . $coFields['BILL_EMAIL'] . '|' . $debugId . '||||||||||' . $salt;
            $debug = Mage::getModel('payucheckout/api_debug')
                ->setRequestBody($requestInfo)
                ->save();

            $debugId = $debug->getId();

            $coFields['udf1'] = $debugId;
            $coFields['ORDER_HASH'] = hash('sha512', $key . '|' . $coFields['ORDER_REF'] . '|' . $coFields['ORDER_PRICE'] . '|' .
                $coFields['ORDER_PINFO'] . '|' . $coFields['BILL_FNAME'] . '|' . $coFields['BILL_EMAIL'] . '|' . $debugId . '||||||||||' . $salt);
        } else {
            $keys = [
                'MERCHANT',
                'ORDER_REF',
                'ORDER_DATE',
                'ORDER_PNAME',
                'ORDER_PCODE',
                'ORDER_PINFO',
                'ORDER_PRICE',
                'ORDER_QTY',
                'ORDER_VAT',
                'ORDER_SHIPPING',
                'PRICES_CURRENCY',
                'DISCOUNT',
                'DESTINATION_CITY',
                'DESTINATION_STATE',
                'DESTINATION_COUNTRY',
                'PAY_METHOD',
                'ORDER_PRICE_TYPE',
                'INSTALLMENT_OPTIONS',
            ];


            $hashstring = '';
            for ($i = 0; $i < sizeOf($keys); $i++) {
                if (is_array($coFields[$keys[$i]])) {
                    for ($v = 0; $v < sizeOf($ORDER_PNAME); $v++) {
                        $addHash = isset($coFields[$keys[$i]][$v]) ? strlen($coFields[$keys[$i]][$v]) . $coFields[$keys[$i]][$v] : '0' . '';
                        $hashstring .= $addHash;
                        //echo $keys[$i].'-'.$addHash."</br>";
                    }
                } else {
                    $addHash = strlen($coFields[$keys[$i]]) . $coFields[$keys[$i]];
                    $hashstring .= $addHash;
                    //echo $keys[$i].'-'.$addHash."</br>";
                }

            }
            $coFields['ORDER_HASH'] = hash_hmac('md5', $hashstring, $salt);
        }

        /*echo mb_strlen('Moleskine sert kapak xs boy (6.5x10.5 cm) duz defter').'='.strlen('Moleskine sert kapak xs boy (6.5x10.5 cm) düz defter').'=';
        echo $hashstring;exit;*/
        return $coFields;
    }

    /**
     * Get url of Payu payment
     *
     * @return string
     */
    public function getPayuCheckoutSharedUrl()
    {
        $mode = Mage::getStoreConfig('payment/payucheckout_shared/demo_mode');

        $url = 'https://secure.payu.com.tr/order/lu.php';

        if ($mode == '') {
            $url = 'https://secure.payu.com.tr/order/lu.php';
        }

        return $url;
    }


    /**
     * Get debug flag
     *
     * @return string
     */
    public function getDebug()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/debug_flag');
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    /**
     * parse response POST array from gateway page and return payment status
     *
     * @return bool
     */
    public function parseResponse()
    {

        return true;

    }

    /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }


    public function getResponseOperation($response)
    {

        $order = Mage::getModel('sales/order');
        $debug_mode = Mage::getStoreConfig('payment/payucheckout_shared/debug_mode');
        $key = Mage::getStoreConfig('payment/payucheckout_shared/key');
        $salt = Mage::getStoreConfig('payment/payucheckout_shared/salt');
        if (isset($response['ctrl'])) {
            $txnid = $response['id'];
            // $orderid=$txnid-370000;
            $orderid = $txnid;

            if (strpos(Mage::getBaseUrl(), 'https') !== false) {
                $link = Mage::getBaseUrl() . 'payucheckout/shared/success/id/' . $response['id'] . '/';
            } else {
                $link = str_replace('http', 'https', Mage::getBaseUrl()) . 'payucheckout/shared/success/id/' . $response['id'] . '/';
            }
            $hashstring = mb_strlen($link) . $link;
            $salt = Mage::getStoreConfig('payment/payucheckout_shared/salt');
            $ctrl = $response['ctrl'];
            $hash = hash_hmac('md5', $hashstring, $salt);
            if ($ctrl == $hash) {
                $status = 'success';
                $order->loadByIncrementId($orderid);
                $billing = $order->getBillingAddress();
                $amount = $order->getGrandTotal();
                $productinfo = 'Ürün Bilgileri';
                $firstname = $billing->getFirstname();
                $email = $order->getCustomerEmail();
                $keyString = '';
                /*foreach($order->getAllVisibleItems() as $value) {
				  echo $value->getName();
				  echo $value->getSku();
				  echo $value->getPrice();
				  echo $value->getQtyOrdered();
				}*/

                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                $order->save();
                $order->sendNewOrderEmail();


            } else {
                $order->loadByIncrementId($orderid);
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                // Invento updated
                $this->updateInventory($orderid);

                $order->cancel()->save();
            }

            if ($response['status'] == 'failure') {
                $order->loadByIncrementId($orderid);
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
                // Invento updated
                $this->updateInventory($orderid);

                $order->cancel()->save();


            } else if ($response['status'] == 'pending') {
                $order->loadByIncrementId($orderid);
                $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
                // Invento updated
                $this->updateInventory($orderid);
                $order->cancel()->save();


            }

        } else {

            /*$order->loadByIncrementId($response['id']);
            $order->setState(Mage_Sales_Model_Order::STATE_NEW, true);
            // Invento updated
            $order_id = $response['id'];
            $this->updateInventory($order_id);

            $order->cancel()->save();*/


        }
    }

    public function updateInventory($order_id)
    {

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

}
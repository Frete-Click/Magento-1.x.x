<?php
class Frete_Click_Model_Carrier extends Frete_Click_Model_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'freteclick';

    protected $_allowedMethods = array();
    
    /**
     * @var Mage_Shipping_Model_Rate_Result
     */
    protected $_result;

    /**
     * @var Mage_Shipping_Model_Rate_Request
     */
    protected $_rawRequest;

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Interface::getAllowedMethods()
     */
    public function getAllowedMethods()
    {
        return $this->_allowedMethods;
    }
    
    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::proccessAdditionalValidation()
     */
    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::log('Frete_Click_Model_Carrier::proccessAdditionalValidation');
        $requestPostcode = Mage::helper('freteclick')->formatZip($request->getDestPostcode());
        $address = Mage::getModel($this->getConfigData('address_model'))->load($requestPostcode);

        if (!$this->isValid($address)) {
            return false;
        }

        if (!$this->validateAllowedZips($requestPostcode)) {
            return false;
        }

        $this->setDestAddress($address);
        return $this;
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::collectRates()
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::log('Frete_Click_Model_Carrier::collectRates');
        $this->_rawRequest = $request;
        $this->_result = $this->_getQuotes();
        $this->_updateFreeMethodQuote($request);

        return $this->_result;
    }

    protected function _setFreeMethodRequest($freeMethod)
    {
        $this->_isFreeRequest = true;
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::getMethodPrice()
     */
    public function getMethodPrice($cost, $method = '')
    {
        $config = $this->getConfigData($this->_freeMethod);
        $freeMethods = explode(',', $config);

        return in_array($method, $freeMethods)
            && $this->getConfigFlag('free_shipping_enable')
            && $this->getConfigData('free_shipping_subtotal') <= $this->_rawRequest->getBaseSubtotalInclTax()
            ? '0.00'
            : $this->getFinalPriceWithHandlingFee($cost);
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::_updateFreeMethodQuote()
     */
    protected function _updateFreeMethodQuote($request)
    {
        if ($request->getFreeMethodWeight() == $request->getPackageWeight() || !$request->hasFreeMethodWeight()) {
            return;
        }

        $freeMethods = $this->getConfigData($this->_freeMethod);
        if (!$freeMethods) {
            return;
        }
        $freeRates = array();
        $freeMethods = explode(',', $freeMethods);

        if (is_object($this->_result) && !empty($freeMethods)) {
            foreach ($this->_result->getAllRates() as $id=>$item) {
                if (in_array($item->getMethod(), $freeMethods)) {
                    $freeRates[$item->getMethod()] = $id;
                }
            }
        }

        if (empty($freeRates)) {
            return;
        }
        $price = array();
        if ($request->getFreeMethodWeight() > 0) {
            $this->_setFreeMethodRequest($freeMethods);

            $result = $this->_getQuotes();
            if ($result && ($rates = $result->getAllRates()) && count($rates)>0) {
                if ((count($rates) == 1)
                    && ($rates[0] instanceof Mage_Shipping_Model_Rate_Result_Method)
                    && ($id = $freeRates[$rates[0]->getMethod()])
                ) {
                    $price[$id] = $rates[0]->getPrice();
                }
                if (count($rates) > 1) {
                    foreach ($rates as $rate) {
                        if ($rate instanceof Mage_Shipping_Model_Rate_Result_Method
                            && in_array($rate->getMethod(), $freeMethods)
                            && ($id = $freeRates[$rate->getMethod()])
                        ) {
                            $price[$id] = $rate->getPrice();
                        }
                    }
                }
            }
        } else {
            /**
             * if we can apply free shipping for all order we should force price
             * to $0.00 for shipping with out sending second request to carrier
             */
            foreach ($freeRates as $id) {
                $price[$id] = 0;
            }
        }

        /**
         * if we did not get our free shipping method in response we must use its old price
         */
        if (!empty($price)) {
            foreach ($freeRates as $id) {
                $this->_result->getRateById($id)->setPrice($price[$id]);
            }
        }
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::_getQuotes()
     */
    protected function _getQuotes()
    {
        Mage::log('Frete_Click_Model_Carrier::_getQuotes');
        $rateResult = Mage::getModel('shipping/rate_result');
        $quotes = $this->getQuotes($this->_rawRequest);

        foreach ($quotes as $quote) {
            if (empty($quote->getPrice())) {
                Mage::log('Empty price for ' . $quote->getMethod());
                continue;
            }
            if (!$quote->hasError()) {
                $method = Mage::getModel('shipping/rate_result_method');
                $method->setCarrier($this->getCarrierCode());
                $method->setCarrierTitle($this->getConfigData('title'));
                $method->setMethod($quote->getMethod());
                $method->setMethodTitle($this->getMethodTitle($quote));
                $method->setPrice($this->getMethodPrice($quote->getPrice(), $quote->getMethod()));
                $method->setCost($quote->getPrice());
            } else {
                Mage::logException(Mage::exception('Mage_Core', $quote->getError()));
                $method = Mage::getModel('shipping/rate_result_error');
                $method->setCarrier($this->getCarrierCode());
                $method->setErrorMessage($this->getConfigData('specificerrmsg'));
                $method->setErrorMessage($quote->getError());
            }

            if ($quote->getQuoteId()) {
                $this->_getSession()->setFreteClickOrderId($quote->getQuoteId());
            }
            
            $rateResult->append($method);
        }
        
        return $rateResult;
    }
}

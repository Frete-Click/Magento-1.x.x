<?php
class FreteClick_Shipping_Model_Carrier extends FreteClick_Shipping_Model_Abstract
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
        Mage::log('FreteClick_Shipping_Model_Carrier::proccessAdditionalValidation');
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
        return $this->getConfigFlag('free_shipping_enable')
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

        if ($request->getFreeMethodWeight() > 0) {
            $this->_setFreeMethodRequest(true);
            $result = $this->_getQuotes();
            $this->_result = $result;
        } else {
            /**
             * if we can apply free shipping for all order we should force price
             * to $0.00 for shipping with out sending second request to carrier
             */
            Mage::log('Save request. Setting zero for all methods.');
            $singleResult = Mage::getModel('shipping/rate_result');
            $rates = $this->_result->getAllRates();

            if ($rate = array_shift($rates)) {
                $rate->setPrice(0);
                $rate->setMethodTitle(__('Free Shipping'));
                $singleResult->append($rate);
            }

            $this->_result = $singleResult;
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

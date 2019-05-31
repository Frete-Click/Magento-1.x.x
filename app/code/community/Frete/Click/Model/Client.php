<?php
class Frete_Click_Model_Client extends Varien_Http_Client
{

    /**
     * @var Mage_Shipping_Model_Rate_Request
     */
    protected $_rateRequest = null;

    protected function _getParsedData($obj)
    {
        $collection = array();
        
        if ($obj->response->data->quote != null) {
            foreach ($obj->response->data->quote as $quote) {
                $collection[] = array(
                    'method' => $quote->{'quote-id'},
                    'carrier_alias' => $quote->{'carrier-alias'},
                    'price' => $quote->total,
                    'deadline' => $quote->deadline,
                );
            }
        }

        return $collection;
    }

    /**
     * @throws Exception
     * @return mixed
     */
    public function getQuotes()
    {
        $quotes = array();

        try {
            $body = $this->request('POST')->getBody();
            Mage::log($this->getLastRequest());
            Mage::log($body);
            $data = Mage::helper('core')->jsonDecode($body, 0);
            if ($quotes = $this->_getParsedData($data)) {
                Mage::log(print_r($data, true));
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return false;
        }

        return $quotes;
    }
}

<?php
class Frete_Click_Model_Client extends Varien_Http_Client
{
    protected function _getParsedData($obj)
    {
        $collection = array();
        
        if (!empty($obj->response) && !empty($obj->response->data) && !empty($obj->response->data->quote)) {
            foreach ($obj->response->data->quote as $quote) {
                $collection[] = array(
                    'quote_id'      => $quote->{'order-id'},
                    'method'        => $quote->{'quote-id'},
                    'carrier_alias' => $quote->{'carrier-alias'},
                    'price'         => $quote->total,
                    'deadline'      => $quote->deadline,
                    'warning'       => $quote->{'delivery-restricted'},
                    'active'        => ($quote->{'carrier-active'} == 'carrier-enabled'),
                    'logo'          => $quote->{'carrier-logo'},
                );
            }
        }

        return $collection;
    }

    /**
     * @return array
     */
    public function getQuotes(Mage_Shipping_Model_Rate_Request $request)
    {
        $quotes = array();
        $hash = md5(http_build_query($this->paramsPost));
        $body = $request->getSession()->getData("freteclick{$hash}");
        if (empty($body)) {
            $body = $this->request('POST')->getBody();
            $request->getSession()->setData("freteclick{$hash}", $body);
            Mage::log($this->getLastRequest());
            Mage::log('Body:');
        } else {
            Mage::log('Session request body:');
        }

        Mage::log($body);
        $data = Mage::helper('core')->jsonDecode($body, 0);
        $quotes = $this->_getParsedData($data);

        return $quotes;
    }
}

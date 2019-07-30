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
        Mage::log('Frete_Click_Model_Client::getQuotes');
        $quotes = array();
        $hash = md5(http_build_query($this->paramsPost));
        $body = $request->getSession()->getData("freteclick{$hash}");
        
        if (empty($body)) {
            $ws = curl_init();
            curl_setopt($ws, CURLOPT_URL, $this->getUri(true));
            curl_setopt($ws, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ws, CURLOPT_POST, true);
            curl_setopt($ws, CURLOPT_POSTFIELDS, http_build_query($this->paramsPost));
            $body = curl_exec($ws);
            curl_close($ws);
        } else {
            Mage::log('Session request');
        }

        $data = Mage::helper('core')->jsonDecode($body, 0);
        $quotes = $this->_getParsedData($data);

        if (empty($quotes) || empty($body)) {
            $request->getSession()->unsetData("freteclick{$hash}");
        } else {
            $request->getSession()->setData("freteclick{$hash}", $body);
        }

        return $quotes;
    }
}

<?php
class FreteClick_Shipping_Model_Client extends Varien_Http_Client
{
    protected function _getParsedData($obj)
    {
        $collection = array();

        if (!empty($obj->response->data) && !empty($obj->response->data->order) && !empty($obj->response->data->order->quotes)) {
            foreach ($obj->response->data->order->quotes as $key => $results) {

                $quote = (array) $results;

                $fixMethodCode = Zend_Filter::filterStatic($quote['carrier']->alias, 'Alnum');
                $collection[] = array(
                    'quote_id'      => $quote['id'],
                    'method'        => $fixMethodCode,
                    'carrier_alias' => $quote['carrier']->alias,
                    'price'         => $quote['total'],
                    'deadline'      => $quote['deliveryDeadline'],
                    //'warning'       => $quote['delivery-restricted'],
                    'active'        => $quote['carrier']->enabled,
                    'logo'          => $quote['carrier']->image,
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
        Mage::log('FreteClick_Shipping_Model_Client::getQuotes');
        $quotes = array();

        $hash = md5(http_build_query($this->paramsPost));
        $body = $request->getSession()->getData("freteclick{$hash}");

        $headers = array(
            'api-token:' . $this->headers['api-token'][1],
            'Content-Type:application/json'
        );

        if (empty($body)) {
            $ws = curl_init();            
            curl_setopt($ws, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ws, CURLOPT_POSTFIELDS, json_encode( $this->paramsPost ));            
            curl_setopt($ws, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ws, CURLOPT_POST, true);
            curl_setopt($ws, CURLOPT_URL, $this->getUri());
            $body = curl_exec($ws);

            curl_close($ws);
        } else {
            Mage::log('Session request');
        }

        //$data = Mage::helper('core')->jsonDecode($body, 0);
        $quotes = $this->_getParsedData(json_decode( $body ));

        if (empty($quotes) || empty($body)) {
            $request->getSession()->unsetData("freteclick{$hash}");
        } else {
            $request->getSession()->setData("freteclick{$hash}", $body);
        }

        return $quotes;
    }
}

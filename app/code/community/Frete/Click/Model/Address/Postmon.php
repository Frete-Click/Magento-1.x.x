<?php
class Frete_Click_Model_Address_Postmon extends Varien_Object
{

    const DEFAULT_COUNTRY = 'Brasil';

    public function parseData($data)
    {
        try {
            if (empty($data->cep) || empty($data->cidade) || empty($data->estado)) {
                throw new Exception('Address unavailable');
            }

            $this->setPostcode($data->cep);
            $this->setCity($data->cidade);
            $this->setRegion($data->estado);

            if (empty($data->logradouro)) {
                $this->setStreet('logradouro');
            } else {
                $this->setStreet($data->logradouro);
            }

            if (empty($data->numero)) {
                $this->setNumber('1');
            } else {
                $this->setNumber($data->numero);
            }

            if (empty($data->bairro)) {
                $this->setDistrict('bairro');
            } else {
                $this->setDistrict($data->bairro);
            }

            if (empty($data->complemento)) {
                $this->setAdditionalInfo('');
            } else {
                $this->setAdditionalInfo($data->complemento);
            }

            $this->setCountry(self::DEFAULT_COUNTRY);
        } catch(Exception $e) {
            $this->setError($e->getMessage());
        }

        return $this;
    }
            
    /**
     * @throws Exception
     * @return boolean
     */
    public function load($postcode)
    {
        try {
            $ws = curl_init();
            curl_setopt($ws,CURLOPT_URL,"https://api.postmon.com.br/v1/cep/{$postcode}");
            curl_setopt($ws, CURLOPT_RETURNTRANSFER, true);
            $body = curl_exec($ws);
            curl_close($ws);
            Mage::log('Postmon response: '.$body);
            $data = Mage::helper('core')->jsonDecode($body, 0);
            
            if ($this->parseData($data)->hasError()) {
                return false;
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage());
            return false;
        }

        return $this;
    }
}

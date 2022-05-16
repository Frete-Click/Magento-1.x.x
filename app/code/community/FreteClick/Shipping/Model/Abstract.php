<?php
abstract class FreteClick_Shipping_Model_Abstract extends Mage_Shipping_Model_Carrier_Abstract
{
    const DEFAULT_API_ORDER = 'order';
    const DEFAULT_STORE_NAME = 'Magento Demo Store';
    const DEFAULT_STORE_PHONE = '1140000000';
    const DEFAULT_STORE_EMAIL = 'noreply@example.com';
    const DEFAULT_STORE_STREET = 'Street 1';
    const DEFAULT_STORE_NUMBER = '1';
    const DEFAULT_STORE_DISTRICT = 'District';

    /**
     * Crossdocking max value for all itens in cart
     * 
     * @var int
     */
    protected $_crossdocking = null;

    /**
     * @var boolean
     */
    protected $_isFreeRequest;
    
    /**
     * @return Mage_Checkout_Model_Session|Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton(Mage::app()->getStore()->isAdmin() ? 'adminhtml/session_quote' : 'checkout/session');
    }

    /**
     * Retrieve all visible items from request
     *
     * @param Mage_Shipping_Model_Rate_Request $request Mage request
     *
     * @return array
     */
    protected function _getRequestItems(Mage_Shipping_Model_Rate_Request $request)
    {
        $allItems = $request->getAllItems();
        $items = array();
    
        foreach ( $allItems as $item ) {
            if ( !$item->getParentItemId() ) {
                $items[] = $item;
            }
        }
    
        $items = $this->_loadBundleChildren($items);
    
        return $items;
    }
    
    /**
     * Filter visible and bundle children products.
     *
     * @param array $items Product Items
     *
     * @return array
     */
    protected function _loadBundleChildren($items)
    {
        $visibleAndBundleChildren = array();
        /* @var $item Mage_Sales_Model_Quote_Item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            $isBundle = ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);
            if ($isBundle) {
                /* @var $child Mage_Sales_Model_Quote_Item */
                foreach ($item->getChildren() as $child) {
                    $visibleAndBundleChildren[] = $child;
                }
            } else {
                $visibleAndBundleChildren[] = $item;
            }
        }
        return $visibleAndBundleChildren;
    }

    /**
     * Retrieves the simple product attribute
     * 
     * @param Mage_Catalog_Model_Product $product Catalog Product
     * @param string $attribute Attribute Code
     * 
     * @return mixed(string|int|float)
     */
    protected function _getProductAttribute($product, $attribute)
    {
        $type = $product->getTypeInstance(true);
        if ($type->getProduct($product)->hasCustomOptions() &&
            ($simpleProductOption = $type->getProduct($product)->getCustomOption('simple_product'))
        ) {
            $simpleProduct = $simpleProductOption->getProduct($product);
            if ($simpleProduct) {
                return $this->_getProductAttribute($simpleProduct, $attribute);
            }
        }
        return $type->getProduct($product)->getData($attribute);
    }
    
    /**
     * Retrieve the name of the most common category in cart
     * 
     * @return string
     */
    protected function _getMainCategory()
    {
        $ids = array_count_values($this->_getCategoryIds());
        sort($ids);
        $id = array_pop($ids);
        if ($category = Mage::getModel('catalog/category')->load($id)) {
            return $category->getName();
        }

        return Mage::helper('freteclick')->__('Mixed');
    }

    protected function _getProductPackage()
    {
        $package = array();
        $sizeFactor = (float)$this->getConfigData('size_factor');
        $weightFactor = (float)$this->getConfigData('weight_factor');

        foreach ($this->getItems() as $item) {
            $totalQty = is_numeric($item->getTotalQty()) ? $item->getTotalQty() : $item->getQty();

            if ($this->_isFreeRequest && ($freeQty = (int)$item->getFreeShipping())) {
                Mage::log(__('Free Item: %s (qty: %s | freeQty: %s)', $item->getName(), $totalQty, $freeQty));

                if (!is_numeric($freeQty) || $totalQty <= $freeQty) {
                    continue;
                }
                
                $totalQty -= $freeQty;
            }

            if ($_product = $item->getProduct()) {
                $_product->load($_product->getId());
                $height = $this->_getProductAttribute($_product, $this->getConfigData('attribute_height'));
                $width = $this->_getProductAttribute($_product, $this->getConfigData('attribute_width'));
                $depth = $this->_getProductAttribute($_product, $this->getConfigData('attribute_length'));
                $height /= $sizeFactor;
                $width  /= $sizeFactor;
                $depth  /= $sizeFactor;
                $weigth = $item->getWeight(); // $weightFactor;
                $package[] = array(
                    'qtd'       => $totalQty,
                    'weight'    => $weigth,  //Mage::helper('freteclick')->formatAmount($weigth, 2, '.', ''),
                    'height'    => $height, //Mage::helper('freteclick')->formatAmount($height, 2, '.', ''),
                    'width'     => $width, //Mage::helper('freteclick')->formatAmount($width, 2, '.', ''),
                    'depth'     => $depth, //Mage::helper('freteclick')->formatAmount($depth, 2, '.', '')
                );
            }
        }

        return $package;
    }

    protected function _getStorePostcode()
    {
        $postcode = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
        $postcode = Mage::helper('freteclick')->formatZip($postcode);
        return $postcode;
    }

    protected function _getStoreName()
    {
        $name = Mage::getStoreConfig('general/store_information/name', $this->getStore());
        if (empty($name)) {
            $name = self::DEFAULT_STORE_NAME;
        }

        return $name;
    }

    protected function _getStorePhone()
    {
        $phone = Mage::getStoreConfig('general/store_information/phone', $this->getStore());
        if (empty($phone)) {
            $phone = self::DEFAULT_STORE_PHONE;
        }

        return $phone;
    }

    protected function _getStoreEmail()
    {
        $email = Mage::getStoreConfig('trans_email/ident_general/email', $this->getStore());
        if (empty($email)) {
            $email = self::DEFAULT_STORE_EMAIL;
        }

        return $email;
    }

    protected function _getStoreStreet()
    {
        $street = Mage::getStoreConfig('shipping/origin/street_line1', $this->getStore());
        if (empty($street)) {
            $street = self::DEFAULT_STORE_STREET;
        }

        return $street;
    }

    protected function _getStoreDistrict()
    {
        $district = Mage::getStoreConfig('shipping/origin/street_line2', $this->getStore());
        if (empty($district)) {
            $district = self::DEFAULT_STORE_DISTRICT;
        }

        return $district;
    }

    protected function _getStoreCity()
    {
        $city = Mage::getStoreConfig('shipping/origin/city', $this->getStore());
        return $city;
    }

    protected function _getStoreRegion()
    {
        $regionId = Mage::getStoreConfig('shipping/origin/region_id', $this->getStore());
        $region = Mage::getModel('directory/region')->load($regionId)->getName();
        return $region;
    }

    protected function _getStoreCountry()
    {
        $countryId = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
        $country = Mage::getModel('directory/country')->load($countryId)->getName();
        return $country;
    }

    protected function _getStoreStreetNumber()
    {
        $number = self::DEFAULT_STORE_NUMBER;
        $street = explode(',', $this->_getStoreStreet());
        $last = array_pop($street);
        if (!empty($last) && is_numeric($last)) {
            $number = $last;
        }

        return $number;
    }

    protected function _getStoreAdditionalInfo()
    {
        return '';
    }

    protected function _getCategoryIds()
    {
        $categoryIds = array();
        foreach ($this->getItems() as $item) {
            $categoryIds = array_merge($categoryIds, $item->getProduct()->getCategoryIds());
        }

        return $categoryIds;
    }

    protected function _getCrossdocking()
    {
        if (empty($this->_crossdocking)) {
            $crossdocking = 0;
            $attribute = $this->getConfigData('attribute_crossdocking');

            if (!empty($attribute)) {
                foreach ($this->getItems() as $item) {
                    $crossdocking = max(
                        $crossdocking,
                        (int) $this->_getProductAttribute($item->getProduct(), $attribute)
                    );
                }
            }

            $this->_crossdocking = $crossdocking;
        }

        return $this->_crossdocking;
    }

    protected function _getCollectionFilter(Varien_Data_Collection $collection)
    {
        if ($collection->count() > 0 && $this->getConfigFlag('fast_and_cheap_filter')) {
            $bestPriceItem = $collection->getFirstItem();
            $fastDeliveryItem = $collection->getFirstItem();

            foreach ($collection as $item) {
                if ($item->getPrice() < $bestPriceItem->getPrice()) {
                    $bestPriceItem = $item;
                }

                if ($item->getDeadline() < $fastDeliveryItem->getDeadline()) {
                    $fastDeliveryItem = $item;
                }
            }

            $fastDeliveryItem->setCarrierAlias(Mage::helper('freteclick')->__('Fastest Shipping'));
            $collection = new Varien_Data_Collection();
            $collection->addItem($fastDeliveryItem);

            if ($bestPriceItem != $fastDeliveryItem) {
                $bestPriceItem->setCarrierAlias(Mage::helper('freteclick')->__('Cheapest Shipping'));
                $collection->addItem($bestPriceItem);
            }
        }

        return $collection;
    }

    /**
     * Postal code validation
     * Store owner can configure multiple zip ranges for validity.
     * @author Rafael Patro <rafaelpatro@gmail.com>
     */
    public function validateAllowedZips($postcode)
    {
        $output = true;
        
        if ($allowedZips = $this->getConfigData('allowed_zips')) {
            $allowedZips = unserialize($allowedZips);
            
            if (is_array($allowedZips) && !empty($allowedZips)) {
                $output = false;
                $postcode = Mage::helper('freteclick')->formatZip($postcode);
                
                foreach ($allowedZips as $zip) {
                    $isValid = ((int)$zip['from'] <= (int)$postcode);
                    $isValid &= ((int)$zip['to'] >= (int)$postcode);
                    if ($isValid) {
                        $output = true;
                        break;
                    }
                }
            }
        }

        return $output;
    }

    public function isValid($address)
    {
        if (empty($address) || empty($address->getStreet()) || empty($address->getDistrict()) || empty($address->getCity())
            || empty($address->getRegion()) || empty($address->getCountry())) {
            Mage::log('Invalid address');
            return false;
        }

        return true;
    }
    
    public function getMethodTitle($item)
    {
        $name = $item->getCarrierAlias();
        $companyTime = $item->getDeadline();
        $finalTime = $companyTime + $this->_getCrossdocking() + (int)$this->getConfigData('crossdocking');
        return Mage::helper('freteclick')->__('%s - %d days', $name, $finalTime);
    }

    /**
     * @return Varien_Http_Client
     */
    public function getClientRequest()
    {
        if ($client = Mage::getModel('freteclick/client', $this->getConfigData('api_quote_url'))) {
            // Store account information

            //$client->setHeaders("Content-type: application/json");
            //$client->setRawData()->setEncType('application/json');

    

            //'name'      => $this->_getStoreName(),
            //'phone'     => $this->_getStorePhone(),
            //'email'     => $this->_getStoreEmail()
 

            // Store address information
            $client->setParameterPost(array(
                'origin' => array(
                    //'cep-origin'            => $this->_getStorePostcode(),
                    //'street-origin'         => $this->_getStoreStreet(),
                    //'address-number-origin' => $this->_getStoreStreetNumber(),
                    //'complement-origin'     => $this->_getStoreAdditionalInfo(),
                    //'district-origin'       => $this->_getStoreDistrict(),
                    'city'           => $this->_getStoreCity(),
                    'state'          => "SP", //$this->_getStoreRegion(),
                    'country'        => $this->_getStoreCountry()
                )
            ));
        }

        return $client;
    }

    /**
     * 
     * @return Varien_Data_Collection
     */
    public function getQuotes(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::log('Frete_Click_Model_Abstract::getQuotes');
        $collection = new Varien_Data_Collection();

        $this->setItems($this->_getRequestItems($request));
        
        if ($client = $this->getClientRequest()) {
            $productPack = $this->_getProductPackage();

            $address = $this->getDestAddress();

            $client->setParameterPost(array(
                'destination' => array(
                    //'cep-destination'               => $address->getPostcode(),
                    //'street-destination'            => $address->getStreet(),
                    //'address-number-destination'    => $address->getNumber(),
                    //'complement-destination'        => $address->getAdditionalInfo(),
                    //'district-destination'          => $address->getDistrict(),
                    'city'              => $address->getCity(),
                    'state'             => $address->getRegion(),
                    'country'           => $address->getCountry()
                )
            ));

            if (!empty($productPack)) {
                // Product Cart information
                $client->setParameterPost(array(
                    'productType' => $this->_getMainCategory(),
                    'productTotalPrice' => $request->getPackageValue(), //Mage::helper('freteclick')->formatAmount($request->getPackageValue()),
                    'packages' => $productPack,
                    'quoteType' => ($this->getConfigData('fast_and_cheap_filter') != 1) ? 'simple' : 'full',
                    'noRetrieve' => false
                ));
            }

            $client->setHeaders('api-token', $this->getConfigData('api_key'));

            $request->setSession($this->_getSession());
                     
            $quotes = $client->getQuotes($request);
            
            if (!empty($quotes)) {
                foreach ($quotes as $quote) {
                    $collection->addItem(new Varien_Object($quote));
                }
            } else {
                Mage::log('Empty response');
            }
        }
        
        $collection = $this->_getCollectionFilter($collection);
        return $collection;
    }
}

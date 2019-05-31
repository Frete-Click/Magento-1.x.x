<?php
abstract class Frete_Click_Model_Abstract extends Mage_Shipping_Model_Carrier_Abstract
{    
    /**
     * Crossdocking max value for all itens in cart
     * 
     * @var int
     */
    protected $_crossdocking = null;
    
    /**
     * Raw rate request data
     *
     * @var Mage_Shipping_Model_Rate_Request|null
     */
    protected $_rawRequest = null;

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

        return __('Mixed');
    }

    protected function _getProductPackage()
    {
        $package = array();
        $sizeFactor = (float)$this->getConfigData('size_factor');
        $weightFactor = (float)$this->getConfigData('weight_factor');
        $items = $this->_getRequestItems($this->_rawRequest);

        foreach ($items as $item) {
            if ($_product = $item->getProduct()) {
                $_product->load($_product->getId());
                $height = $this->_getProductAttribute($_product, $this->getConfigData('attribute_height'));
                $width = $this->_getProductAttribute($_product, $this->getConfigData('attribute_width'));
                $depth = $this->_getProductAttribute($_product, $this->getConfigData('attribute_length'));
                $height*= $sizeFactor;
                $width *= $sizeFactor;
                $depth *= $sizeFactor;
                $weigth = $item->getWeight() * $weightFactor;
                $height = number_format($height, 2, ',', '');
                $width  = number_format($width, 2, ',', '');
                $depth  = number_format($depth, 2, ',', '');
                $weigth = number_format($weigth, 2, ',', '');
                $package[] = array(
                    'qtd'       => $item->getTotalQty(),
                    'weight'    => $weigth,
                    'height'    => $height,
                    'width'     => $width,
                    'depth'     => $depth
                );
            }
        }

        return $package;
    }

    protected function _getStorePostcode()
    {
        $srcPostCode = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
        $srcPostCode = Mage::helper('freteclick')->formatZip($srcPostCode);
        return $srcPostCode;
    }

    protected function _getStoreName()
    {
        $name = Mage::getStoreConfig('general/store_information/name', $this->getStore());
        return $name;
    }

    protected function _getStorePhone()
    {
        $phone = Mage::getStoreConfig('general/store_information/phone', $this->getStore());
        return $phone;
    }

    protected function _getStoreEmail()
    {
        $email = Mage::getStoreConfig('trans_email/ident_general/email', $this->getStore());
        return $email;
    }

    protected function _getStoreStreet()
    {
        $street = Mage::getStoreConfig('shipping/origin/street_line1', $this->getStore());
        return $street;
    }

    protected function _getStoreDistrict()
    {
        $district = Mage::getStoreConfig('shipping/origin/street_line2', $this->getStore());
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
        $number = 1;
        $street = explode(',', $this->_getStoreStreet());
        $last = array_pop($street);
        if (is_numeric($last)) {
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
        $items = $this->_getRequestItems($this->_rawRequest);
        foreach ($items as $item) {
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
                $items = $this->_getRequestItems($this->_rawRequest);

                foreach ($items as $item) {
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
            $client->setParameterPost(array(
                'api-key'   => $this->getConfigData('api_key'),
                'order'     => 'total',
                'name'      => $this->_getStoreName(),
                'phone'     => $this->_getStorePhone(),
                'email'     => $this->_getStoreEmail()
            ));

            // Store address information
            $client->setParameterPost(array(
                'cep-origin'            => $this->_getStorePostcode(),
                'street-origin'         => $this->_getStoreStreet(),
                'address-number-origin' => $this->_getStoreStreetNumber(),
                'complement-origin'     => $this->_getStoreAdditionalInfo(),
                'district-origin'       => $this->_getStoreDistrict(),
                'city-origin'           => $this->_getStoreCity(),
                'state-origin'          => $this->_getStoreRegion(),
                'country-origin'        => $this->_getStoreCountry()
            ));

            $productPack = $this->_getProductPackage();
            if (!empty($productPack)) {
                // Product Cart information
                $client->setParameterPost(array(
                    'product-type' => $this->_getMainCategory(),
                    'product-total-price' => $this->_rawRequest->getPackageValue(),
                    'product-package' => $productPack
                ));
            }
        }

        return $client;
    }

    /**
     * 
     * @return Varien_Data_Collection
     */
    public function getQuotes()
    {
        Mage::log('Frete_Click_Model_Abstract::getQuotes');
        $collection = new Varien_Data_Collection();
        
        if ($client = $this->getClientRequest()) {
            $address = $this->getDestAddress();
            $client->setParameterPost(array(
                'cep-destination'               => $address->getPostcode(),
                'street-destination'            => $address->getStreet(),
                'address-number-destination'    => $address->getNumber(),
                'complement-destination'        => $address->getAdditionalInfo(),
                'district-destination'          => $address->getDistrict(),
                'city-destination'              => $address->getCity(),
                'state-destination'             => $address->getRegion(),
                'country-destination'           => $address->getCountry()
            ));

            if ($quotes = $client->getQuotes()) {
                foreach ($quotes as $quote) {
                    $collection->addItem(new Varien_Object($quote));
                }
            } else {
                $collection->addItem(new Varien_Object(array('error'=>'Empty response')));
            }
        }
        
        return $collection;
    }
}

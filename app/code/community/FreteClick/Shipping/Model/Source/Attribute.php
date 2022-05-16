<?php
class FreteClick_Shipping_Model_Source_Attribute extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    public function toOptionArray()
    {
        $optionArray = array(
            array('value' => '', 'label' => Mage::helper('adminhtml')->__('Select'))
        );
        $collection = Mage::getResourceSingleton('catalog/product_attribute_collection');
        /* @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        foreach ($collection as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel();
            if (empty($label)) {
                $label = $code;
            } else {
                $label.= " ({$code})";
            }
            $optionArray[] = array(
                'value' => $code,
                'label' => $label,
            );
        }
        return $optionArray;
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Eav_Model_Entity_Attribute_Source_Interface::getAllOptions()
     */
    public function getAllOptions()
    {
        return self::toOptionArray();
    }
}

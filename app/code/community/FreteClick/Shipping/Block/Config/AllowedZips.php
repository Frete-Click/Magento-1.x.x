<?php
class FreteClick_Shipping_Block_Config_AllowedZips extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    public function _prepareToRender()
    {
        $this->addColumn('from', array(
            'label' => Mage::helper('freteclick')->__('Zip From'),
            'style' => 'width:100px'
        ));
        $this->addColumn('to', array(
            'label' => Mage::helper('freteclick')->__('Zip To'),
            'style' => 'width:100px'
        ));
        
        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('freteclick')->__('Add');
    }
}

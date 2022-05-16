<?php
class Frete_Click_Model_Observer
{
    /**
     * @param Varien_Event_Observer $observer
     */
    public function addFreteClickComment(Varien_Event_Observer $observer)
    {
        Mage::log('FreteClick_Shipping_Model_Observer::addFreteClickComment');
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getData('order');
        $freteClickOrderId = Mage::getSingleton('checkout/session')->getFreteClickOrderId();

        if (!empty($freteClickOrderId) && $order->getShippingMethod(true)->getCarrierCode() == 'freteclick') {
            $order->addStatusHistoryComment(
                Mage::helper('freteclick')->__('Frete Click Order Id: %s', $freteClickOrderId)
            );

            Mage::getSingleton('checkout/session')->unsFreteClickOrderId();
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function addAdminFreteClickComment(Varien_Event_Observer $observer)
    {
        Mage::log('FreteClick_Shipping_Model_Observer::addAdminFreteClickComment');
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getData('order');
        $freteClickOrderId = Mage::getSingleton('adminhtml/session_quote')->getFreteClickOrderId();

        if (!empty($freteClickOrderId) && $order->getShippingMethod(true)->getCarrierCode() == 'freteclick') {
            $order->addStatusHistoryComment(
                Mage::helper('freteclick')->__('Frete Click Order Id: %s', $freteClickOrderId)
            );
            $order->save();
            Mage::getSingleton('adminhtml/session_quote')->unsFreteClickOrderId();
        }
    }

}

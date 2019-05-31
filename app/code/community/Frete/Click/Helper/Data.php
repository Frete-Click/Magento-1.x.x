<?php
class Frete_Click_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function formatTaxvat($taxvat)
    {
        $taxvat = preg_replace('/\D/', '', $taxvat);
        return $taxvat;
    }
    
    public function formatZip($postcode, $length=8)
    {
        $postcode = preg_replace('/\D/', '', $postcode);
        $postcode = str_pad($postcode, $length, '0', STR_PAD_LEFT);
        return $postcode;
    }
}

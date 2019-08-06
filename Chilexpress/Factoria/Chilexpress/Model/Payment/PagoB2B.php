<?php


namespace Factoria\Chilexpress\Model\Payment;

class PagoB2B extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "pagob2b";
    protected $_isOffline = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}

<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class Vnpay extends AbstractMethod
{
    protected $_code = 'fashionstore_vnpay';

    protected $_isOffline = true;
}
<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class Momo extends AbstractMethod
{
    protected $_code = 'fashionstore_momo';

    protected $_isOffline = true;
}
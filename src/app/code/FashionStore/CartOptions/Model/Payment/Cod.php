<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class Cod extends AbstractMethod
{
    protected $_code = 'fashionstore_cod';

    protected $_isOffline = true;
}
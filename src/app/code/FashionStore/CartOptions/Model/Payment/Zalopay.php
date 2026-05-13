<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class Zalopay extends AbstractMethod
{
    protected $_code = 'fashionstore_zalopay';

    protected $_isOffline = true;
}
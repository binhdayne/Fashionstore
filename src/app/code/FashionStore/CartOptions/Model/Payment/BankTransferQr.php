<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class BankTransferQr extends AbstractMethod
{
    protected $_code = 'fashionstore_banktransfer_qr';

    protected $_isOffline = true;
}
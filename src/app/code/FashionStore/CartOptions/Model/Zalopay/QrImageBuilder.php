<?php
declare(strict_types=1);

namespace FashionStore\CartOptions\Model\Zalopay;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrImageBuilder
{
    public function buildDataUri(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $qrCode = new QrCode($content, size: 280, margin: 8);
        $writer = new SvgWriter();

        return $writer->write($qrCode)->getDataUri();
    }
}
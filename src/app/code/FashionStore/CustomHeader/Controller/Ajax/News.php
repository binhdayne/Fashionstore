<?php

namespace FashionStore\CustomHeader\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;

class News extends Action implements HttpGetActionInterface
{
    private const RSS_URL = 'https://vnexpress.net/rss/kinh-doanh.rss';

    private JsonFactory $resultJsonFactory;

    private Curl $curl;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->curl = $curl;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $payload = [
            'title' => '',
            'link' => '',
        ];

        try {
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->curl->setOption(CURLOPT_TIMEOUT, 8);
            $this->curl->get(self::RSS_URL);

            $xml = @simplexml_load_string($this->curl->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);

            if ($xml !== false && isset($xml->channel->item[0])) {
                $item = $xml->channel->item[0];
                $payload['title'] = trim((string) $item->title);
                $payload['link'] = trim((string) $item->link);
            }
        } catch (\Throwable $exception) {
            $payload = [
                'title' => '',
                'link' => '',
            ];
        }

        return $result->setData($payload);
    }
}
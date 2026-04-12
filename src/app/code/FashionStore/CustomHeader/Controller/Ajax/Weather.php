<?php

namespace FashionStore\CustomHeader\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\HTTP\Client\Curl;

class Weather extends Action implements HttpGetActionInterface
{
    private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';
    private const API_KEY = '576aeed77ae5f7fc3f1b5d3e2dc64116';

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
            'temp' => null,
            'icon' => '',
        ];

        try {
            $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 5);
            $this->curl->setOption(CURLOPT_TIMEOUT, 8);
            $this->curl->get(self::API_URL . '?' . http_build_query([
                'q' => 'Hanoi',
                'appid' => self::API_KEY,
                'units' => 'metric',
                'lang' => 'vi',
            ]));

            $response = json_decode($this->curl->getBody(), true);

            if (is_array($response) && isset($response['main']['temp'])) {
                $payload['temp'] = (int) round((float) $response['main']['temp']);
            }

            if (!empty($response['weather'][0]['icon'])) {
                $payload['icon'] = 'https://openweathermap.org/img/wn/'
                    . $response['weather'][0]['icon']
                    . '@2x.png';
            }
        } catch (\Throwable $exception) {
            $payload = [
                'temp' => null,
                'icon' => '',
            ];
        }

        return $result->setData($payload);
    }
}
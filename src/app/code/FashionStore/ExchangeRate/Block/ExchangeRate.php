<?php
namespace FashionStore\ExchangeRate\Block;

use Magento\Framework\View\Element\Template;

class ExchangeRate extends Template
{
    public function getExchangeRates()
    {
        try {
            $url = 'https://portal.vietcombank.com.vn/Usercontrols/TVPortal.TyGia/pXML.aspx?b=10';
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $xml = @file_get_contents($url, false, $context);
            
            if (!$xml) {
                return $this->getMockData();
            }
            
            $data = simplexml_load_string($xml);
            if (!$data) {
                return $this->getMockData();
            }
            
            $currencies = ['USD', 'EUR', 'JPY', 'CNY', 'GBP', 'AUD', 'SGD', 'KRW'];
            $rates = [];
            
            foreach ($data->Exrate as $rate) {
                $code = (string)$rate['CurrencyCode'];
                if (in_array($code, $currencies)) {
                    $rates[] = [
                        'code' => $code,
                        'name' => (string)$rate['CurrencyName'],
                        'buy'  => (string)$rate['Buy'],
                        'sell' => (string)$rate['Sell'],
                        'transfer' => (string)$rate['Transfer'],
                    ];
                }
            }
            
            return !empty($rates) ? $rates : $this->getMockData();
            
        } catch (\Exception $e) {
            return $this->getMockData();
        }
    }
    
    private function getMockData()
    {
        return [
            ['code' => 'USD', 'name' => 'Đô la Mỹ',     'buy' => '25,140', 'sell' => '25,470', 'transfer' => '25,390'],
            ['code' => 'EUR', 'name' => 'Euro',           'buy' => '26,800', 'sell' => '28,100', 'transfer' => '27,800'],
            ['code' => 'JPY', 'name' => 'Yên Nhật',       'buy' => '158',    'sell' => '168',    'transfer' => '166'],
            ['code' => 'CNY', 'name' => 'Nhân dân tệ',    'buy' => '3,410',  'sell' => '3,610',  'transfer' => '3,560'],
            ['code' => 'GBP', 'name' => 'Bảng Anh',       'buy' => '30,500', 'sell' => '32,100', 'transfer' => '31,700'],
            ['code' => 'SGD', 'name' => 'Đô la Singapore','buy' => '18,200', 'sell' => '19,100', 'transfer' => '18,900'],
        ];
    }
    
    public function getDateTime()
    {
        return date('d/m/Y H:i');
    }
}

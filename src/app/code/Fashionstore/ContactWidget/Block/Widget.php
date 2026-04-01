<?php
/**
 * Floating Contact Widget Block
 */

namespace Fashionstore\ContactWidget\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Widget extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Fashionstore_ContactWidget::contact-widget.phtml';

    /**
     * Widget constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get contact methods configuration
     *
     * @return array
     */
    public function getContactMethods()
    {
        return [
            'hotline' => [
                'label' => __('Hotline'),
                'icon' => '☎',
                'link' => 'tel:+84862344733', // Thay đổi số điện thoại của bạn
                'color' => '#28a745' // Green
            ],
            'zalo' => [
                'label' => __('Chat Zalo'),
                'icon' => 'Z',
                'link' => 'https://zalo.me/0862344733', // Thay đổi link Zalo của bạn
                'color' => '#0084ff' // Blue
            ],
            'messenger' => [
                'label' => __('Messenger'),
                'icon' => 'M',
                'link' => 'https://www.facebook.com/share/186F3HL1Zc/', // Thay đổi Facebook Page của bạn
                'color' => 'linear-gradient(135deg, #0084ff, #00d4ff)' // Gradient
            ]
        ];
    }
}
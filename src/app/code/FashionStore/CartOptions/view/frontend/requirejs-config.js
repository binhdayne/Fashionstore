var config = {
    map: {
        '*': {
            'Magento_Checkout/js/proceed-to-checkout':
                'FashionStore_CartOptions/js/proceed-to-checkout-payment',
            'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method':
                'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-redirect',
            'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2':
                'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect',
            'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-method':
                'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-svg-method',
            'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-overlay-method':
                'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-svg-method'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/payment-service': {
                'FashionStore_CartOptions/js/model/payment-service-mixin': true
            },
            'Magento_Checkout/js/model/step-navigator': {
                'FashionStore_CartOptions/js/model/step-navigator-mixin': true
            }
        }
    }
};

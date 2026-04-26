var config = {
    map: {
        '*': {
            'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method':
                'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-redirect',
            'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2':
                'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect'
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
define([
    'Magento_Checkout/js/view/payment/default',
    'mage/url'
], function (Component, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vnpay_Payment/payment/vnpay',
            redirectAfterPlaceOrder: false
        },

        getCode: function () {
            return 'vnpay';
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {}
            };
        },

        afterPlaceOrder: function () {
            window.location.replace(urlBuilder.build('vnpay/payment/redirect'));
        }
    });
});

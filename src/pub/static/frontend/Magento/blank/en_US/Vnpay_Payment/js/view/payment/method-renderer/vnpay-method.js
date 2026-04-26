define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Vnpay_Payment/payment/vnpay'
        },

        getCode: function () {
            return 'vnpay';
        },

        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {}
            };
        }
    });
});

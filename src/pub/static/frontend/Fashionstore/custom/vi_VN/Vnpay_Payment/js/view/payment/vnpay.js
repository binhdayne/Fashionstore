define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    // Register VNPAY renderer so the method appears in checkout payment list.
    rendererList.push({
        type: 'vnpay',
        component: 'Vnpay_Payment/js/view/payment/method-renderer/vnpay-method'
    });

    return Component.extend({});
});

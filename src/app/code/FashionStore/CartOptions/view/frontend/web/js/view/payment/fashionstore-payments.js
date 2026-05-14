define([
    'uiComponent',
    'underscore',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/payment/method-list',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_Checkout/js/model/quote'
], function (Component, _, selectPaymentMethod, methodList, rendererList, quote) {
    'use strict';

    var allowedMethods = [
        'fashionstore_cod',
        'fashionstore_banktransfer_qr',
        'fashionstore_zalopay',
        'vnpay',
        'fashionstore_vnpay'
    ];

    var fallbackMethods = {
        fashionstore_cod: {
            method: 'fashionstore_cod',
            title: 'Thanh toán offline khi nhận hàng'
        },
        fashionstore_banktransfer_qr: {
            method: 'fashionstore_banktransfer_qr',
            title: 'Chuyển khoản QR'
        },
        fashionstore_zalopay: {
            method: 'fashionstore_zalopay',
            title: 'ZaloPay'
        },
        vnpay: {
            method: 'vnpay',
            title: 'Thanh toán bằng VNPAY'
        }
    };

    function ensureSyntheticMethods(methods) {
        var currentMethods = methods ? methods.slice() : [],
            existingCodes = _.pluck(currentMethods, 'method');

        _.each(fallbackMethods, function (method, code) {
            if (allowedMethods.indexOf(code) !== -1 && existingCodes.indexOf(code) === -1) {
                currentMethods.push(method);
            }
        });

        return currentMethods;
    }

    function filterMethods(methods) {
        return _.sortBy(_.filter(ensureSyntheticMethods(methods), function (method) {
            return allowedMethods.indexOf(method.method) !== -1;
        }), function (method) {
            return allowedMethods.indexOf(method.method);
        });
    }

    function syncVisibleMethods(methods) {
        var filteredMethods = filterMethods(methods),
            selectedMethod = quote.paymentMethod(),
            selectedMethodCode = selectedMethod ? selectedMethod.method : null;

        if (filteredMethods.length !== (methods || []).length) {
            methodList(filteredMethods);
        }

        if (selectedMethodCode && allowedMethods.indexOf(selectedMethodCode) === -1) {
            selectPaymentMethod(filteredMethods[0] || null);
        }
    }

    function forceSyncMethods() {
        syncVisibleMethods(methodList());
    }

    rendererList.push(
        {
            type: 'fashionstore_cod',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-redirect'
        },
        {
            type: 'fashionstore_banktransfer_qr',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/bank-transfer-qr-method'
        },
        {
            type: 'fashionstore_zalopay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-svg-method'
        },
        {
            type: 'vnpay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-redirect'
        },
        {
            type: 'fashionstore_vnpay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-redirect'
        }
    );

    return Component.extend({
        initialize: function () {
            this._super();
            forceSyncMethods();
            methodList.subscribe(syncVisibleMethods);
            window.setTimeout(forceSyncMethods, 0);
            window.setTimeout(forceSyncMethods, 300);
            window.setTimeout(forceSyncMethods, 1000);

            return this;
        }
    });
});

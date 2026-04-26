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
        'vnpay',
        'fashionstore_vnpay',
        'fashionstore_momo',
        'fashionstore_cod',
        'fashionstore_zalopay',
        'fashionstore_banktransfer_qr'
    ];

    var fallbackMethods = {
        vnpay: {
            method: 'vnpay',
            title: 'Thanh toán bằng VNPAY'
        },
        fashionstore_momo: {
            method: 'fashionstore_momo',
            title: 'MoMo'
        },
        fashionstore_cod: {
            method: 'fashionstore_cod',
            title: 'Thanh toan khi nhan hang'
        },
        fashionstore_zalopay: {
            method: 'fashionstore_zalopay',
            title: 'ZaloPay'
        },
        fashionstore_banktransfer_qr: {
            method: 'fashionstore_banktransfer_qr',
            title: 'Chuyen khoan QR'
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
        return _.filter(ensureSyntheticMethods(methods), function (method) {
            return allowedMethods.indexOf(method.method) !== -1;
        });
    }

    function syncVisibleMethods(methods) {
        var filteredMethods = filterMethods(methods),
            selectedMethod = quote.paymentMethod(),
            selectedMethodCode = selectedMethod ? selectedMethod.method : null;

        if (JSON.stringify(filteredMethods) !== JSON.stringify(methods || [])) {
            methodList(filteredMethods);
        }

        if (selectedMethodCode && allowedMethods.indexOf(selectedMethodCode) === -1) {
            selectPaymentMethod(filteredMethods[0] || null);
        }
    }

    rendererList.push(
        {
            type: 'vnpay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect'
        },
        {
            type: 'fashionstore_vnpay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect'
        },
        {
            type: 'fashionstore_momo',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect'
        },
        {
            type: 'fashionstore_cod',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2-redirect'
        },
        {
            type: 'fashionstore_zalopay',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/zalopay-svg-method'
        },
        {
            type: 'fashionstore_banktransfer_qr',
            component: 'FashionStore_CartOptions/js/view/payment/method-renderer/bank-transfer-qr-method-v2'
        }
    );

    return Component.extend({
        initialize: function () {
            this._super();
            
            // Get current methods
            var currentMethods = methodList() || [];
            
            // Ensure VNPAY and other synthetic methods are always available
            var finalMethods = ensureSyntheticMethods(currentMethods);
            
            // Set methodList with all methods including synthetic ones
            methodList(finalMethods);
            
            // Set up subscription for future changes
            methodList.subscribe(syncVisibleMethods);

            return this;
        }
    });
});
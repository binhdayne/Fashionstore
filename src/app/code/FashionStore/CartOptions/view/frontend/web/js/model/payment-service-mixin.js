define([
    'underscore'
], function (_) {
    'use strict';

    var allowedMethods = [
        'fashionstore_vnpay',
        'fashionstore_momo',
        'fashionstore_cod',
        'fashionstore_zalopay',
        'fashionstore_banktransfer_qr'
    ];

    var fallbackMethods = [
        {
            method: 'fashionstore_cod',
            title: 'Thanh toan khi nhan hang'
        },
        {
            method: 'fashionstore_banktransfer_qr',
            title: 'Chuyen khoan QR'
        }
    ];

    function ensureSyntheticMethods(methods) {
        var currentMethods = methods ? methods.slice() : [],
            existingCodes = _.pluck(currentMethods, 'method');

        _.each(fallbackMethods, function (method) {
            if (existingCodes.indexOf(method.method) === -1) {
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

    return function (target) {
        var originalSetPaymentMethods = target.setPaymentMethods,
            originalGetAvailablePaymentMethods = target.getAvailablePaymentMethods;

        target.setPaymentMethods = function (methods) {
            return originalSetPaymentMethods.call(this, filterMethods(methods));
        };

        target.getAvailablePaymentMethods = function () {
            return filterMethods(originalGetAvailablePaymentMethods.call(this));
        };

        return target;
    };
});
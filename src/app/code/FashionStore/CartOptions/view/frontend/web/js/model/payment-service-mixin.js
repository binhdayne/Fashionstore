define([
    'underscore'
], function (_) {
    'use strict';

    var allowedMethods = [
        'fashionstore_cod',
        'fashionstore_banktransfer_qr',
        'fashionstore_zalopay',
        'vnpay',
        'fashionstore_vnpay'
    ];

    var fallbackMethods = [
        {
            method: 'fashionstore_cod',
            title: 'Thanh toán offline khi nhận hàng'
        },
        {
            method: 'fashionstore_banktransfer_qr',
            title: 'Chuyển khoản QR'
        },
        {
            method: 'fashionstore_zalopay',
            title: 'ZaloPay'
        },
        {
            method: 'vnpay',
            title: 'Thanh toán bằng VNPAY'
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
        return _.sortBy(_.filter(ensureSyntheticMethods(methods), function (method) {
            return allowedMethods.indexOf(method.method) !== -1;
        }), function (method) {
            return allowedMethods.indexOf(method.method);
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

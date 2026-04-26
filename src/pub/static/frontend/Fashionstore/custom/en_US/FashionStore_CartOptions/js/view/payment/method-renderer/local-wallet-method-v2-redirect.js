define([
    'require',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/url'
], function (require, Component, redirectOnSuccessAction, urlBuilder) {
    'use strict';

    var vnpayRedirectMethods = [
        'vnpay',
        'fashionstore_vnpay'
    ];

    var methodConfig = {
        vnpay: {
            badge: '',
            description: '',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-vnpay.svg'),
            modifier: 'vnpay'
        },
        fashionstore_vnpay: {
            badge: '',
            description: '',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-vnpay.svg'),
            modifier: 'vnpay'
        },
        fashionstore_momo: {
            badge: 'Wallet',
            description: 'Thanh toan bang vi MoMo cho don hang noi dia.',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-momo.svg'),
            modifier: 'momo'
        },
        fashionstore_cod: {
            badge: 'Offline',
            description: 'Thanh toan khi nhan hang, nhan vien se xac nhan va thu tien khi giao don.',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-cod.svg'),
            modifier: 'cod'
        },
        fashionstore_banktransfer_qr: {
            badge: 'Transfer',
            description: 'Quet ma QR chuyen khoan va tai len anh minh chung de shop doi soat nhanh hon.',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-banktransferqr.svg'),
            modifier: 'banktransferqr'
        }
    };

    return Component.extend({
        defaults: {
            template: 'FashionStore_CartOptions/payment/local-wallet'
        },

        redirectAfterPlaceOrder: false,

        getMethodConfig: function () {
            return methodConfig[this.getCode()] || {};
        },

        getBadge: function () {
            return this.getMethodConfig().badge || 'Pay';
        },

        getDescription: function () {
            return this.getMethodConfig().description || '';
        },

        getLogoUrl: function () {
            return this.getMethodConfig().icon || '';
        },

        getMethodCssClass: function () {
            var modifier = this.getMethodConfig().modifier || 'generic';

            return 'fashionstore-payment-method--' + modifier;
        },

        getCssClasses: function () {
            var cssClasses = {
                '_active': this.getCode() === this.isChecked()
            };

            cssClasses[this.getMethodCssClass()] = true;

            return cssClasses;
        },

        afterPlaceOrder: function () {
            if (vnpayRedirectMethods.indexOf(this.getCode()) !== -1) {
                window.location.replace(urlBuilder.build('vnpay/payment/redirect'));
                return;
            }

            redirectOnSuccessAction.execute();
        }
    });
});

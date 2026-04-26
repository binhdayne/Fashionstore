define([
    'require',
    'Magento_Checkout/js/view/payment/default'
], function (require, Component) {
    'use strict';

    var methodConfig = {
        fashionstore_vnpay: {
            badge: 'Gateway',
            description: 'Quet QR, the ATM noi dia va ung dung ngan hang ho tro VNPay.',
            icon: require.toUrl('FashionStore_CartOptions/images/payment-vnpay.svg'),
            modifier: 'vnpay'
        },
        fashionstore_momo: {
            badge: 'Wallet',
            description: 'Thanh toan nhanh bang vi MoMo tren mobile hoac bang QR.',
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
        }
    });
});
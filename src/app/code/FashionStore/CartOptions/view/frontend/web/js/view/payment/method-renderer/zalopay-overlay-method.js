define([
    'jquery',
    'mage/url',
    'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method'
], function ($, urlBuilder, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'FashionStore_CartOptions/payment/zalopay-overlay-wallet'
        },
        redirectAfterPlaceOrder: false,
        pollingIntervalMs: 60000,
        poller: null,

        initObservable: function () {
            this._super()
                .observe([
                    'qrImageUrl',
                    'gatewayUrl',
                    'statusMessage',
                    'orderIncrementId',
                    'isCreatingOrder',
                    'isPolling',
                    'isModalVisible'
                ]);

            this.qrImageUrl('');
            this.gatewayUrl('');
            this.statusMessage('');
            this.orderIncrementId('');
            this.isCreatingOrder(false);
            this.isPolling(false);
            this.isModalVisible(false);

            return this;
        },

        afterPlaceOrder: function () {
            this.createZalopayOrder();
        },

        getButtonTitle: function () {
            return 'Thanh toan voi ZaloPay';
        },

        createZalopayOrder: function () {
            var self = this;

            self.isCreatingOrder(true);
            self.statusMessage('Dang chuyen huong sang cong ZaloPay...');

            $.ajax({
                url: urlBuilder.build('fashionstore_cartoptions/zalopay/create'),
                type: 'POST',
                dataType: 'json',
                data: {
                    force: 1
                }
            }).done(function (response) {
                if (response && response.success && response.order_url) {
                    window.location.href = response.order_url;
                    return;
                }

                self.messageContainer.addErrorMessage({
                    message: response && response.message ? response.message : 'Khong the tao giao dich ZaloPay.'
                });
                self.statusMessage('Khong the tao giao dich ZaloPay.');
            }).fail(function () {
                self.messageContainer.addErrorMessage({
                    message: 'Khong the ket noi toi may chu ZaloPay luc nay.'
                });
                self.statusMessage('Khong the ket noi toi may chu ZaloPay luc nay.');
            }).always(function () {
                self.isCreatingOrder(false);
            });
        },

        buildQrImageUrl: function (qrContent) {
            if (!qrContent) {
                return '';
            }

            return '';
        },

        openPaymentModal: function () {
            this.isModalVisible(false);
        },

        closePaymentModal: function () {
            this.isModalVisible(false);
        },

        queryPaymentStatus: function () {
            var self = this;

            if (!self.orderIncrementId()) {
                return;
            }

            $.ajax({
                url: urlBuilder.build('fashionstore_cartoptions/zalopay/query'),
                type: 'GET',
                dataType: 'json',
                data: {
                    increment_id: self.orderIncrementId()
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    self.statusMessage(response && response.message ? response.message : 'Chua lay duoc trang thai giao dich.');
                    return;
                }

                if (response.paid) {
                    self.stopPolling();
                    self.closePaymentModal();
                    self.statusMessage('Thanh toan thanh cong. Dang chuyen sang trang xac nhan don hang...');
                    window.location.href = response.redirect_url;
                    return;
                }

                if (response.is_processing) {
                    self.statusMessage('Giao dich dang duoc ZaloPay xu ly. He thong se kiem tra lai sau 1 phut.');
                    return;
                }

                self.statusMessage(response.sub_return_message || response.return_message || 'Dang cho thanh toan. He thong se kiem tra lai sau 1 phut.');
            }).fail(function () {
                self.statusMessage('Khong the truy van trang thai giao dich luc nay.');
            });
        },

        startPolling: function () {
            var self = this;

            self.stopPolling();
            self.isPolling(true);
            self.poller = window.setInterval(function () {
                self.queryPaymentStatus();
            }, self.pollingIntervalMs);
        },

        stopPolling: function () {
            if (this.poller) {
                window.clearInterval(this.poller);
                this.poller = null;
            }

            this.isPolling(false);
        },

        handleOverlayClose: function () {
            this.stopPolling();
            this.closePaymentModal();
        }
    });
});

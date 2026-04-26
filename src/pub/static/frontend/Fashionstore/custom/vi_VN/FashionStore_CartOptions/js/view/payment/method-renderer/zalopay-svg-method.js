define([
    'jquery',
    'mage/url',
    'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method'
], function ($, urlBuilder, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'FashionStore_CartOptions/payment/zalopay-svg-wallet'
        },
        redirectAfterPlaceOrder: false,
        pollingIntervalMs: 5000,
        poller: null,

        initObservable: function () {
            this._super()
                .observe([
                    'qrImageUrl',
                    'gatewayUrl',
                    'statusMessage',
                    'orderIncrementId',
                    'amountLabel',
                    'isCreatingOrder',
                    'isPolling',
                    'isModalVisible'
                ]);

            this.qrImageUrl('');
            this.gatewayUrl('');
            this.statusMessage('');
            this.orderIncrementId('');
            this.amountLabel('');
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
            self.statusMessage('Dang tao ma thanh toan ZaloPay...');

            $.ajax({
                url: urlBuilder.build('fashionstore_cartoptions/zalopay/create'),
                type: 'POST',
                dataType: 'json'
            }).done(function (response) {
                if (!response || !response.success) {
                    self.messageContainer.addErrorMessage({
                        message: response && response.message ? response.message : 'Khong the tao giao dich ZaloPay.'
                    });
                    self.statusMessage('Khong the tao giao dich ZaloPay.');
                    return;
                }

                self.orderIncrementId(response.order_increment_id || '');
                self.gatewayUrl(response.order_url || '');
                self.qrImageUrl(response.qr_image || '');
                self.amountLabel(self.buildAmountLabel(response));
                self.statusMessage('Quet QR bang ZaloPay de hoan tat thanh toan. He thong se tu dong kiem tra trang thai moi 5 giay.');
                self.openPaymentModal();
                self.startPolling();
            }).fail(function () {
                self.messageContainer.addErrorMessage({
                    message: 'Khong the ket noi toi may chu ZaloPay luc nay.'
                });
                self.statusMessage('Khong the ket noi toi may chu ZaloPay luc nay.');
            }).always(function () {
                self.isCreatingOrder(false);
            });
        },

        openPaymentModal: function () {
            this.isModalVisible(true);
        },

        buildAmountLabel: function (response) {
            var amount = response && typeof response.grand_total !== 'undefined' ? Number(response.grand_total) : NaN,
                currency = response && response.currency_code ? response.currency_code : 'VND';

            if (!Number.isFinite(amount)) {
                return '';
            }

            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: currency,
                maximumFractionDigits: 0
            }).format(amount);
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
                    self.statusMessage('Giao dich dang duoc ZaloPay xu ly. He thong se kiem tra lai sau 5 giay.');
                    return;
                }

                self.statusMessage(response.sub_return_message || response.return_message || 'Dang cho thanh toan. He thong se kiem tra lai sau 5 giay.');
            }).fail(function () {
                self.statusMessage('Khong the truy van trang thai giao dich luc nay.');
            });
        },

        startPolling: function () {
            var self = this;

            self.stopPolling();
            self.isPolling(true);
            window.setTimeout(function () {
                self.queryPaymentStatus();
            }, 1500);
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
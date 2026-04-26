define([
    'jquery',
    'require',
    'mage/url',
    'FashionStore_CartOptions/js/view/payment/method-renderer/local-wallet-method-v2'
], function ($, require, urlBuilder, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'FashionStore_CartOptions/payment/bank-transfer-qr-v2'
        },
        redirectAfterPlaceOrder: false,
        selectedProofFile: null,

        initObservable: function () {
            this._super()
                .observe([
                    'qrImageUrl',
                    'statusMessage',
                    'orderIncrementId',
                    'amountLabel',
                    'redirectUrl',
                    'proofNote',
                    'proofFileName',
                    'uploadedProofUrl',
                    'isPreparingTransfer',
                    'isUploadingProof',
                    'isModalVisible'
                ]);

            this.qrImageUrl(require.toUrl('FashionStore_CartOptions/images/bank-transfer-qr.jpg'));
            this.statusMessage('');
            this.orderIncrementId('');
            this.amountLabel('');
            this.redirectUrl('');
            this.proofNote('');
            this.proofFileName('');
            this.uploadedProofUrl('');
            this.isPreparingTransfer(false);
            this.isUploadingProof(false);
            this.isModalVisible(false);

            return this;
        },

        afterPlaceOrder: function () {
            this.prepareTransferOrder();
        },

        getButtonTitle: function () {
            return 'Dat hang va nhan QR chuyen khoan';
        },

        prepareTransferOrder: function () {
            var self = this;

            self.isPreparingTransfer(true);
            self.statusMessage('Dang tao don hang cho thanh toan chuyen khoan QR...');

            $.ajax({
                url: urlBuilder.build('fashionstore_cartoptions/banktransferqr/create'),
                type: 'POST',
                dataType: 'json'
            }).done(function (response) {
                if (!response || !response.success) {
                    self.messageContainer.addErrorMessage({
                        message: response && response.message ? response.message : 'Khong the khoi tao thanh toan chuyen khoan QR.'
                    });
                    self.statusMessage(response && response.message ? response.message : 'Khong the khoi tao thanh toan chuyen khoan QR.');
                    return;
                }

                self.orderIncrementId(response.order_increment_id || '');
                self.amountLabel(self.buildAmountLabel(response));
                self.redirectUrl(response.redirect_url || '');
                self.statusMessage('Don hang da duoc tao. Vui long quet ma QR, chuyen khoan dung so tien va tai len anh minh chung de shop doi soat.');
                self.openPaymentModal();
            }).fail(function () {
                self.messageContainer.addErrorMessage({
                    message: 'Khong the ket noi toi may chu luc nay.'
                });
                self.statusMessage('Khong the ket noi toi may chu luc nay.');
            }).always(function () {
                self.isPreparingTransfer(false);
            });
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

        openPaymentModal: function () {
            this.isModalVisible(true);
        },

        closePaymentModal: function () {
            this.isModalVisible(false);
        },

        handleOverlayClose: function () {
            this.closePaymentModal();
        },

        handleFileSelection: function (data, event) {
            var file = event && event.target && event.target.files ? event.target.files[0] : null;

            this.selectedProofFile = file || null;
            this.proofFileName(file ? file.name : '');
        },

        uploadProof: function () {
            var self = this,
                formData;

            if (!self.selectedProofFile || !self.orderIncrementId()) {
                self.messageContainer.addErrorMessage({
                    message: 'Vui long chon anh minh chung truoc khi tai len.'
                });
                return;
            }

            formData = new FormData();
            formData.append('proof_image', self.selectedProofFile);
            formData.append('increment_id', self.orderIncrementId());
            formData.append('proof_note', self.proofNote());

            self.isUploadingProof(true);
            self.statusMessage('Dang tai len anh minh chung thanh toan...');

            $.ajax({
                url: urlBuilder.build('fashionstore_cartoptions/banktransferqr/upload'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (response) {
                if (!response || !response.success) {
                    self.messageContainer.addErrorMessage({
                        message: response && response.message ? response.message : 'Khong the tai len minh chung thanh toan.'
                    });
                    self.statusMessage(response && response.message ? response.message : 'Khong the tai len minh chung thanh toan.');
                    return;
                }

                self.uploadedProofUrl(response.proof_url || '');
                self.redirectUrl(response.redirect_url || self.redirectUrl());
                self.statusMessage(response.message || 'Da tai len minh chung thanh toan thanh cong.');
            }).fail(function () {
                self.messageContainer.addErrorMessage({
                    message: 'Khong the tai len minh chung thanh toan luc nay.'
                });
                self.statusMessage('Khong the tai len minh chung thanh toan luc nay.');
            }).always(function () {
                self.isUploadingProof(false);
            });
        },

        goToSuccessPage: function () {
            if (this.redirectUrl()) {
                window.location.href = this.redirectUrl();
            }
        }
    });
});
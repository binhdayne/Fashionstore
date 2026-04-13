define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element),
            $buyNowButton = $form.find('[data-role="buy-now"]'),
            $returnUrl = $form.find('[data-role="buy-now-return-url"]'),
            $buyNowFlag = $form.find('[data-role="buy-now-flag"]'),
            originalAction = $form.attr('action') || '';

        if (!$buyNowButton.length || !$returnUrl.length || !$buyNowFlag.length) {
            return;
        }

        $buyNowButton.prop('disabled', false);

        function resetBuyNowState() {
            $returnUrl.val('');
            $buyNowFlag.val('0');
            if (originalAction) {
                $form.attr('action', originalAction);
            }
            $buyNowButton.prop('disabled', false).removeClass('disabled');
        }

        function isFormValid() {
            if (typeof $form.validation !== 'function') {
                return true;
            }

            $form.validation();

            return $form.validation('isValid');
        }

        function submitBuyNow() {
            $buyNowButton.prop('disabled', true).addClass('disabled');
            if (config.buyNowUrl) {
                $form.attr('action', config.buyNowUrl);
            }
            $buyNowFlag.val('1');
            HTMLFormElement.prototype.submit.call($form[0]);
        }

        $buyNowButton.on('click', function (event) {
            event.preventDefault();

            if ($(this).prop('disabled')) {
                return;
            }

            resetBuyNowState();

            if (!isFormValid()) {
                return;
            }

            submitBuyNow();
        });

        $form.on('submit', resetBuyNowState);
        $form.on('click', '#product-addtocart-button', function () {
            $buyNowFlag.val('0');
        });
    };
});
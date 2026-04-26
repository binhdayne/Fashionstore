define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element),
            $buyNowButton = $form.find('[data-role="buy-now"]'),
            $returnUrl = $form.find('[data-role="buy-now-return-url"]'),
            $buyNowFlag = $form.find('[data-role="buy-now-flag"]'),
            $addToCartButton = $form.find('#product-addtocart-button'),
            $sizeRequiredMessage = $form.find('[data-role="buy-now-size-required"]'),
            originalAction = $form.attr('action') || '';

        if (!$buyNowButton.length || !$returnUrl.length || !$buyNowFlag.length) {
            return;
        }

        function resetBuyNowState() {
            $returnUrl.val('');
            $buyNowFlag.val('0');
            if (originalAction) {
                $form.attr('action', originalAction);
            }
            syncButtonState();
        }

        function showSizeRequiredMessage() {
            if ($sizeRequiredMessage.length) {
                $sizeRequiredMessage.removeAttr('hidden').show();
            }
        }

        function hideSizeRequiredMessage() {
            if ($sizeRequiredMessage.length) {
                $sizeRequiredMessage.attr('hidden', 'hidden').hide();
            }
        }

        function hasSelectedSize() {
            var $sizeSwatch = $form.find('.swatch-attribute[data-attribute-code="size"]').first(),
                $sizeField = $(),
                $sizeSelect,
                selectedValue;

            if ($sizeSwatch.length) {
                if ($sizeSwatch.find('.swatch-option.selected').length) {
                    return true;
                }

                $sizeSelect = $sizeSwatch.find('select, input.super-attribute-select').first();
                if (!$sizeSelect.length) {
                    $sizeSelect = $form.find('select[name^="super_attribute"]').first();
                }

                selectedValue = $sizeSelect.length ? $.trim(String($sizeSelect.val() || '')) : '';
                return selectedValue !== '';
            }

            $form.find('select.product-custom-option').each(function () {
                var $select = $(this),
                    $field = $select.closest('.field'),
                    labelText = $.trim($field.find('.label').first().text()).toLowerCase();

                if (labelText.indexOf('size') !== -1 || labelText.indexOf('kích cỡ') !== -1 || labelText.indexOf('kích thước') !== -1) {
                    $sizeField = $field;
                    return false;
                }

                return true;
            });

            if (!$sizeField.length) {
                $sizeField = $form.find('.field[data-attribute-code="size"]').first();
            }

            if (!$sizeField.length) {
                return true;
            }

            $sizeSelect = $sizeField.find('select').first();
            if (!$sizeSelect.length) {
                return true;
            }

            selectedValue = $.trim(String($sizeSelect.val() || ''));
            return selectedValue !== '';
        }

        function setButtonSizeLock($button, lock) {
            if (!$button.length) {
                return;
            }

            if (lock) {
                $button.attr('data-size-lock', '1')
                    .prop('disabled', false)
                    .removeAttr('disabled')
                    .addClass('fashionstore-size-lock')
                    .attr('aria-disabled', 'true');

                return;
            }

            if ($button.attr('data-size-lock') === '1') {
                $button.removeAttr('data-size-lock data-size-lock-prev-disabled')
                    .removeClass('fashionstore-size-lock')
                    .attr('aria-disabled', 'false');
            }

            if (!$button.attr('data-size-lock')) {
                $button.prop('disabled', false);
            }
        }

        function syncButtonState() {
            var sizeMissing = !hasSelectedSize();
            setButtonSizeLock($buyNowButton, sizeMissing);
            setButtonSizeLock($addToCartButton, sizeMissing);

            if (!sizeMissing) {
                hideSizeRequiredMessage();
            }
        }

        function clearInitialSizeSelection() {
            var $sizeSwatch,
                $sizeSelect;

            if ($form.attr('data-size-selection-reset') === '1') {
                return;
            }

            $form.attr('data-size-selection-reset', '1');

            $sizeSwatch = $form.find('.swatch-attribute[data-attribute-code="size"]').first();
            if ($sizeSwatch.length) {
                $sizeSwatch.find('.swatch-option.selected').removeClass('selected');
                $sizeSwatch.find('input.super-attribute-select, select').each(function () {
                    $(this).val('').trigger('change');
                });
            }

            $form.find('select.product-custom-option').each(function () {
                var $select = $(this),
                    $field = $select.closest('.field'),
                    labelText = $.trim($field.find('.label').first().text()).toLowerCase();

                if (labelText.indexOf('size') !== -1 || labelText.indexOf('kích cỡ') !== -1 || labelText.indexOf('kích thước') !== -1) {
                    $select.val('').trigger('change');
                }
            });

            $sizeSelect = $form.find('select[name^="super_attribute"]').first();
            if ($sizeSelect.length) {
                $sizeSelect.val('').trigger('change');
            }
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

        function enhanceSizeAsButtons() {
            var $sizeField = $(),
                $sizeSelect,
                $renderTarget,
                $buttonList,
                validOptions;

            $form.find('select.product-custom-option').each(function () {
                var $select = $(this),
                    $field = $select.closest('.field'),
                    labelText = $.trim($field.find('.label').first().text()).toLowerCase();

                if (labelText.indexOf('size') !== -1 || labelText.indexOf('kích cỡ') !== -1 || labelText.indexOf('kích thước') !== -1) {
                    $sizeField = $field;
                    return false;
                }

                return true;
            });

            if (!$sizeField.length) {
                return;
            }

            $sizeSelect = $sizeField.find('select').first();
            if (!$sizeSelect.length) {
                return;
            }

            validOptions = $sizeSelect.find('option').filter(function () {
                return !!$(this).val();
            });

            if (validOptions.length <= 1) {
                if (validOptions.length === 1) {
                    $sizeSelect.val(validOptions.first().val()).trigger('change');
                }
                $sizeField.hide();
                return;
            }

            $sizeField.show();

            $sizeSelect.addClass('fashionstore-size-select-hidden').hide();

            $renderTarget = $sizeField.find('.control').first();
            if (!$renderTarget.length) {
                $renderTarget = $sizeField;
            }

            $renderTarget.find('.fashionstore-size-options').remove();
            $buttonList = $('<div class="fashionstore-size-options" data-role="size-options"></div>');

            $sizeSelect.find('option').each(function () {
                var value = $(this).val(),
                    label = $.trim($(this).text()),
                    $button;

                if (!value) {
                    return;
                }

                $button = $('<button type="button" class="fashionstore-size-option"></button>');
                $button.text(label).attr('data-value', value);

                if ($(this).is(':disabled')) {
                    $button.prop('disabled', true).addClass('disabled');
                }

                if ($sizeSelect.val() === value) {
                    $button.addClass('selected');
                }

                $buttonList.append($button);
            });

            $renderTarget.append($buttonList);

            $buttonList.on('click', '.fashionstore-size-option', function () {
                var value = $(this).attr('data-value');

                if ($(this).prop('disabled')) {
                    return;
                }

                $sizeSelect.val(value).trigger('change');
            });

            $sizeSelect.off('change.fashionstoreSize').on('change.fashionstoreSize', function () {
                var selectedValue = $(this).val();

                $buttonList.find('.fashionstore-size-option').removeClass('selected');
                $buttonList.find('.fashionstore-size-option[data-value="' + selectedValue + '"]').addClass('selected');
                syncButtonState();
            });
        }

        function bindShareModal() {
            var $wrap = $form.find('[data-role="share-wrap"]'),
                namespace = '.fashionstoreShare';

            if ($form.find('[data-role="share-wrap"][data-inline-share-handler="1"]').length) {
                return;
            }

            function closeAllShareModal() {
                $form.find('[data-role="share-modal"]')
                    .attr('hidden', 'hidden')
                    .removeClass('is-open');
                $form.find('[data-role="share-toggle"]').attr('aria-expanded', 'false');
                $('body').removeClass('fashionstore-share-open');
            }

            if (!$wrap.length) {
                return;
            }

            $(document).off('click' + namespace, '[data-role="share-toggle"]');
            $(document).off('click' + namespace, '[data-role="share-close"]');
            $(document).off('click' + namespace, '[data-role="share-close-btn"]');
            $(document).off('click' + namespace, '[data-role="share-modal-dialog"]');
            $(document).off('keydown' + namespace);

            $(document).on('click' + namespace, '[data-role="share-toggle"]', function (event) {
                var $currentToggle = $(this),
                    $currentWrap = $currentToggle.closest('[data-role="share-wrap"]'),
                    $currentModal = $currentWrap.find('[data-role="share-modal"]'),
                    isOpen;

                event.preventDefault();
                event.stopPropagation();

                isOpen = !$currentModal.is('[hidden]') && $currentModal.hasClass('is-open');
                if (isOpen) {
                    closeAllShareModal();
                } else {
                    closeAllShareModal();
                    $currentModal.removeAttr('hidden').addClass('is-open');
                    $('body').addClass('fashionstore-share-open');
                    $currentToggle.attr('aria-expanded', 'true');
                }
            });

            $(document).on('click' + namespace, '[data-role="share-close"], [data-role="share-close-btn"]', function (event) {
                event.preventDefault();
                closeAllShareModal();
            });

            $(document).on('click' + namespace, '[data-role="share-modal-dialog"]', function (event) {
                event.stopPropagation();
            });

            $(document).on('keydown' + namespace, function (event) {
                if (event.key === 'Escape') {
                    closeAllShareModal();
                }
            });
        }

        clearInitialSizeSelection();
        enhanceSizeAsButtons();
        bindShareModal();
        syncButtonState();
        setTimeout(enhanceSizeAsButtons, 120);
        setTimeout(enhanceSizeAsButtons, 420);
        setTimeout(syncButtonState, 120);
        setTimeout(syncButtonState, 420);

        $buyNowButton.on('click', function (event) {
            event.preventDefault();

            if (!hasSelectedSize()) {
                showSizeRequiredMessage();
                syncButtonState();
                return;
            }

            if ($(this).prop('disabled') && $(this).attr('data-size-lock') !== '1') {
                return;
            }

            resetBuyNowState();

            if (!isFormValid()) {
                return;
            }

            submitBuyNow();
        });

        $form.on('click', '[data-role="qty-increase"]', function () {
            var $qtyInput = $form.find('#qty'),
                currentQty;

            if (!$qtyInput.length) {
                return;
            }

            currentQty = parseFloat($qtyInput.val()) || 1;
            $qtyInput.val(currentQty + 1).trigger('change');
        });

        $form.on('click', '[data-role="qty-decrease"]', function () {
            var $qtyInput = $form.find('#qty'),
                minQty,
                currentQty,
                nextQty;

            if (!$qtyInput.length) {
                return;
            }

            minQty = parseFloat($qtyInput.attr('min')) || 1;
            currentQty = parseFloat($qtyInput.val()) || minQty;
            nextQty = Math.max(minQty, currentQty - 1);
            $qtyInput.val(nextQty).trigger('change');
        });

        $form.on('submit', function (event) {
            if (!hasSelectedSize()) {
                event.preventDefault();
                showSizeRequiredMessage();
                syncButtonState();
                return false;
            }

            resetBuyNowState();
            return true;
        });

        $form.on('click', '#product-addtocart-button', function (event) {
            $buyNowFlag.val('0');

            if (!hasSelectedSize()) {
                event.preventDefault();
                showSizeRequiredMessage();
                syncButtonState();
            }
        });

        $form.on('change', '.swatch-attribute[data-attribute-code="size"] .swatch-input, .swatch-attribute[data-attribute-code="size"] select, .field[data-attribute-code="size"] select, select.product-custom-option', function () {
            hideSizeRequiredMessage();
            syncButtonState();
        });

        $form.on('contentUpdated', function () {
            enhanceSizeAsButtons();
            bindShareModal();
            syncButtonState();
        });

        hideSizeRequiredMessage();
        syncButtonState();
    };
});
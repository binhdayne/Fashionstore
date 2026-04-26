        require([
            'jquery',
            'mage/url',
            'mage/storage',
            'Magento_Checkout/js/model/step-navigator',
            'uiRegistry',
            'Magento_Checkout/js/model/quote',
            'Magento_Checkout/js/model/totals',
            'Magento_Catalog/js/price-utils',
            'Magento_Checkout/js/model/shipping-rate-service',
            'Magento_Checkout/js/action/select-shipping-method',
            'Magento_Customer/js/model/address-list',
            'Magento_Checkout/js/action/select-shipping-address',
            'Magento_Checkout/js/action/set-shipping-information'
        ], function (
            $,
            urlBuilder,
            storage,
            stepNavigator,
            registry,
            quote,
            totals,
            priceUtils,
            shippingRateService,
            selectShippingMethodAction,
            addressList,
            selectShippingAddressAction,
            setShippingInformationAction
        ) {
            var shippingComponent,
                billingComponent,
                globalPlaceOrderButton,
                paymentHashGuardId,
                deliveryOptionStorageKey = 'fs_checkout_delivery_method',
                deliveryExpressFee = 30000,
                selectedDeliveryMethod = 'standard';

            function persistDeliveryMethodToBackend() {
                var endpointUrl = urlBuilder.build('fashionstore_cartoptions/cart/setdeliverymethod'),
                    payload = 'delivery_method=' + encodeURIComponent(selectedDeliveryMethod);

                return storage.post(
                    endpointUrl,
                    payload,
                    false,
                    'application/x-www-form-urlencoded; charset=UTF-8'
                );
            }

            function formatDisplayPrice(amount) {
                var value = Number(amount);

                if (!isFinite(value)) {
                    value = 0;
                }

                return priceUtils.formatPrice(value, window.checkoutConfig.priceFormat);
            }

            function escapeHtml(value) {
                return $('<div/>').text(value || '').html();
            }

            function formatDateVi(date) {
                var day = String(date.getDate()).padStart(2, '0'),
                    month = String(date.getMonth() + 1).padStart(2, '0'),
                    year = date.getFullYear();

                return day + '/' + month + '/' + year;
            }

            function addDays(date, days) {
                var next = new Date(date.getTime());
                next.setDate(next.getDate() + days);
                return next;
            }

            function getBackendGrandTotal() {
                var grandTotalSegment = totals && typeof totals.getSegment === 'function'
                    ? totals.getSegment('grand_total')
                    : null,
                    value = grandTotalSegment && grandTotalSegment.value !== undefined
                        ? Number(grandTotalSegment.value)
                        : NaN;

                return isFinite(value) ? value : null;
            }

            function getBackendShippingAmount() {
                var shippingSegment = totals && typeof totals.getSegment === 'function'
                        ? totals.getSegment('shipping')
                        : null,
                    value = shippingSegment && shippingSegment.value !== undefined
                        ? Number(shippingSegment.value)
                        : 0;

                return isFinite(value) ? value : 0;
            }

            function getDeliveryFee() {
                return selectedDeliveryMethod === 'express' ? deliveryExpressFee : 0;
            }

            function getDeliveryDateText() {
                var now = new Date(),
                    fromDate,
                    toDate;

                if (selectedDeliveryMethod === 'express') {
                    return 'Trong ngày: ' + formatDateVi(now);
                }

                fromDate = addDays(now, 2);
                toDate = addDays(now, 5);

                return 'Đến giữa: ' + formatDateVi(fromDate) + ' - ' + formatDateVi(toDate);
            }

            function getShippingAddressHtml() {
                var address = quote && quote.shippingAddress ? quote.shippingAddress() : null,
                    fullName,
                    lines = [],
                    streetLines = [],
                    cityLine = [];

                if (!address) {
                    return '<span>Vui lòng nhập địa chỉ giao hàng.</span>';
                }

                fullName = $.trim([address.firstname || '', address.lastname || ''].join(' '));
                if (fullName) {
                    lines.push('<span>' + escapeHtml(fullName.toUpperCase()) + '</span>');
                }

                if (address.region && address.region.region) {
                    lines.push('<span>' + escapeHtml(address.region.region) + '</span>');
                }

                if ($.isArray(address.street)) {
                    streetLines = address.street.filter(function (part) {
                        return $.trim(part || '') !== '';
                    });
                } else if (address.street) {
                    streetLines = [address.street];
                }

                if (streetLines.length) {
                    lines.push('<span>' + escapeHtml(streetLines.join(', ')) + '</span>');
                }

                if (address.city) {
                    cityLine.push(address.city);
                }

                if (cityLine.length) {
                    lines.push('<span>' + escapeHtml(cityLine.join(', ')) + '</span>');
                }

                if (address.telephone) {
                    lines.push('<span>' + escapeHtml(address.telephone) + '</span>');
                }

                if (!lines.length) {
                    return '<span>Vui lòng nhập địa chỉ giao hàng.</span>';
                }

                return lines.join('');
            }

            function updateDeliveryPanelContent() {
                var addressNode = $('#fs-delivery-address-lines'),
                    dateNode = $('#fs-delivery-date-value');

                if (addressNode.length) {
                    addressNode.html(getShippingAddressHtml());
                }

                if (dateNode.length) {
                    dateNode.text(getDeliveryDateText());
                }
            }

            function renderDeliveryFeeRow() {
                var totalsBody = $('.opc-block-summary .table-totals tbody').first(),
                    fee = getDeliveryFee(),
                    shippingRow,
                    shippingPriceNode;

                if (!totalsBody.length) {
                    return;
                }

                totalsBody.find('#fs-delivery-fee-row').remove();

                shippingRow = totalsBody.find('tr.totals.shipping, tr.totals.shipping.excl, tr[class*="shipping"]').first();
                if (!shippingRow.length) {
                    return;
                }

                shippingPriceNode = shippingRow.find('td.amount .price').first();
                if (!shippingPriceNode.length) {
                    shippingPriceNode = shippingRow.find('td.amount').first();
                }

                shippingPriceNode.text(formatDisplayPrice(fee > 0 ? fee : 0));
            }

            function applyGrandTotalWithDeliveryFee() {
                var backendGrandTotal = getBackendGrandTotal(),
                    backendShippingAmount = getBackendShippingAmount(),
                    fee = getDeliveryFee(),
                    displayGrandTotal;

                if (backendGrandTotal === null) {
                    return;
                }

                displayGrandTotal = backendGrandTotal - backendShippingAmount + (fee > 0 ? fee : 0);

                $('.opc-block-summary tr.grand.totals td.amount .price').text(formatDisplayPrice(displayGrandTotal));
            }

            function updateDeliveryMethodNote() {
                var note = $('#fs-delivery-method-note'),
                    fee = getDeliveryFee();

                if (!note.length) {
                    return;
                }

                if (fee > 0) {
                    note.text('Đã áp dụng phụ thu giao hàng hỏa tốc: ' + formatDisplayPrice(fee));
                    return;
                }

                note.text('Giao hàng thường không tính thêm phí.');
            }

            function updateDeliveryFeePresentation() {
                renderDeliveryFeeRow();
                applyGrandTotalWithDeliveryFee();
                updateDeliveryMethodNote();
                updateDeliveryPanelContent();
            }

            function tuneOrderSummaryDisplay() {
                var summaryTitle = $('.opc-sidebar .opc-block-summary > .title').first(),
                    itemCount = $('.opc-sidebar .opc-block-summary .minicart-items .product-item').length,
                    table = $('.opc-sidebar .opc-block-summary .table-totals').first();

                if (summaryTitle.length) {
                    summaryTitle.text('Tổng đơn hàng');
                    summaryTitle.attr('data-order-count', String(itemCount || 0) + ' Sản phẩm');
                }

                if (table.length) {
                    table.find('tr').each(function () {
                        var mark = $(this).find('.mark .label, .mark').first(),
                            text = $.trim(mark.text()).toLowerCase();

                        if (text.indexOf('cart subtotal') > -1 || text.indexOf('subtotal') > -1) {
                            mark.text('Tạm tính');
                        }

                        if (text.indexOf('grand total') > -1 || text.indexOf('tổng cộng') > -1) {
                            mark.text('Tổng đơn đặt hàng');
                        }

                        if (text.indexOf('tax') > -1 || text.indexOf('thuế') > -1) {
                            mark.text('Đã bao gồm thuế giá trị gia tăng');
                        }
                    });
                }

                if (!$('.opc-sidebar .fs-coupon-row').length && $('.opc-sidebar .opc-block-summary').length) {
                    $('.opc-sidebar .opc-block-summary').append('<div class="fs-coupon-row" aria-hidden="true"></div>');
                }
            }

            function syncDeliveryMethodToCheckoutProvider() {
                var checkoutProvider = registry.get('checkoutProvider');

                if (!checkoutProvider || typeof checkoutProvider.set !== 'function') {
                    return;
                }

                checkoutProvider.set('shippingAddress.custom_attributes.fs_delivery_method', selectedDeliveryMethod);
            }

            function ensureDeliveryMethodBox() {
                var paymentMethods = $('#payment .payment-methods').first(),
                    container,
                    selectedValue;

                if (!paymentMethods.length || $('#fs-delivery-method-box').length) {
                    return;
                }

                container = $(
                    '<div id="fs-delivery-method-box" class="fs-delivery-method-box">' +
                        '<div class="fs-delivery-head">' +
                            '<span class="fs-delivery-method-label">1.Tùy chọn giao hàng <span class="fs-delivery-method-status">✓</span></span>' +
                            '<button type="button" class="fs-delivery-change">Thay đổi</button>' +
                        '</div>' +
                        '<div class="fs-delivery-body">' +
                            '<div class="fs-delivery-section">' +
                                '<h3 class="fs-delivery-section-title">Giao đến địa chỉ</h3>' +
                                '<div id="fs-delivery-address-lines" class="fs-delivery-address-lines"></div>' +
                            '</div>' +
                            '<div class="fs-delivery-section">' +
                                '<h3 class="fs-delivery-section-title">Ngày giao hàng</h3>' +
                                '<p id="fs-delivery-date-value" class="fs-delivery-date-value"></p>' +
                            '</div>' +
                            '<div class="fs-delivery-method-options" role="radiogroup" aria-label="Phương thức giao hàng">' +
                            '<button type="button" class="fs-delivery-method-option" data-method="standard" role="radio" aria-checked="false">' +
                                '<span class="fs-delivery-method-option-title">Giao hàng thường</span>' +
                                '<span class="fs-delivery-method-option-desc">Sẽ đến trong 2-5 ngày</span>' +
                            '</button>' +
                            '<button type="button" class="fs-delivery-method-option" data-method="express" role="radio" aria-checked="false">' +
                                '<span class="fs-delivery-method-option-title">Giao hàng hỏa tốc</span>' +
                                '<span class="fs-delivery-method-option-desc">Sẽ đến trong 1-4 tiếng ở nội thành và trong ngày ở liên tỉnh</span>' +
                            '</button>' +
                            '</div>' +
                        '</div>' +
                        '<p id="fs-delivery-method-note" class="fs-delivery-method-note"></p>' +
                    '</div>'
                );

                paymentMethods.before(container);

                selectedValue = window.localStorage ? window.localStorage.getItem(deliveryOptionStorageKey) : null;
                if (selectedValue === 'standard' || selectedValue === 'express') {
                    selectedDeliveryMethod = selectedValue;
                }

                function syncDeliveryMethodOptions() {
                    $('#fs-delivery-method-box .fs-delivery-method-option').each(function () {
                        var option = $(this),
                            isActive = option.attr('data-method') === selectedDeliveryMethod;

                        option.toggleClass('is-active', isActive);
                        option.attr('aria-checked', isActive ? 'true' : 'false');
                    });
                }

                $('#fs-delivery-method-box .fs-delivery-method-option').on('click', function () {
                    selectedDeliveryMethod = $(this).attr('data-method') === 'express' ? 'express' : 'standard';

                    if (window.localStorage) {
                        window.localStorage.setItem(deliveryOptionStorageKey, selectedDeliveryMethod);
                    }

                    syncDeliveryMethodOptions();
                    syncDeliveryMethodToCheckoutProvider();
                    updateDeliveryFeePresentation();
                    persistDeliveryMethodToBackend();
                });

                $('#fs-delivery-method-box .fs-delivery-change').on('click', function () {
                    $('#fs-delivery-method-box').toggleClass('is-editing');
                });

                syncDeliveryMethodOptions();
                syncDeliveryMethodToCheckoutProvider();
                updateDeliveryPanelContent();
                updateDeliveryFeePresentation();
                persistDeliveryMethodToBackend();
            }

            function getActivePlaceOrderButton() {
                var activeMethod = $('#payment .payment-method._active'),
                    checkedInput = $('#payment input[name="payment[method]"]:checked'),
                    targetMethod = activeMethod;

                if (!targetMethod.length && checkedInput.length) {
                    targetMethod = checkedInput.closest('.payment-method');
                }

                if (!targetMethod.length) {
                    return $();
                }

                return targetMethod.find('.action.primary.checkout, button[title="Place Order"]')
                    .filter(function () {
                        return !$(this).is(':disabled');
                    })
                    .first();
            }

            function syncGlobalPlaceOrderState() {
                var nativeButton = getActivePlaceOrderButton();

                if (!globalPlaceOrderButton || !globalPlaceOrderButton.length) {
                    return;
                }

                globalPlaceOrderButton.prop('disabled', !nativeButton.length);
            }

            function initGlobalPlaceOrder() {
                var paymentContainer = $('#payment .payment-methods').first(),
                    wrap;

                if (!paymentContainer.length) {
                    paymentContainer = $('#payment').first();
                }

                if (!paymentContainer.length || $('#fs-global-place-order-wrap').length) {
                    syncGlobalPlaceOrderState();
                    return;
                }

                wrap = $('<div id="fs-global-place-order-wrap" class="fs-global-place-order-wrap"></div>');
                globalPlaceOrderButton = $('<button type="button" class="action primary fs-global-place-order">Đặt hàng</button>');
                wrap.append(globalPlaceOrderButton);
                paymentContainer.append(wrap);

                globalPlaceOrderButton.on('click', function (event) {
                    var nativeButton = getActivePlaceOrderButton(),
                        syncRequest;

                    event.preventDefault();
                    if (!nativeButton.length) {
                        return;
                    }

                    if (!ensureShippingAddressReady()) {
                        return;
                    }

                    syncRequest = syncShippingInformationForTotals();

                    if (syncRequest && typeof syncRequest.always === 'function') {
                        syncRequest.always(function () {
                            nativeButton.trigger('click');
                        });
                        return;
                    }

                    window.setTimeout(function () {
                        nativeButton.trigger('click');
                    }, 250);
                });

                $(document).on('change click', '#payment input[name="payment[method]"], #payment .payment-method-title, #payment .payment-method-content', function () {
                    window.setTimeout(syncGlobalPlaceOrderState, 0);
                });

                window.setTimeout(syncGlobalPlaceOrderState, 200);
                window.setTimeout(syncGlobalPlaceOrderState, 1000);
            }

            function ensurePaymentStepVisible() {
                if (billingComponent && typeof billingComponent.isVisible === 'function') {
                    billingComponent.isVisible(true);
                }

                $('.fs-checkout-breadcrumb[data-step="payment"]')
                    .addClass('is-active')
                    .removeClass('is-disabled');

                initGlobalPlaceOrder();
                ensureDeliveryMethodBox();
                syncGlobalPlaceOrderState();
            }

            function setCheckoutActionMode(mode) {
                var stepActions = $('.fs-checkout-step-actions');

                if (!stepActions.length) {
                    return;
                }

                stepActions.attr('data-mode', mode);
                stepActions.find('.fs-checkout-action').removeClass('is-active');

                if (mode === 'address') {
                    stepActions.find('.fs-checkout-action-address').addClass('is-active');
                    return;
                }

                stepActions.find('.fs-checkout-action-payment').addClass('is-active');
            }

            function toggleAddressEditor(isEnabled) {
                $('#shipping, #opc-shipping_method')
                    .find('input, select, textarea, button')
                    .prop('disabled', !isEnabled);

                $('#shipping, #opc-shipping_method')[isEnabled ? 'show' : 'hide']();

                $('#payment .billing-address-same-as-shipping-block input[type="checkbox"]')
                    .prop('checked', true)
                    .prop('disabled', !isEnabled)
                    .trigger('change');

                $('#payment .billing-address-form, #payment .billing-address-details')
                    .find('input, select, textarea, button')
                    .prop('disabled', !isEnabled);

                $('#payment .billing-address-form, ' +
                    '#payment .billing-address-same-as-shipping-block, ' +
                    '#payment .billing-address-details .action-edit, ' +
                    '#payment .action-update')[isEnabled ? 'show' : 'hide']();

                $('.billing-address-form, .billing-address-same-as-shipping-block, .billing-address-details .action-edit, .action-update')[isEnabled ? 'show' : 'hide']();
                setCheckoutActionMode(isEnabled ? 'address' : 'payment');
            }

            function syncShippingInformationForTotals() {
                var ratesObservable,
                    availableRates,
                    firstRate,
                    selectedShippingMethod;

                if (!shippingComponent || typeof shippingComponent.setShippingInformation !== 'function') {
                    return;
                }

                if (typeof quote.isVirtual === 'function' && quote.isVirtual()) {
                    return;
                }

                if (!ensureShippingAddressReady()) {
                    return;
                }

                selectedShippingMethod = quote.shippingMethod ? quote.shippingMethod() : null;

                if (!selectedShippingMethod) {
                    ratesObservable = shippingRateService.getShippingRates();
                    availableRates = ratesObservable && typeof ratesObservable === 'function' ? ratesObservable() : [];

                    if (availableRates && availableRates.length) {
                        firstRate = availableRates[0];
                        if (firstRate) {
                            selectShippingMethodAction(firstRate);
                        }
                    }

                    selectedShippingMethod = quote.shippingMethod ? quote.shippingMethod() : null;
                    if (!selectedShippingMethod) {
                        return null;
                    }
                }

                try {
                    return setShippingInformationAction();
                } catch (error) {
                    return null;
                }
            }

            function ensureShippingAddressReady() {
                var currentAddress,
                    availableAddresses,
                    firstAddress;

                if (typeof quote.isVirtual === 'function' && quote.isVirtual()) {
                    return true;
                }

                currentAddress = quote.shippingAddress && typeof quote.shippingAddress === 'function'
                    ? quote.shippingAddress()
                    : null;

                if (currentAddress && currentAddress.firstname) {
                    return true;
                }

                availableAddresses = addressList && typeof addressList === 'function' ? addressList() : [];
                if (!availableAddresses || !availableAddresses.length) {
                    return false;
                }

                firstAddress = availableAddresses[0];
                if (firstAddress) {
                    selectShippingAddressAction(firstAddress);
                    return true;
                }

                return false;
            }

            function enforcePaymentHash() {
                if (window.location.hash === '#payment') {
                    return;
                }

                if (window.history && typeof window.history.replaceState === 'function') {
                    window.history.replaceState(null, '', window.location.pathname + window.location.search + '#payment');
                } else {
                    window.location.hash = '#payment';
                }
            }

            function startPaymentHashGuard() {
                var attempts = 0;

                if (paymentHashGuardId) {
                    window.clearInterval(paymentHashGuardId);
                }

                paymentHashGuardId = window.setInterval(function () {
                    attempts += 1;
                    enforcePaymentHash();

                    if (attempts >= 40) {
                        window.clearInterval(paymentHashGuardId);
                        paymentHashGuardId = null;
                    }
                }, 250);
            }

            function navigateToPaymentStep() {
                syncShippingInformationForTotals();
                ensurePaymentStepVisible();
                stepNavigator.navigateTo('payment');

                enforcePaymentHash();
                toggleAddressEditor(false);

                scrollToStep('#payment');
            }

            function openAddressEditor() {
                ensurePaymentStepVisible();
                enforcePaymentHash();
                toggleAddressEditor(true);
                scrollToStep('#shipping');
            }

            function scrollToStep(stepSelector) {
                var stepElement = $(stepSelector);

                if (!stepElement.length) {
                    return;
                }

                $('html, body').animate({
                    scrollTop: Math.max(stepElement.offset().top - 16, 0)
                }, 180);
            }

            registry.async('checkout.steps.shipping-step')(function (component) {
                shippingComponent = component;
                syncShippingInformationForTotals();
            });

            registry.async('checkout.steps.billing-step')(function (component) {
                billingComponent = component;
                ensurePaymentStepVisible();
            });

            if (quote.shippingMethod && typeof quote.shippingMethod.subscribe === 'function') {
                quote.shippingMethod.subscribe(function () {
                    syncShippingInformationForTotals();
                });
            }

            if (quote.shippingAddress && typeof quote.shippingAddress.subscribe === 'function') {
                quote.shippingAddress.subscribe(function () {
                    syncShippingInformationForTotals();
                    updateDeliveryPanelContent();
                });
            }

            if (shippingRateService && typeof shippingRateService.getShippingRates === 'function') {
                (function () {
                    var ratesObservable = shippingRateService.getShippingRates();
                    if (ratesObservable && typeof ratesObservable.subscribe === 'function') {
                        ratesObservable.subscribe(function () {
                            syncShippingInformationForTotals();
                        });
                    }
                })();
            }

            if (totals && typeof totals.totals === 'function') {
                totals.totals.subscribe(function () {
                    updateDeliveryFeePresentation();
                });
            }

            $(document).on('click', '.fs-checkout-breadcrumb[data-step="payment"] .fs-checkout-breadcrumb-button', function () {
                navigateToPaymentStep();
            });

            $(document).on('click', '.fs-checkout-action-address', function () {
                openAddressEditor();
            });

            $(document).on('click', '.fs-checkout-action-payment', function () {
                navigateToPaymentStep();
            });

            $(document).on('click', 'a[href="#shipping"], a[href$="#shipping"]', function (event) {
                event.preventDefault();
                openAddressEditor();
            });

            $(window).on('hashchange', function () {
                enforcePaymentHash();
                toggleAddressEditor(false);
                window.setTimeout(ensurePaymentStepVisible, 0);
            });

            $(document).ready(function () {
                navigateToPaymentStep();
                startPaymentHashGuard();
                toggleAddressEditor(false);
                ensureDeliveryMethodBox();
                updateDeliveryFeePresentation();
                tuneOrderSummaryDisplay();
                window.setTimeout(enforcePaymentHash, 100);
                window.setTimeout(enforcePaymentHash, 400);
                window.setTimeout(enforcePaymentHash, 1200);
                window.setTimeout(function () {
                    toggleAddressEditor(false);
                }, 300);
                window.setTimeout(function () {
                    toggleAddressEditor(false);
                }, 1000);
                window.setInterval(updateDeliveryFeePresentation, 1200);
                window.setInterval(tuneOrderSummaryDisplay, 1200);
                window.setTimeout(ensurePaymentStepVisible, 400);
                window.setTimeout(ensurePaymentStepVisible, 1200);
                window.setTimeout(ensurePaymentStepVisible, 3000);
                window.setTimeout(ensurePaymentStepVisible, 5000);
            });
        });
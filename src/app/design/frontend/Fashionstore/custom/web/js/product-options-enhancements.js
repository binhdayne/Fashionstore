(function () {
    'use strict';

    function isProductPage() {
        return document.body.classList.contains('catalog-product-view');
    }

    function normalizeText(value) {
        return (value || '')
            .toLowerCase()
            .trim()
            .replace(/\s+/g, ' ')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function getFieldLabelText(select) {
        var id = select.getAttribute('id') || '';
        var labelEl = id ? document.querySelector('label[for="' + id + '"]') : null;
        var text = '';

        if (labelEl) {
            text = labelEl.textContent || '';
        } else {
            var field = select.closest('.field');
            if (field) {
                var directLabel = field.querySelector('> .label, .label');
                if (directLabel) {
                    text = directLabel.textContent || '';
                }
            }
        }

        return normalizeText(text);
    }

    function isSizeSelect(select) {
        var normalized = getFieldLabelText(select);
        var name = (select.getAttribute('name') || '').toLowerCase();

        if (normalized.indexOf('size') !== -1 || normalized.indexOf('kich co') !== -1) {
            return true;
        }

        return name.indexOf('size') !== -1;
    }

    function clearSizeWrapper(select) {
        var next = select.nextElementSibling;
        if (next && next.classList.contains('fs-size-options')) {
            next.remove();
        }
    }

    function syncActiveSizeButton(select) {
        var wrapper = select.nextElementSibling;
        if (!wrapper || !wrapper.classList.contains('fs-size-options')) {
            return;
        }

        var value = String(select.value || '');
        wrapper.querySelectorAll('.fs-size-option').forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-size-value') === value);
        });
    }

    function renderSizeButtons(select) {
        clearSizeWrapper(select);

        var wrapper = document.createElement('div');
        wrapper.className = 'fs-size-options';
        var currentValue = String(select.value || '');
        var buttonCount = 0;

        Array.prototype.forEach.call(select.options || [], function (option) {
            var value = String(option.value || '');
            var text = (option.text || '').trim();

            if (!value || !text || /^(-{2,}|please select|vui long chon)/i.test(text)) {
                return;
            }

            buttonCount += 1;

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'fs-size-option';
            button.textContent = text;
            button.setAttribute('data-size-value', value);

            if (value === currentValue) {
                button.classList.add('is-active');
            }

            if (option.disabled) {
                button.disabled = true;
                button.classList.add('is-disabled');
            }

            button.addEventListener('click', function () {
                if (button.disabled) {
                    return;
                }

                select.value = value;
                if (window.jQuery) {
                    window.jQuery(select).trigger('change');
                } else {
                    select.dispatchEvent(new Event('change', {bubbles: true}));
                }
                syncActiveSizeButton(select);
            });

            wrapper.appendChild(button);
        });

        if (!buttonCount) {
            select.classList.remove('fs-size-select--hidden');
            return;
        }

        select.classList.add('fs-size-select--hidden');
        select.insertAdjacentElement('afterend', wrapper);
    }

    function enhanceSizeSelectors() {
        document.querySelectorAll('.catalog-product-view .product-options-wrapper select').forEach(function (select) {
            if (isSizeSelect(select)) {
                renderSizeButtons(select);
                select.addEventListener('change', function () {
                    syncActiveSizeButton(select);
                    window.setTimeout(function () {
                        renderSizeButtons(select);
                    }, 20);
                }, {once: true});
            }
        });
    }

    function clampQty(value, min) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed < min) {
            return min;
        }
        return parsed;
    }

    function createQtyButton(action, label, text) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'fs-qty-btn fs-qty-btn--' + action;
        button.setAttribute('data-qty-action', action);
        button.setAttribute('aria-label', label);
        button.textContent = text;
        return button;
    }

    function enhanceQtyInput() {
        var qtyInput = document.querySelector('.catalog-product-view .box-tocart input.qty, .catalog-product-view .box-tocart #qty');
        if (!qtyInput || qtyInput.closest('.fs-qty-control')) {
            return;
        }

        var min = parseInt(qtyInput.getAttribute('min'), 10);
        if (isNaN(min) || min < 1) {
            min = 1;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'fs-qty-control';
        var decrement = createQtyButton('decrement', 'Decrease quantity', '-');
        var increment = createQtyButton('increment', 'Increase quantity', '+');

        qtyInput.setAttribute('inputmode', 'numeric');
        qtyInput.parentNode.insertBefore(wrapper, qtyInput);
        wrapper.appendChild(decrement);
        wrapper.appendChild(qtyInput);
        wrapper.appendChild(increment);

        decrement.addEventListener('click', function () {
            var current = clampQty(qtyInput.value, min);
            qtyInput.value = Math.max(min, current - 1);
            qtyInput.dispatchEvent(new Event('change', {bubbles: true}));
        });

        increment.addEventListener('click', function () {
            var current = clampQty(qtyInput.value, min);
            qtyInput.value = current + 1;
            qtyInput.dispatchEvent(new Event('change', {bubbles: true}));
        });

        qtyInput.addEventListener('input', function () {
            qtyInput.value = clampQty(qtyInput.value, min);
        });

        qtyInput.addEventListener('blur', function () {
            qtyInput.value = clampQty(qtyInput.value, min);
        });
    }

    function init() {
        if (!isProductPage()) {
            return;
        }

        enhanceSizeSelectors();
        enhanceQtyInput();

        document.addEventListener('change', function (event) {
            var select = event.target.closest('.catalog-product-view .product-options-wrapper select');
            if (select && isSizeSelect(select)) {
                window.setTimeout(function () {
                    renderSizeButtons(select);
                }, 40);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

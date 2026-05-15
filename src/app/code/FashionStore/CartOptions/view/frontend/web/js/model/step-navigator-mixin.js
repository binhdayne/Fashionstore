define([], function () {
    'use strict';

    // Guard: chi chay tren trang checkout
    if (typeof window.checkoutConfig === 'undefined') {
        return function (target) { return target; };
    }

    function normalizeStep(code) {
        return code === 'shipping' ? 'payment' : code;
    }

    return function (target) {
        var originalHandleHash = target.handleHash,
            originalNavigateTo = target.navigateTo,
            originalSetHash = target.setHash;

        target.handleHash = function () {
            if (window.location.hash === '#shipping') {
                window.location.hash = '#payment';
            }
            return originalHandleHash.apply(this, arguments);
        };

        target.navigateTo = function (code, scrollToElementId) {
            return originalNavigateTo.call(this, normalizeStep(code), scrollToElementId);
        };

        target.setHash = function (hash) {
            return originalSetHash.call(this, normalizeStep(hash));
        };

        return target;
    };
});

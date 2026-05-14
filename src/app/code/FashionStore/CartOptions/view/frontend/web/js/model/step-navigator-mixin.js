define([], function () {
    'use strict';

    function replaceHash(hash) {
        var targetHash = hash.charAt(0) === '#' ? hash : '#' + hash;

        if (window.location.hash === targetHash) {
            return;
        }

        if (window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(
                null,
                '',
                window.location.pathname + window.location.search + targetHash
            );

            return;
        }

        window.location.hash = targetHash;
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
                replaceHash('#payment');
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

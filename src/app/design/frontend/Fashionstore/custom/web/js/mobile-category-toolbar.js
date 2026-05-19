(function () {
    'use strict';

    var mobileQuery = window.matchMedia('(max-width: 768px)');

    function isListingPage() {
        return document.body.classList.contains('catalog-category-view') ||
            document.body.classList.contains('catalogsearch-result-index');
    }

    function moveToolbarAfterRecommendation() {
        if (!isListingPage() || !mobileQuery.matches) {
            return;
        }

        var recommendation = document.querySelector('.fashionstore-recommendation');
        var priceBox = document.querySelector('.fs-price-box');

        if (!recommendation || !priceBox) {
            return;
        }

        var targetToolbar = priceBox.closest('.toolbar.toolbar-products');
        if (!targetToolbar) {
            targetToolbar = document.createElement('div');
            targetToolbar.className = 'toolbar toolbar-products';
            priceBox.parentNode.insertBefore(targetToolbar, priceBox);
            targetToolbar.appendChild(priceBox);
        }

        targetToolbar.classList.add('fs-mobile-toolbar');
        recommendation.insertAdjacentElement('afterend', targetToolbar);

        document.querySelectorAll('.toolbar-sorter, .sorter, .sorter-action, select.sorter-options').forEach(function (element) {
            element.setAttribute('hidden', 'hidden');
        });

        document.querySelectorAll('.toolbar.toolbar-products').forEach(function (toolbar) {
            if (toolbar !== targetToolbar && !toolbar.querySelector('.fs-price-box')) {
                toolbar.classList.add('fs-mobile-toolbar-empty');
            }
        });
    }

    function init() {
        moveToolbarAfterRecommendation();
        window.setTimeout(moveToolbarAfterRecommendation, 250);
        window.setTimeout(moveToolbarAfterRecommendation, 800);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', moveToolbarAfterRecommendation);
    } else if (mobileQuery.addListener) {
        mobileQuery.addListener(moveToolbarAfterRecommendation);
    }
}());

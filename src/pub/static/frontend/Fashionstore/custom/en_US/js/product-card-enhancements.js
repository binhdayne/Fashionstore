define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var galleryCache = {};
    var globalBound = false;

    function parseGallery($slider) {
        var raw = $slider.attr('data-gallery');

        if (!raw) {
            return [];
        }

        try {
            var list = JSON.parse(raw);
            return Array.isArray(list) ? list.filter(Boolean) : [];
        } catch (e) {
            return [];
        }
    }

    function applyImage($slider, nextIndex) {
        var images = parseGallery($slider);
        var $img = $slider.find('img.product-image-photo').first();

        if (!$img.length || images.length < 2) {
            return;
        }

        var normalizedIndex = (nextIndex + images.length) % images.length;
        $slider.attr('data-gallery-index', String(normalizedIndex));
        $img.attr('src', images[normalizedIndex]);
    }

    function loadGalleryBySku(sku) {
        if (!sku) {
            return $.Deferred().resolve([]).promise();
        }

        if (galleryCache[sku]) {
            return $.Deferred().resolve(galleryCache[sku]).promise();
        }

        return $.ajax({
            url: '/graphql',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                query: 'query ($sku: String!) { products(filter: { sku: { eq: $sku } }) { items { media_gallery { url } } } }',
                variables: {
                    sku: sku
                }
            })
        }).then(function (response) {
            var items = response && response.data && response.data.products ? response.data.products.items : [];
            var media = items && items[0] && items[0].media_gallery ? items[0].media_gallery : [];
            var urls = media.map(function (entry) {
                return entry && entry.url ? entry.url : null;
            }).filter(Boolean);

            urls = urls.filter(function (url, idx, arr) {
                return arr.indexOf(url) === idx;
            });
            galleryCache[sku] = urls;
            return urls;
        }, function () {
            galleryCache[sku] = [];
            return [];
        });
    }

    function initSlider($scope) {
        $scope.find('.product-photo-slider').each(function () {
            var $slider = $(this);
            var images = parseGallery($slider);

            if (images.length < 2) {
                $slider.addClass('single-image');
                return;
            }

            var $img = $slider.find('img.product-image-photo').first();
            var currentSrc = $img.attr('src') || '';
            var currentIdx = images.indexOf(currentSrc);
            $slider.attr('data-gallery-index', String(currentIdx > -1 ? currentIdx : 0));
        });

        $scope.on('click', '.product-photo-nav', function (event) {
            event.preventDefault();
            event.stopPropagation();

            var $btn = $(event.currentTarget);
            var $slider = $btn.closest('.product-photo-slider');
            var current = parseInt($slider.attr('data-gallery-index') || '0', 10);
            var next = $btn.hasClass('nav-next') ? current + 1 : current - 1;
            var sku = $slider.attr('data-product-sku') || '';
            var images = parseGallery($slider);

            if (images.length > 1) {
                applyImage($slider, next);
                return;
            }

            loadGalleryBySku(sku).then(function (gallery) {
                if (gallery.length > 1) {
                    $slider.attr('data-gallery', JSON.stringify(gallery));
                    $slider.removeClass('single-image');
                    applyImage($slider, next);
                }
            });
        });
    }

    function copyFallback(url) {
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(url).trigger('select');

        try {
            document.execCommand('copy');
            alert($t('Product link copied.'));
        } catch (e) {
            alert($t('Cannot copy product link.'));
        }

        $temp.remove();
    }

    function initShare($scope) {
        $scope.on('click', '.actions-secondary .share-copy-btn', function (event) {
            event.preventDefault();

            var $wrap = $(event.currentTarget).closest('.product-share-wrap');
            var url = $wrap.attr('data-share-url');

            if (!url) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    alert($t('Product link copied.'));
                }).catch(function () {
                    copyFallback(url);
                });
                return;
            }

            copyFallback(url);
        });
    }

    function bindAll(element) {
        var $scope = $(element || document);

        if ($scope.data('productCardEnhancementsBound')) {
            return;
        }

        $scope.data('productCardEnhancementsBound', true);
        initSlider($scope);
        initShare($scope);
    }

    $(function () {
        if (!globalBound) {
            bindAll(document);
            globalBound = true;
        }
    });

    return function (config, element) {
        bindAll(element || document);
    };
});

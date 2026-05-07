require(['jquery', 'domReady!'], function ($) {
    'use strict';

    function getAllCategoryLinks() {
        var seen = {};
        var links = [];

        $('.nav-sections .navigation .level0 > a, .nav-sections .navigation a.level-top').each(function () {
            var link = $(this);
            var label = $.trim(link.text());
            var href = link.attr('href');

            if (!label || !href || seen[href]) {
                return;
            }

            seen[href] = true;
            links.push({ label: label, href: href });
        });

        return links;
    }

    function renderSearchCategories() {
        var links = getAllCategoryLinks();
        var dropdown = $('#fs-search-categories');

        if (!dropdown.length) {
            return;
        }

        dropdown.empty();

        $.each(links, function (_, item) {
            dropdown.append(
                $('<a/>', {
                    class: 'fs-search-category-link',
                    href: item.href,
                    text: item.label
                })
            );
        });
    }

    function showSearchCategories() {
        renderSearchCategories();
        $('#fs-search-categories').addClass('is-visible');
    }

    function hideSearchCategories() {
        $('#fs-search-categories').removeClass('is-visible');
    }

    function ensureSearchCategories() {
        var blockSearch = $('.block-search').first();
        var searchControl = blockSearch.find('.control').first();
        var searchInput = blockSearch.find('input[type="search"], input[type="text"]').first();

        if (!blockSearch.length || !searchControl.length || !searchInput.length) {
            return;
        }

        if (!$('#fs-search-categories').length) {
            searchControl.after('<div id="fs-search-categories" class="fs-search-categories" aria-label="Quick categories"></div>');
        }

        renderSearchCategories();

        searchInput.off('.fsHeaderSearch');
        searchInput.on('focus.fsHeaderSearch click.fsHeaderSearch', function () {
            showSearchCategories();
        });

        $(document).off('click.fsHeaderSearch').on('click.fsHeaderSearch', function (event) {
            if ($(event.target).closest('.block-search').length) {
                return;
            }

            hideSearchCategories();
        });

        blockSearch.off('submit.fsHeaderSearch').on('submit.fsHeaderSearch', function () {
            hideSearchCategories();
        });
    }

    ensureSearchCategories();
    $(window).on('resize', function () {
        renderSearchCategories();
    });
});

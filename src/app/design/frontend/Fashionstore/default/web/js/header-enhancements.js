require(['jquery', 'domReady!'], function ($) {
    'use strict';

    function getCategoryLinks() {
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
            links.push({
                label: label,
                href: href
            });
        });

        return links.slice(0, 10);
    }

    function renderMobileMenu() {
        var links = getCategoryLinks();
        var list = $('#fs-mobile-menu-list');

        if (!list.length) {
            return;
        }

        list.empty();

        $.each(links, function (_, item) {
            list.append(
                $('<li/>', { class: 'fs-mobile-menu-item' }).append(
                    $('<a/>', {
                        class: 'fs-mobile-menu-link',
                        href: item.href,
                        text: item.label
                    })
                )
            );
        });
    }

    function closeMobileMenu() {
        $('body').removeClass('fs-mobile-menu-open');
        $('#fs-mobile-menu-overlay, #fs-mobile-menu-drawer').removeClass('is-open');
    }

    function openMobileMenu() {
        renderMobileMenu();
        $('body').addClass('fs-mobile-menu-open');
        $('#fs-mobile-menu-overlay, #fs-mobile-menu-drawer').addClass('is-open');
    }

    function ensureMobileMenu() {
        var header = $('.page-header .header.panel').first();

        if (!header.length || $('#fs-header-hamburger').length) {
            return;
        }

        header.prepend(
            $('<button/>', {
                id: 'fs-header-hamburger',
                class: 'fs-header-hamburger',
                type: 'button',
                'aria-label': 'Open mobile menu',
                'aria-controls': 'fs-mobile-menu-drawer',
                'aria-expanded': 'false'
            }).append('<span></span><span></span><span></span>')
        );

        $('body').append(
            '<div id="fs-mobile-menu-overlay" class="fs-mobile-menu-overlay"></div>' +
            '<aside id="fs-mobile-menu-drawer" class="fs-mobile-menu-drawer" aria-hidden="true">' +
                '<div class="fs-mobile-menu-head">' +
                    '<span class="fs-mobile-menu-title">Danh muc</span>' +
                    '<button id="fs-mobile-menu-close" class="fs-mobile-menu-close" type="button" aria-label="Close mobile menu">&times;</button>' +
                '</div>' +
                '<ul id="fs-mobile-menu-list" class="fs-mobile-menu-list"></ul>' +
            '</aside>'
        );

        $(document).on('click', '#fs-header-hamburger', function () {
            $('#fs-header-hamburger').attr('aria-expanded', 'true');
            $('#fs-mobile-menu-drawer').attr('aria-hidden', 'false');
            openMobileMenu();
        });

        $(document).on('click', '#fs-mobile-menu-close, #fs-mobile-menu-overlay, .fs-mobile-menu-link', function () {
            $('#fs-header-hamburger').attr('aria-expanded', 'false');
            $('#fs-mobile-menu-drawer').attr('aria-hidden', 'true');
            closeMobileMenu();
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                $('#fs-header-hamburger').attr('aria-expanded', 'false');
                $('#fs-mobile-menu-drawer').attr('aria-hidden', 'true');
                closeMobileMenu();
            }
        });
    }

    function renderSearchCategories() {
        var links = getCategoryLinks();
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

    ensureMobileMenu();
    ensureSearchCategories();
    $(window).on('resize', function () {
        renderMobileMenu();
        renderSearchCategories();
    });
});
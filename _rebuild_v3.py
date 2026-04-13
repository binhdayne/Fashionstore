#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Rebuild search: 100 products, multi-layer scoring, dual-state UI, word-boundary highlight."""

FP = '/home/trongan/Fashionstore/src/app/code/FashionStore/CustomHeader/view/frontend/templates/header/weather.phtml'

with open(FP, 'r', encoding='utf-8') as f:
    content = f.read()

with open(FP + '.bak3', 'w', encoding='utf-8') as f:
    f.write(content)
print("Backup saved")

def lstart(text, marker):
    idx = text.index(marker)
    ls = text.rfind('\n', 0, idx)
    return ls + 1 if ls >= 0 else 0

# ══════════════════════════════════════════════════════
# 1. CSS: Add pill svg + no-underline + fix border-radius
# ══════════════════════════════════════════════════════

old_css_pill = """    .search-pill.active {
        background: #111;
        color: #fff;
        border-color: #111;
    }

    .search-products-section {"""

new_css_pill = """    .search-pill.active {
        background: #111;
        color: #fff;
        border-color: #111;
    }

    .search-pill svg {
        width: 14px;
        height: 14px;
        color: #9ca3af;
        flex-shrink: 0;
    }

    .search-pill.active svg {
        color: #fff;
    }

    .search-products-section {"""

content = content.replace(old_css_pill, new_css_pill, 1)

# Ensure no-underline class
if '.no-underline' not in content:
    # Add before the closing </style>
    content = content.replace('</style>', """
    .no-underline,
    .no-underline:hover,
    .no-underline:focus,
    .no-underline:visited {
        text-decoration: none !important;
    }
</style>""", 1)

print("STEP 1: CSS fixed")

# ══════════════════════════════════════════════════════
# 2. HTML: Replace search bar content with dual-state
# ══════════════════════════════════════════════════════

html_start = lstart(content, '<div id="search-overlay">')
html_end = content.index('\n<script>') + 1

NEW_HTML = """\
<div id="search-overlay"></div>
<div id="search-bar">
    <div class="search-top-bar">
        <form id="search_form" action="<?= $escaper->escapeUrl($action) ?>" method="get">
            <input
                id="custom-search-input"
                type="text"
                name="<?= $escaper->escapeHtmlAttr($queryParam) ?>"
                placeholder="<?= $escaper->escapeHtmlAttr(__('Tìm kiếm sản phẩm...')) ?>"
                autocomplete="off"
                maxlength="<?= $escaper->escapeHtmlAttr($maxQueryLength) ?>">
            <span id="search-input-clear" class="search-input-clear">&times;</span>
            <span class="search-input-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>
        </form>
        <div id="search-close">&times;</div>
    </div>
    <div class="search-results-container">
        <div class="search-results-inner">
            <!-- ═══ EMPTY STATE: Category Pills ═══ -->
            <div id="search-empty-state">
                <div id="search-pills-section" class="search-suggestions-section">
                    <div class="search-section-title"><?= $escaper->escapeHtml(__('Danh mục')) ?></div>
                    <div id="search-category-pills" class="search-suggestion-pills"></div>
                </div>
                <div id="search-recently-section" class="search-products-section">
                    <div class="search-section-title"><?= $escaper->escapeHtml(__('Gợi ý cho bạn')) ?></div>
                    <div id="search-recently-grid" class="search-products-grid"></div>
                </div>
            </div>

            <!-- ═══ FILLED STATE: Suggestions + Results ═══ -->
            <div id="search-filled-state" hidden>
                <div id="search-suggest-section" class="search-suggestions-section">
                    <div class="search-section-title"><?= $escaper->escapeHtml(__('Gợi ý tìm kiếm')) ?></div>
                    <div id="search-suggest-pills" class="search-suggestion-pills"></div>
                </div>
                <div id="search-products-section" class="search-products-section">
                    <div class="search-section-title"><?= $escaper->escapeHtml(__('Kết quả tìm kiếm')) ?></div>
                    <div id="search-products-grid" class="search-products-grid"></div>
                </div>
                <div id="search-zero-result" class="search-zero-result" hidden>
                    <div class="search-zero-msg"></div>
                    <div class="search-section-title"><?= $escaper->escapeHtml(__('Có thể bạn sẽ thích')) ?></div>
                    <div id="search-zero-grid" class="search-products-grid"></div>
                </div>
                <div id="search-view-all" class="search-view-all-wrap" hidden>
                    <a id="search-view-all-btn" class="search-view-all-btn no-underline" href="#"><?= $escaper->escapeHtml(__('XEM TẤT CẢ')) ?></a>
                </div>
            </div>

            <div id="search-loading" class="search-products-section" hidden>
                <div class="search-section-title"><?= $escaper->escapeHtml(__('Kết quả tìm kiếm')) ?></div>
                <div class="search-loading-skeleton">
                    <div class="search-skeleton-card"><div class="search-skeleton-img"></div><div class="search-skeleton-text w-60"></div><div class="search-skeleton-text w-30"></div><div class="search-skeleton-text w-40"></div></div>
                    <div class="search-skeleton-card"><div class="search-skeleton-img"></div><div class="search-skeleton-text w-60"></div><div class="search-skeleton-text w-30"></div><div class="search-skeleton-text w-40"></div></div>
                    <div class="search-skeleton-card"><div class="search-skeleton-img"></div><div class="search-skeleton-text w-60"></div><div class="search-skeleton-text w-30"></div><div class="search-skeleton-text w-40"></div></div>
                    <div class="search-skeleton-card"><div class="search-skeleton-img"></div><div class="search-skeleton-text w-60"></div><div class="search-skeleton-text w-30"></div><div class="search-skeleton-text w-40"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

"""

content = content[:html_start] + NEW_HTML + content[html_end:]
print("STEP 2: HTML replaced with dual-state")

# ══════════════════════════════════════════════════════
# 3. JS-A: PRODUCTS (100) + SCORING ENGINE + HIGHLIGHT
# ══════════════════════════════════════════════════════

prod_db_idx = content.index('PRODUCT DATABASE')
comment_start = content.rfind('    /*', 0, prod_db_idx)
js_a_start = content.rfind('\n', 0, comment_start) + 1
js_a_end = lstart(content, 'function getCustomerDisplayName(customer) {')

NEW_JS_A = """\
    /* ═══════════════════════════════════════════════════════════════════
     *  PRODUCT DATABASE — 100 SKUs (ID 2-101)
     * ═══════════════════════════════════════════════════════════════════ */
    var PRODUCTS = [
        /* ── Áo Nam (10) ── */
        { id: 2,  name: 'Áo Thun Nam Basic Trắng',       sku: 'ao-nam-001', price: '199.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-001-1.png' },
        { id: 3,  name: 'Áo Thun Nam Basic Đen',         sku: 'ao-nam-002', price: '199.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-002-1.png' },
        { id: 4,  name: 'Áo Polo Nam Xanh',              sku: 'ao-nam-003', price: '299.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-003-1.png' },
        { id: 5,  name: 'Áo Polo Nam Đỏ',                sku: 'ao-nam-004', price: '299.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-004-1.png' },
        { id: 6,  name: 'Áo Sơ Mi Nam Trắng',            sku: 'ao-nam-005', price: '399.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-005-1.png' },
        { id: 7,  name: 'Áo Sơ Mi Nam Kẻ Sọc',           sku: 'ao-nam-006', price: '399.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-006-1.png' },
        { id: 8,  name: 'Áo Khoác Nam Hoodie',            sku: 'ao-nam-007', price: '499.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-007-1.png' },
        { id: 9,  name: 'Áo Khoác Nam Bomber',            sku: 'ao-nam-008', price: '599.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-008-1.png' },
        { id: 10, name: 'Áo Len Nam Cổ Tròn',             sku: 'ao-nam-009', price: '449.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-009-1.png' },
        { id: 11, name: 'Áo Tank Top Nam',                 sku: 'ao-nam-010', price: '149.000đ',    category: 'ao-nam', image: '/media/import_master/ao-nam-010-1.png' },
        /* ── Quần Nam (10) ── */
        { id: 12, name: 'Quần Jeans Nam Xanh',             sku: 'quan-nam-001', price: '499.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-001-1.png' },
        { id: 13, name: 'Quần Jeans Nam Đen',              sku: 'quan-nam-002', price: '499.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-002-1.png' },
        { id: 14, name: 'Quần Kaki Nam Be',                sku: 'quan-nam-003', price: '399.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-003-1.png' },
        { id: 15, name: 'Quần Kaki Nam Xanh Rêu',         sku: 'quan-nam-004', price: '399.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-004-1.png' },
        { id: 16, name: 'Quần Short Nam Thể Thao',        sku: 'quan-nam-005', price: '249.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-005-1.png' },
        { id: 17, name: 'Quần Short Nam Kaki',             sku: 'quan-nam-006', price: '299.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-006-1.png' },
        { id: 18, name: 'Quần Tây Nam Đen',                sku: 'quan-nam-007', price: '599.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-007-1.png' },
        { id: 19, name: 'Quần Tây Nam Xám',                sku: 'quan-nam-008', price: '599.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-008-1.png' },
        { id: 20, name: 'Quần Jogger Nam',                 sku: 'quan-nam-009', price: '349.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-009-1.png' },
        { id: 21, name: 'Quần Lửng Nam',                   sku: 'quan-nam-010', price: '299.000đ',  category: 'quan-nam', image: '/media/import_master/quan-nam-010-1.png' },
        /* ── Áo Nữ (10) ── */
        { id: 22, name: 'Áo Thun Nữ Trắng',               sku: 'ao-nu-001', price: '179.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-001-1.png' },
        { id: 23, name: 'Áo Thun Nữ Hồng',                sku: 'ao-nu-002', price: '179.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-002-1.png' },
        { id: 24, name: 'Áo Sơ Mi Nữ Trắng',              sku: 'ao-nu-003', price: '349.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-003-1.png' },
        { id: 25, name: 'Áo Sơ Mi Nữ Hoa',                sku: 'ao-nu-004', price: '349.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-004-1.png' },
        { id: 26, name: 'Áo Croptop Nữ',                   sku: 'ao-nu-005', price: '229.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-005-1.png' },
        { id: 27, name: 'Áo Kiểu Nữ Tay Bồng',            sku: 'ao-nu-006', price: '399.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-006-1.png' },
        { id: 28, name: 'Áo Len Nữ Cổ Lọ',                sku: 'ao-nu-007', price: '429.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-007-1.png' },
        { id: 29, name: 'Áo Khoác Nữ Denim',              sku: 'ao-nu-008', price: '549.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-008-1.png' },
        { id: 30, name: 'Áo Blazer Nữ Đen',               sku: 'ao-nu-009', price: '699.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-009-1.png' },
        { id: 31, name: 'Áo Thun Nữ Oversize',            sku: 'ao-nu-010', price: '249.000đ',     category: 'ao-nu', image: '/media/import_master/ao-nu-010-1.png' },
        /* ── Quần Nữ (10) ── */
        { id: 32, name: 'Quần Jeans Nữ Skinny',            sku: 'quan-nu-001', price: '479.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-001-1.png' },
        { id: 33, name: 'Quần Jeans Nữ Ống Rộng',         sku: 'quan-nu-002', price: '499.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-002-1.png' },
        { id: 34, name: 'Quần Culottes Nữ',                sku: 'quan-nu-003', price: '399.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-003-1.png' },
        { id: 35, name: 'Quần Palazzo Nữ',                 sku: 'quan-nu-004', price: '419.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-004-1.png' },
        { id: 36, name: 'Quần Short Nữ Jeans',             sku: 'quan-nu-005', price: '279.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-005-1.png' },
        { id: 37, name: 'Quần Short Nữ Thể Thao',         sku: 'quan-nu-006', price: '229.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-006-1.png' },
        { id: 38, name: 'Quần Legging Nữ',                 sku: 'quan-nu-007', price: '199.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-007-1.png' },
        { id: 39, name: 'Quần Tây Nữ Đen',                 sku: 'quan-nu-008', price: '549.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-008-1.png' },
        { id: 40, name: 'Quần Jogger Nữ',                  sku: 'quan-nu-009', price: '319.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-009-1.png' },
        { id: 41, name: 'Quần Lửng Nữ Kaki',               sku: 'quan-nu-010', price: '299.000đ',   category: 'quan-nu', image: '/media/import_master/quan-nu-010-1.png' },
        /* ── Váy Đầm (10) ── */
        { id: 42, name: 'Đầm Maxi Hoa Nhí',                sku: 'vay-dam-001', price: '599.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-001-1.png' },
        { id: 43, name: 'Đầm Công Sở Đen',                 sku: 'vay-dam-002', price: '699.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-002-1.png' },
        { id: 44, name: 'Váy Chữ A Kẻ Sọc',               sku: 'vay-dam-003', price: '499.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-003-1.png' },
        { id: 45, name: 'Váy Mini Jean',                    sku: 'vay-dam-004', price: '349.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-004-1.png' },
        { id: 46, name: 'Đầm Dự Tiệc Đỏ',                 sku: 'vay-dam-005', price: '899.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-005-1.png' },
        { id: 47, name: 'Váy Xòe Hoa',                     sku: 'vay-dam-006', price: '449.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-006-1.png' },
        { id: 48, name: 'Đầm Wrap Nữ',                     sku: 'vay-dam-007', price: '549.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-007-1.png' },
        { id: 49, name: 'Váy Midi Trơn',                    sku: 'vay-dam-008', price: '399.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-008-1.png' },
        { id: 50, name: 'Đầm Suông Linen',                 sku: 'vay-dam-009', price: '649.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-009-1.png' },
        { id: 51, name: 'Váy Thể Thao Nữ',                 sku: 'vay-dam-010', price: '299.000đ',   category: 'vay-dam', image: '/media/import_master/vay-dam-010-1.png' },
        /* ── Giày Nam (10) ── */
        { id: 52, name: 'Giày Sneaker Nam Trắng',           sku: 'giay-nam-001', price: '799.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-001-1.png' },
        { id: 53, name: 'Giày Sneaker Nam Đen',             sku: 'giay-nam-002', price: '799.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-002-1.png' },
        { id: 54, name: 'Giày Oxford Nam',                  sku: 'giay-nam-003', price: '1.299.000đ',category: 'giay-nam', image: '/media/import_master/giay-nam-003-1.png' },
        { id: 55, name: 'Giày Loafer Nam',                  sku: 'giay-nam-004', price: '999.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-004-1.png' },
        { id: 56, name: 'Dép Sandal Nam',                   sku: 'giay-nam-005', price: '399.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-005-1.png' },
        { id: 57, name: 'Giày Boot Nam Da',                 sku: 'giay-nam-006', price: '1.599.000đ',category: 'giay-nam', image: '/media/import_master/giay-nam-006-1.png' },
        { id: 58, name: 'Giày Thể Thao Nam',               sku: 'giay-nam-007', price: '899.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-007-1.png' },
        { id: 59, name: 'Giày Slip On Nam',                 sku: 'giay-nam-008', price: '699.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-008-1.png' },
        { id: 60, name: 'Dép Quai Hậu Nam',                sku: 'giay-nam-009', price: '299.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-009-1.png' },
        { id: 61, name: 'Giày Moccasin Nam',               sku: 'giay-nam-010', price: '849.000đ',  category: 'giay-nam', image: '/media/import_master/giay-nam-010-1.png' },
        /* ── Giày Nữ (10) ── */
        { id: 62, name: 'Giày Cao Gót Đen',                sku: 'giay-nu-001', price: '899.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-001-1.png' },
        { id: 63, name: 'Giày Cao Gót Đỏ',                 sku: 'giay-nu-002', price: '899.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-002-1.png' },
        { id: 64, name: 'Giày Sneaker Nữ Trắng',           sku: 'giay-nu-003', price: '699.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-003-1.png' },
        { id: 65, name: 'Giày Búp Bê Nữ',                  sku: 'giay-nu-004', price: '599.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-004-1.png' },
        { id: 66, name: 'Dép Sandal Nữ',                   sku: 'giay-nu-005', price: '449.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-005-1.png' },
        { id: 67, name: 'Giày Boot Nữ Cổ Cao',             sku: 'giay-nu-006', price: '1.199.000đ',category: 'giay-nu', image: '/media/import_master/giay-nu-006-1.png' },
        { id: 68, name: 'Giày Wedge Nữ',                   sku: 'giay-nu-007', price: '799.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-007-1.png' },
        { id: 69, name: 'Giày Mule Nữ',                    sku: 'giay-nu-008', price: '649.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-008-1.png' },
        { id: 70, name: 'Dép Lê Nữ',                       sku: 'giay-nu-009', price: '199.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-009-1.png' },
        { id: 71, name: 'Giày Thể Thao Nữ',                sku: 'giay-nu-010', price: '749.000đ',   category: 'giay-nu', image: '/media/import_master/giay-nu-010-1.png' },
        /* ── Túi Xách (10) ── */
        { id: 72, name: 'Túi Xách Nữ Da Đen',              sku: 'tui-xach-001', price: '1.299.000đ',category: 'tui-xach', image: '/media/import_master/tui-xach-001-1.png' },
        { id: 73, name: 'Túi Xách Nữ Da Nâu',              sku: 'tui-xach-002', price: '1.299.000đ',category: 'tui-xach', image: '/media/import_master/tui-xach-002-1.png' },
        { id: 74, name: 'Túi Tote Canvas',                  sku: 'tui-xach-003', price: '299.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-003-1.png' },
        { id: 75, name: 'Balo Nữ Thời Trang',               sku: 'tui-xach-004', price: '499.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-004-1.png' },
        { id: 76, name: 'Clutch Nữ Dạ Tiệc',               sku: 'tui-xach-005', price: '599.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-005-1.png' },
        { id: 77, name: 'Túi Đeo Chéo Nữ',                 sku: 'tui-xach-006', price: '399.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-006-1.png' },
        { id: 78, name: 'Túi Xách Nam Da',                  sku: 'tui-xach-007', price: '999.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-007-1.png' },
        { id: 79, name: 'Balo Nam Laptop',                   sku: 'tui-xach-008', price: '699.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-008-1.png' },
        { id: 80, name: 'Túi Bucket Nữ',                    sku: 'tui-xach-009', price: '549.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-009-1.png' },
        { id: 81, name: 'Ví Da Nữ',                         sku: 'tui-xach-010', price: '349.000đ',  category: 'tui-xach', image: '/media/import_master/tui-xach-010-1.png' },
        /* ── Phụ Kiện (10) ── */
        { id: 82, name: 'Thắt Lưng Nam Da',                 sku: 'phu-kien-001', price: '399.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-001-1.png' },
        { id: 83, name: 'Mũ Cap Nam',                       sku: 'phu-kien-002', price: '199.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-002-1.png' },
        { id: 84, name: 'Kính Mát Nam',                     sku: 'phu-kien-003', price: '499.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-003-1.png' },
        { id: 85, name: 'Kính Mát Nữ',                      sku: 'phu-kien-004', price: '499.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-004-1.png' },
        { id: 86, name: 'Vòng Tay Nữ Bạc',                  sku: 'phu-kien-005', price: '299.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-005-1.png' },
        { id: 87, name: 'Dây Chuyền Nữ',                    sku: 'phu-kien-006', price: '599.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-006-1.png' },
        { id: 88, name: 'Khăn Quàng Nữ',                    sku: 'phu-kien-007', price: '349.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-007-1.png' },
        { id: 89, name: 'Mũ Rộng Vành Nữ',                  sku: 'phu-kien-008', price: '249.000đ',  category: 'phu-kien', image: '/media/import_master/phu-kien-008-1.png' },
        { id: 90, name: 'Tất Vớ Nam',                        sku: 'phu-kien-009', price: '99.000đ',   category: 'phu-kien', image: '/media/import_master/phu-kien-009-1.png' },
        { id: 91, name: 'Tất Vớ Nữ',                        sku: 'phu-kien-010', price: '99.000đ',   category: 'phu-kien', image: '/media/import_master/phu-kien-010-1.png' },
        /* ── Sale (10) ── */
        { id: 92,  name: 'Áo Thun Nam Sale 50%',             sku: 'sale-001', price: '99.000đ',      category: 'sale', image: '/media/import_master/sale-001-1.png' },
        { id: 93,  name: 'Quần Jeans Nữ Sale',              sku: 'sale-002', price: '249.000đ',     category: 'sale', image: '/media/import_master/sale-002-1.png' },
        { id: 94,  name: 'Giày Sneaker Sale',               sku: 'sale-003', price: '399.000đ',     category: 'sale', image: '/media/import_master/sale-003-1.png' },
        { id: 95,  name: 'Váy Đầm Sale 40%',                sku: 'sale-004', price: '299.000đ',     category: 'sale', image: '/media/import_master/sale-004-1.png' },
        { id: 96,  name: 'Túi Xách Sale',                   sku: 'sale-005', price: '499.000đ',     category: 'sale', image: '/media/import_master/sale-005-1.png' },
        { id: 97,  name: 'Áo Khoác Sale 30%',               sku: 'sale-006', price: '349.000đ',     category: 'sale', image: '/media/import_master/sale-006-1.png' },
        { id: 98,  name: 'Phụ Kiện Sale',                    sku: 'sale-007', price: '149.000đ',     category: 'sale', image: '/media/import_master/sale-007-1.png' },
        { id: 99,  name: 'Quần Nam Sale',                    sku: 'sale-008', price: '199.000đ',     category: 'sale', image: '/media/import_master/sale-008-1.png' },
        { id: 100, name: 'Giày Nữ Sale 50%',                sku: 'sale-009', price: '349.000đ',     category: 'sale', image: '/media/import_master/sale-009-1.png' },
        { id: 101, name: 'Áo Nữ Sale Cuối Mùa',             sku: 'sale-010', price: '89.000đ',      category: 'sale', image: '/media/import_master/sale-010-1.png' }
    ];

    var CATEGORY_LABELS = {
        'ao-nam': 'Áo Nam', 'ao-nu': 'Áo Nữ',
        'quan-nam': 'Quần Nam', 'quan-nu': 'Quần Nữ',
        'vay-dam': 'Váy Đầm', 'giay-nam': 'Giày Nam',
        'giay-nu': 'Giày Nữ', 'tui-xach': 'Túi Xách',
        'phu-kien': 'Phụ Kiện', 'sale': 'Sale'
    };

    /* Recently Viewed: mặc định 4 sản phẩm cuối */
    var recentlyViewed = PRODUCTS.slice(-4);

    /* ═══════════════════════════════════════════════════════════════════
     *  MULTI-LAYER SCORING ENGINE
     *  Ưu tiên 1: +10 = Khớp chính xác cả cụm từ trong Tên
     *  Ưu tiên 2: +5  = Khớp toàn bộ token AND trong Tên
     *  Ưu tiên 3: +2/từ = Khớp từng token OR (word boundary) trong Tên
     *  Ưu tiên 4: +3  = SKU chứa searchTerm
     * ═══════════════════════════════════════════════════════════════════ */

    function removeAccents(str) {
        return (str || '').toLowerCase()
            .normalize('NFD').replace(/[\\u0300-\\u036f]/g, '')
            .replace(/đ/g, 'd').replace(/Đ/g, 'D');
    }

    function handleSearch(rawQuery) {
        var query = $.trim(rawQuery).replace(/\\s+/g, ' ').toLowerCase();
        var nQuery = removeAccents(query);
        if (!nQuery.replace(/\\s+/g, '')) {
            return { items: [], title: '', isFallback: false, query: query };
        }

        var tokens = nQuery.split(/\\s+/).filter(function (w) { return w.length > 0; });
        var scored = [];

        for (var i = 0; i < PRODUCTS.length; i++) {
            var p = PRODUCTS[i];
            var score = 0;
            var nName = removeAccents(p.name);
            var nameWords = nName.split(/\\s+/);

            /* Ưu tiên 1: +10 Exact Phrase in Name */
            if (nName.indexOf(nQuery) !== -1) {
                score += 10;
            }

            /* Ưu tiên 2: +5 All tokens AND match */
            var allMatch = true;
            for (var ti = 0; ti < tokens.length; ti++) {
                var found = false;
                for (var ni = 0; ni < nameWords.length; ni++) {
                    if (nameWords[ni] === tokens[ti]) { found = true; break; }
                }
                if (!found) { allMatch = false; break; }
            }
            if (allMatch && tokens.length > 0) {
                score += 5;
            }

            /* Ưu tiên 3: +2/token partial OR word boundary */
            for (var wi = 0; wi < tokens.length; wi++) {
                for (var wj = 0; wj < nameWords.length; wj++) {
                    if (nameWords[wj] === tokens[wi]) {
                        score += 2;
                        break;
                    }
                }
            }

            /* Ưu tiên 4: +3 SKU match */
            var nSku = removeAccents(p.sku);
            if (nSku.indexOf(nQuery) !== -1) {
                score += 3;
            }

            if (score > 0) {
                scored.push({ product: p, score: score, matchType: score >= 10 ? 'name' : (score >= 3 ? 'name' : 'sku') });
            }
        }

        /* Sort descending by score, then by id */
        scored.sort(function (a, b) {
            if (b.score !== a.score) return b.score - a.score;
            return a.product.id - b.product.id;
        });

        /* Zero-result → recentlyViewed fallback */
        if (scored.length === 0) {
            var fallback = [];
            for (var fi = 0; fi < recentlyViewed.length; fi++) {
                fallback.push({ product: recentlyViewed[fi], matchType: 'fallback' });
            }
            return { items: fallback, title: 'Có thể bạn sẽ thích', isFallback: true, query: query };
        }

        /* Magic 4: top 4 highest score */
        var top = scored.slice(0, 4);

        return { items: top, title: 'Kết quả tìm kiếm', isFallback: false, query: query };
    }

    /* Get unique categories */
    function getUniqueCategories() {
        var seen = {};
        var cats = [];
        for (var i = 0; i < PRODUCTS.length; i++) {
            var slug = PRODUCTS[i].category;
            if (slug && !seen[slug]) {
                seen[slug] = true;
                cats.push({ slug: slug, label: CATEGORY_LABELS[slug] || slug });
            }
        }
        return cats;
    }

    /* ═══════════════════════════════════════════════════════════════════
     *  HIGHLIGHT ENGINE — Word-Boundary Only
     *  "áo" in đậm "Áo" nhưng KHÔNG in đậm "ao" trong "thao"
     * ═══════════════════════════════════════════════════════════════════ */
    function getHighlightedText(text, query, matchType) {
        var safe = escapeHtml(text);
        if (!query || matchType === 'sku' || matchType === 'fallback') return safe;

        var queryWords = removeAccents(query).split(/\\s+/).filter(function (w) { return w.length > 0; });
        if (!queryWords.length) return safe;

        var tokens = text.split(/(\\s+)/);
        var result = '';

        for (var i = 0; i < tokens.length; i++) {
            var token = tokens[i];
            if (/^\\s+$/.test(token)) {
                result += token;
                continue;
            }
            var nToken = removeAccents(token);
            var isMatch = false;
            for (var j = 0; j < queryWords.length; j++) {
                if (nToken === queryWords[j]) {
                    isMatch = true;
                    break;
                }
            }
            if (isMatch) {
                result += '<strong class="text-black font-extrabold no-underline">' + escapeHtml(token) + '</strong>';
            } else {
                result += escapeHtml(token);
            }
        }

        return result;
    }

    /* Generate suggestion pill labels from top scored products */
    function generateSuggestions(query) {
        var nq = removeAccents($.trim(query));
        if (!nq) return [];
        var tokens = nq.split(/\\s+/).filter(function (w) { return w.length > 0; });
        var seen = {};
        var suggestions = [];

        for (var i = 0; i < PRODUCTS.length && suggestions.length < 4; i++) {
            var nName = removeAccents(PRODUCTS[i].name);
            var nameWords = nName.split(/\\s+/);
            var hit = false;
            for (var ti = 0; ti < tokens.length; ti++) {
                for (var ni = 0; ni < nameWords.length; ni++) {
                    if (nameWords[ni] === tokens[ti]) { hit = true; break; }
                }
                if (hit) break;
            }
            if (hit) {
                var label = PRODUCTS[i].name;
                if (!seen[label]) {
                    seen[label] = true;
                    suggestions.push(label);
                }
            }
        }
        return suggestions;
    }

"""

content = content[:js_a_start] + NEW_JS_A + content[js_a_end:]
print("STEP 3: JS-A replaced (100 products + scoring engine)")

# ══════════════════════════════════════════════════════
# 4. JS-B: UI FUNCTIONS (dual-state)
# ══════════════════════════════════════════════════════

js_b_start = lstart(content, 'function hideSearchResults() {')
js_b_end = lstart(content, 'function updateSearchPlaceholder() {')

NEW_JS_B = """\
    function hideSearchResults() {
        $('#search-filled-state').prop('hidden', true);
        $('#search-products-section').prop('hidden', true);
        $('#search-zero-result').prop('hidden', true);
        $('#search-view-all').prop('hidden', true);
        $('#search-loading').prop('hidden', true);
        $('#search-suggest-pills').empty();
        $('#search-products-grid').empty();
        $('#search-zero-grid').empty();
        $('#search-input-clear').removeClass('visible');
    }

    function buildProductCard(item, query, matchType) {
        var nameHtml = (query && matchType === 'name')
            ? getHighlightedText(item.name, query, matchType)
            : escapeHtml(item.name);

        return '<a class="search-product-card no-underline" href="/' + escapeHtml(item.sku) + '.html">' +
            '<div class="search-product-img-wrap">' +
            '<img class="search-product-img" src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.name) + '" loading="eager" decoding="sync" onerror="this.src=\\'https://placehold.co/400x533/f9fafb/a1a1aa?text=No+Image\\'">' +
            '</div>' +
            '<div class="search-product-name no-underline">' + nameHtml + '</div>' +
            '<div class="search-product-sku no-underline">' + escapeHtml(item.sku) + '</div>' +
            '<div class="search-product-price no-underline">' + escapeHtml(item.price) + '</div>' +
            '</a>';
    }

    /* ═══ EMPTY STATE: Category pills + recently viewed ═══ */
    function showDefaultState() {
        $('#search-filled-state').prop('hidden', true);
        $('#search-loading').prop('hidden', true);
        $('#search-empty-state').prop('hidden', false);

        /* Category pills */
        var cats = getUniqueCategories();
        var pillsHtml = '';
        $.each(cats, function (i, cat) {
            pillsHtml += '<a class="search-pill no-underline" href="#" data-category="' + escapeHtml(cat.label) + '">' +
                '<span>' + escapeHtml(cat.label) + '</span></a>';
        });
        $('#search-category-pills').html(pillsHtml);

        /* 4 recently viewed products */
        var gridHtml = '';
        $.each(recentlyViewed, function (i, item) {
            gridHtml += buildProductCard(item, null, null);
        });
        $('#search-recently-grid').html(gridHtml);
    }

    /* ═══ FILLED STATE: Suggestions + Results ═══ */
    function renderSearchResults(query) {
        var searchUrl = $('#search_form').attr('action');
        var queryParamName = '<?= $escaper->escapeJs($queryParam) ?>';

        $('#search-loading').prop('hidden', true);

        /* Switch to filled state */
        $('#search-empty-state').prop('hidden', true);
        $('#search-filled-state').prop('hidden', false);

        if ($.trim(query)) {
            $('#search-input-clear').addClass('visible');
        } else {
            $('#search-input-clear').removeClass('visible');
        }

        /* ─── Suggestion pills from top matches ─── */
        var suggestions = generateSuggestions(query);
        var suggestHtml = '';
        $.each(suggestions, function (i, label) {
            suggestHtml += '<a class="search-pill no-underline" href="#" data-suggest="' + escapeHtml(label) + '">' +
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                '<span>' + escapeHtml(label) + '</span></a>';
        });
        $('#search-suggest-pills').html(suggestHtml);
        $('#search-suggest-section').prop('hidden', !suggestHtml);

        /* ─── Scoring Engine ─── */
        var result = handleSearch(query);

        /* ─── ZERO-RESULT: Fallback ─── */
        if (result.isFallback) {
            $('#search-products-section').prop('hidden', true);
            var zeroHtml = '';
            $.each(result.items, function (i, entry) {
                zeroHtml += buildProductCard(entry.product, null, 'fallback');
            });
            $('#search-zero-result .search-zero-msg').text(
                'Không tìm thấy sản phẩm phù hợp. Có thể bạn sẽ thích:'
            );
            $('#search-zero-grid').html(zeroHtml);
            $('#search-zero-result').prop('hidden', false);

            var viewAllUrl0 = searchUrl + '?' + encodeURIComponent(queryParamName) + '=' + encodeURIComponent(query);
            $('#search-view-all-btn').attr('href', viewAllUrl0);
            $('#search-view-all').prop('hidden', false);
            return;
        }

        /* ─── Normal results ─── */
        $('#search-zero-result').prop('hidden', true);
        var gridHtml = '';
        $.each(result.items, function (i, entry) {
            gridHtml += buildProductCard(entry.product, query, entry.matchType);
        });
        $('#search-products-grid').html(gridHtml);
        $('#search-products-section').prop('hidden', false);

        var viewAllUrl = searchUrl + '?' + encodeURIComponent(queryParamName) + '=' + encodeURIComponent(query);
        $('#search-view-all-btn').attr('href', viewAllUrl);
        $('#search-view-all').prop('hidden', false);
    }

"""

content = content[:js_b_start] + NEW_JS_B + content[js_b_end:]
print("STEP 4: JS-B replaced (UI functions)")

# ══════════════════════════════════════════════════════
# 5. JS-C: EVENT HANDLERS
# ══════════════════════════════════════════════════════

evt_comment_idx = content.index('Clicking a category pill')
evt_start = content.rfind('    /*', 0, evt_comment_idx)
evt_start = content.rfind('\n', 0, evt_start) + 1
evt_end = lstart(content, 'renderCustomerState(customerData')

NEW_EVENTS = """\
    /* Category pill → fill input + search */
    $(document).on('click', '.search-pill[data-category]', function (e) {
        e.preventDefault();
        $('.search-pill').removeClass('active');
        $(this).addClass('active');
        var pillText = $(this).attr('data-category');
        getSearchInput().val(pillText);
        $('#search-input-clear').addClass('visible');
        fetchSearchSuggestions();
    });

    /* Suggestion pill → toggle active + fill input + search */
    $(document).on('click', '.search-pill[data-suggest]', function (e) {
        e.preventDefault();
        $('.search-pill[data-suggest]').removeClass('active');
        $(this).addClass('active');
        var pillText = $(this).attr('data-suggest');
        getSearchInput().val(pillText);
        $('#search-input-clear').addClass('visible');
        fetchSearchSuggestions();
    });

"""

content = content[:evt_start] + NEW_EVENTS + content[evt_end:]
print("STEP 5: JS-C replaced (event handlers)")

# ══════════════════════════════════════════════════════
# WRITE & VALIDATE
# ══════════════════════════════════════════════════════

with open(FP, 'w', encoding='utf-8') as f:
    f.write(content)

lines = content.count('\n') + 1
bo = content.count('{')
bc = content.count('}')
po = content.count('(')
pc = content.count(')')
so = content.count('[')
sc = content.count(']')

print(f"\nFile written: {FP}")
print(f"Lines: {lines}")
print(f"Braces {{}}: {bo}/{bc} (diff: {bo - bc})")
print(f"Parens (): {po}/{pc} (diff: {po - pc})")
print(f"Brackets []: {so}/{sc} (diff: {so - sc})")

# Count products
prod_count = content.count("image: '/media/import_master/")
print(f"\nProduct count: {prod_count}")

checks = {
    'var PRODUCTS': 'PRODUCTS array',
    '/media/import_master/': 'correct image path',
    'function handleSearch': 'handleSearch',
    'function getHighlightedText': 'getHighlightedText',
    'function buildProductCard': 'buildProductCard',
    'function showDefaultState': 'showDefaultState',
    'function renderSearchResults': 'renderSearchResults',
    'function hideSearchResults': 'hideSearchResults',
    'function generateSuggestions': 'generateSuggestions',
    'recentlyViewed': 'recentlyViewed',
    'data-category': 'category attr',
    'data-suggest': 'suggest attr',
    'text-black font-extrabold': 'highlight class',
    'no-underline': 'no-underline class',
    'search-empty-state': 'empty state',
    'search-filled-state': 'filled state',
    'Có thể bạn sẽ thích': 'zero-result title',
    'score += 10': 'score +10 (exact phrase)',
    'score += 5': 'score +5 (all tokens AND)',
    'score += 2': 'score +2 (per token)',
    'score += 3': 'score +3 (SKU)',
    'object-cover': 'object-cover CSS',
    'object-top': 'object-top CSS',
    'line-clamp': 'line-clamp CSS',
    'aspect-ratio: 3 / 4': 'aspect ratio 3/4',
}

print("\nMarker verification:")
for marker, label in checks.items():
    count = content.count(marker)
    print(f"  {label}: {count}")

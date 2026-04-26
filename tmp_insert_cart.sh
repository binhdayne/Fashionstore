#!/usr/bin/env bash
set -euo pipefail
FILE="/home/duong/magento2-shop/src/app/code/FashionStore/CustomHeader/view/frontend/templates/header/weather.phtml"

# Avoid duplicate insertion.
if grep -q 'header-cart-link' "$FILE"; then
  echo "header-cart-link already present"
  exit 0
fi

# Insert cart markup before the closing right-section div (line 899 from current file layout).
sed -i '899i\        </a>' "$FILE"
sed -i '899i\            </svg>' "$FILE"
sed -i '899i\                <path d="M3 4h2l2.3 10.2a1 1 0 0 0 1 .8h9.8a1 1 0 0 0 1-.8L21 7H7"></path>' "$FILE"
sed -i '899i\                <circle cx="18" cy="20" r="1.5"></circle>' "$FILE"
sed -i '899i\                <circle cx="9" cy="20" r="1.5"></circle>' "$FILE"
sed -i '899i\            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true" focusable="false">' "$FILE"
sed -i '899i\        <a class="header-cart-link" href="/checkout/cart/" aria-label="View cart">' "$FILE"

# Add CSS once right after inline-search icon style block.
if ! grep -q '^\s*\.header-cart-link \{' "$FILE"; then
  sed -i '303i\
    .header-cart-link {\
        display: inline-flex;\
        align-items: center;\
        justify-content: center;\
        width: 36px;\
        height: 36px;\
        border: 1px solid rgba(255, 255, 255, 0.35);\
        border-radius: 50%;\
        color: #fff;\
        text-decoration: none;\
        transition: border-color 0.2s ease, background-color 0.2s ease;\
        flex: 0 0 auto;\
    }\
\
    .header-cart-link:hover,\
    .header-cart-link:focus {\
        border-color: rgba(255, 255, 255, 0.7);\
        background: rgba(255, 255, 255, 0.08);\
        color: #fff;\
    }\
\
    .header-cart-link svg {\
        width: 18px;\
        height: 18px;\
        stroke: currentColor;\
    }' "$FILE"
fi

if ! grep -q '^\s*\.header-cart-link \{$' "$FILE"; then
  echo "warning: cart css not found"
fi

grep -n 'header-cart-link' "$FILE" | head -n 10
sed -n '882,910p' "$FILE"

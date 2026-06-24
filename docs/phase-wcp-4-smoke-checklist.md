# Phase WCP-4 — WooCommerce Cart / Checkout Integration — Smoke Checklist

## Add to Cart

- [ ] Calculate a price, then click "Add to Cart" — button shows "Adding…" state
- [ ] After success, button shows "Added to Cart!" and a "View Cart" link appears
- [ ] Clicking "View Cart" navigates to the WooCommerce cart page
- [ ] The cart item shows the book specs (size, pages, copies, binding, paper, colors, country)
- [ ] The cart item price matches the calculated total (not the base product price of 0)
- [ ] Adding multiple books creates separate cart line items

## Price Security

- [ ] Modifying the offer data in browser devtools before add-to-cart returns a 403 signature error
- [ ] Recalculating after a failed add-to-cart produces a new valid signature
- [ ] Cart price is re-verified via HMAC on `woocommerce_before_calculate_totals` — tampered session data is ignored

## Checkout

- [ ] WooCommerce checkout page shows book specs in the order summary
- [ ] Completing checkout stores all book specs as order item meta
- [ ] Standard WooCommerce checkout flow (payment, shipping) is not disrupted

## Order Display

- [ ] **Admin order view:** Book specs appear on order line item; price breakdown table visible
- [ ] **Customer "My Account" order view:** Book specs appear as formatted meta (no internal keys like `_ppp_bpe_book_size`)
- [ ] **Order confirmation email:** Book specs are included in the email order summary
- [ ] Internal meta (signature, breakdown array, source) is hidden from customer-facing views

## Edge Cases

- [ ] If base product is missing, add-to-cart returns a 500 with clear error message
- [ ] Add-to-cart without calculating first: button stays disabled (no offer available)
- [ ] Recalculating after adding to cart resets the button to "Add to Cart"
- [ ] Non-logged-in (guest) users can add to cart and checkout

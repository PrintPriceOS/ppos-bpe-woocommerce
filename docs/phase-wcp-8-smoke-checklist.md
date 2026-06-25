# Phase WCP-8 — Printhouse Mini Queue — Smoke Checklist

## Admin Production Queue Page

- [ ] Menu item shows "Production Queue" under PrintPricePro
- [ ] Page loads without PHP errors
- [ ] Queue table displays BPE orders with correct columns: Order, Customer, Book Specs, Files, Preflight, Payment, Production, Actions
- [ ] Status filter links work (All, New, Reviewing, Accepted, In Prepress, In Production, Completed, Shipped, Action Required)
- [ ] Active filter is visually highlighted
- [ ] Empty state message displays when no orders match filter
- [ ] Book specs (size, pages, copies, binding) display correctly from order item meta

## Production Status Management

- [ ] Status dropdown shows all 8 production statuses
- [ ] Changing status shows confirmation dialog with status label
- [ ] Optional note prompt appears after confirmation
- [ ] Status updates via REST API (`POST /wp-json/printpricepro/v1/queue/{order_id}/status`)
- [ ] Production badge color updates inline after status change
- [ ] Order note is created with old → new status transition and optional note
- [ ] Status history is appended to order meta `_ppp_bpe_status_history`
- [ ] Attempting same status returns error
- [ ] Non-BPE orders return error

## Initial Status

- [ ] New BPE orders automatically receive `new` production status on checkout
- [ ] Status history entry is created with "Order created" note

## Customer Tracking (My Account > View Order)

- [ ] Production tracking section appears on BPE order view
- [ ] Step progress indicator shows 7 steps: New → Reviewing → Accepted → In Prepress → In Production → Completed → Shipped
- [ ] Completed steps show green dots with connecting lines
- [ ] Current step shows blue dot with glow
- [ ] Future steps show grey dots
- [ ] "Action Required" status shows red alert banner

## Control Plane Sync

- [ ] When federated_node mode is active, status changes sync to Control Plane
- [ ] When not in federated_node mode, no CP sync attempted
- [ ] Missing CP order ID gracefully skips sync

## REST API

- [ ] `GET /wp-json/printpricepro/v1/queue` returns queue items (admin only)
- [ ] `POST /wp-json/printpricepro/v1/queue/{id}/status` updates status (admin only)
- [ ] Both endpoints require `manage_woocommerce` capability
- [ ] Unauthenticated requests are rejected

## Styles & Assets

- [ ] Admin CSS loads only on plugin pages
- [ ] Tracking CSS loads on customer order view page
- [ ] No style conflicts with default WordPress admin theme
- [ ] Queue table is responsive and readable

# Phase WCP-7 — Control Plane Node Connection — Smoke Checklist

## Settings

- [ ] New settings section "Control Plane / Node Settings" appears on Settings page.
- [ ] `Control Plane URL` field saves and sanitizes correctly.
- [ ] `Node API Key` field uses `type="password"` and saves correctly.
- [ ] Existing fields (`Tenant ID`, `Node ID`, `Webhook Secret`) still work.
- [ ] Settings default values include `control_plane_url` and `node_api_key`.

## Node Activation Gate

- [ ] Control Plane features are **disabled** when mode is `local` or `api`.
- [ ] Control Plane features are **disabled** when mode is `federated_node` but `control_plane_url`, `node_id`, or `tenant_id` is empty.
- [ ] Control Plane features are **enabled** only when mode is `federated_node` AND all required fields are configured.
- [ ] No data is sent to Control Plane when Node Mode is off.

## REST Endpoints

- [ ] `POST /wp-json/printpricepro/v1/node/handshake` — returns node identity and capabilities when authenticated.
- [ ] `GET /wp-json/printpricepro/v1/node/capacity` — returns active order count and production capabilities.
- [ ] `POST /wp-json/printpricepro/v1/webhooks/control-plane` — accepts signed webhook events.
- [ ] `POST /wp-json/printpricepro/v1/node/orders/{id}/sync` — admin-only manual order sync.
- [ ] `POST /wp-json/printpricepro/v1/node/orders/{id}/sync-files` — admin-only manual file sync.
- [ ] `GET /wp-json/printpricepro/v1/node/status` — admin-only node connection status.
- [ ] All node endpoints reject unauthenticated requests.
- [ ] Webhook endpoint verifies HMAC signature (`X-PPP-Signature`) or Node API Key (`X-PPP-Node-Key`).

## Order Sync

- [ ] When a BPE order is created in federated_node mode, it is automatically synced to Control Plane.
- [ ] CP order ID is stored in order meta (`_ppp_bpe_cp_order_id`).
- [ ] Sync timestamp is stored (`_ppp_bpe_cp_synced_at`).
- [ ] Order status changes are pushed to Control Plane.
- [ ] Sync failure adds an order note with error message (does not break WooCommerce flow).

## File Sync

- [ ] After file upload, files are automatically synced to Control Plane (if enabled).
- [ ] File sync stores timestamp in `_ppp_bpe_cp_files_synced`.
- [ ] Manual file sync endpoint works from admin.

## Dispatch Packages (Inbound)

- [ ] `dispatch_package` webhook creates a new WooCommerce order.
- [ ] Book specs from dispatch are saved as order item meta.
- [ ] Customer info from dispatch is saved to billing fields.
- [ ] Dispatch ID stored in `_ppp_bpe_cp_dispatch_id`.
- [ ] Duplicate dispatch IDs are detected and return the existing order (idempotent).

## Production Status

- [ ] `production_status_update` webhook updates order meta.
- [ ] Status update adds an order note.
- [ ] `order_cancelled` webhook cancels the WooCommerce order with reason.

## Admin UI

- [ ] "Join PrintPrice OS" page shows connection status when in federated_node mode.
- [ ] "Join PrintPrice OS" page shows CTA and setup instructions when NOT connected.
- [ ] Admin order view shows Control Plane section with CP Order ID, sync timestamps, production status.
- [ ] Control Plane section is hidden for non-BPE orders and when not in node mode.

## Health Endpoint

- [ ] `GET /wp-json/printpricepro/v1/health` includes `control_plane` flag in `production_flags`.

## Error Handling & Audit

- [ ] All Control Plane API errors are logged via WooCommerce logger (when debug mode on).
- [ ] Failed syncs do not crash WooCommerce checkout or order flow.
- [ ] All log messages use source `printpricepro-bpe-control-plane`.

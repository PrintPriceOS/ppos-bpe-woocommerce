# Phase WCP-6 — Preflight Bridge — Smoke Test Checklist

## Settings

- [ ] Preflight Settings section appears on PrintPricePro > Settings page.
- [ ] "Enable Preflight" checkbox is visible and saves correctly.
- [ ] "Preflight API URL" field is visible and saves correctly.
- [ ] "Auto-start Preflight" checkbox is visible and saves correctly.
- [ ] "Webhook Secret" field is visible and saves correctly.
- [ ] Preflight is disabled when mode is "Local" even if checkbox is checked.
- [ ] Preflight API URL defaults to BPE API URL when left empty.

## REST Endpoints

- [ ] `POST /wp-json/printpricepro/v1/orders/{id}/preflight/start` — returns 400 if preflight is disabled.
- [ ] `POST /wp-json/printpricepro/v1/orders/{id}/preflight/start` — returns 400 if files not uploaded.
- [ ] `POST /wp-json/printpricepro/v1/orders/{id}/preflight/start` — returns 200 with `preflight_pending` when successful.
- [ ] `GET /wp-json/printpricepro/v1/orders/{id}/preflight/status` — returns current status and humanized report.
- [ ] `POST /wp-json/printpricepro/v1/webhooks/preflight` — rejects requests without valid HMAC signature.
- [ ] `POST /wp-json/printpricepro/v1/webhooks/preflight` — accepts valid webhook and updates order status.
- [ ] Permission checks: only order owner or admin can access start/status endpoints.

## Health Endpoint

- [ ] `GET /wp-json/printpricepro/v1/health` — `production_flags.preflight` reflects actual setting.
- [ ] `production_flags.os_connection` reflects federated_node mode.

## Customer-Facing UI

- [ ] Preflight section does NOT appear when preflight is disabled.
- [ ] Preflight section does NOT appear when files are not yet uploaded.
- [ ] Preflight section appears on Thank You page after files are uploaded (when enabled).
- [ ] Preflight section appears on My Account > Order view after files are uploaded (when enabled).
- [ ] "Run Preflight Check" button starts the check and shows pending status.
- [ ] Pending status shows pulsing animation.
- [ ] Polling fetches updated status every 5 seconds while pending.
- [ ] Passed status shows green badge and success message.
- [ ] Warnings status shows amber badge and warning message.
- [ ] Blocked status shows red badge, error message, and suggests re-upload.
- [ ] Report table displays individual checks with file, name, severity, and message.
- [ ] "Re-run Preflight" button is available after a result is received.

## Auto-start

- [ ] When auto-start is enabled, preflight starts immediately after both files are uploaded.
- [ ] When auto-start is disabled, customer must manually click "Run Preflight Check".
- [ ] Upload response includes `preflight_status` field when auto-start triggers.
- [ ] Upload success message includes preflight started notice when auto-start triggers.

## Admin Order View

- [ ] Preflight Check section appears below Production Files on admin order page.
- [ ] Shows status, start time, and job ID.
- [ ] Shows humanized report table when results are available.
- [ ] Shows "not yet started" when preflight hasn't been run.
- [ ] Section hidden when preflight is disabled and no status exists.

## WooCommerce Integration

- [ ] Preflight does NOT block WooCommerce checkout or cart flow.
- [ ] Order notes are created when preflight starts and completes.
- [ ] Preflight status is independent of WooCommerce order status.

## Edge Cases

- [ ] Re-uploading files after preflight blocked allows re-running preflight.
- [ ] Starting preflight when already pending returns existing status (no duplicate jobs).
- [ ] Plugin functions normally when preflight API is unreachable (graceful error).
- [ ] No errors or notices when preflight is disabled.

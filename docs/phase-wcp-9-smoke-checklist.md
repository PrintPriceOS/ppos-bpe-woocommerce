# Phase WCP-9 — Licensing & SaaS Conversion Layer — Smoke Checklist

## License Activation

- [ ] License page is accessible at PrintPricePro > License.
- [ ] Without a license, plan shows "Free" and status is "inactive".
- [ ] Entering a valid license key activates the license and reloads the page.
- [ ] After activation, plan, customer, activated date, and masked key are displayed.
- [ ] Deactivating the license reverts to Free plan.
- [ ] If the BPE API URL is not configured, license activates in "unverified" mode.
- [ ] Invalid license key shows an error message.
- [ ] License key is never exposed in full in any REST response or frontend.

## Plan Limits & Feature Gating

- [ ] Free plan: calculator works up to 50 quotes/month.
- [ ] Free plan: 51st quote returns HTTP 429 with `limit_reached: true`.
- [ ] Free plan: frontend shows limit-reached message and disables submit button.
- [ ] Pro plan: unlimited quotes (no 429 response).
- [ ] Free plan: "Powered by PrintPricePro" branding appears below calculator.
- [ ] Pro plan: branding is removed.
- [ ] Free plan: PDF upload returns HTTP 403 with upgrade message.
- [ ] Pro plan: PDF upload works normally.
- [ ] Free/Pro plan: Preflight start returns HTTP 403 with upgrade message.
- [ ] Preflight Add-on plan: Preflight start works normally.
- [ ] Free/Pro/Preflight plan: Control Plane sync is skipped silently.
- [ ] Connected Node plan: Control Plane sync executes.

## Usage Metering

- [ ] Each successful quote increments `quote_calculated` event count.
- [ ] Each file upload increments `files_uploaded` event count.
- [ ] Each order creation increments `order_created` event count.
- [ ] Usage resets on new calendar month.
- [ ] Usage bar on License page reflects current counts.
- [ ] Usage summary shows "unlimited" for paid plans.

## Upgrade Prompts

- [ ] Free plan with ≥80% quota used: volume trigger notice appears on admin pages.
- [ ] Free plan with 100% quota used: limit-reached notice appears.
- [ ] Pro plan with >5 file uploads/month: preflight trigger appears.
- [ ] Pro plan with >10 orders/month: node upgrade trigger appears.
- [ ] Connected Node plan: marketplace trigger appears.
- [ ] Notices only appear on PrintPricePro admin pages.
- [ ] Each notice has a CTA button linking to the License page.

## Plan Comparison

- [ ] License page shows all 5 plans in a grid.
- [ ] Current plan is highlighted with "Current Plan" badge.
- [ ] Feature checklist per plan shows correct included/excluded icons.

## REST API

- [ ] `GET /wp-json/printpricepro/v1/license/status` returns plan, limits, usage (requires `manage_woocommerce`).
- [ ] `POST /wp-json/printpricepro/v1/license/activate` activates license (requires `manage_woocommerce`).
- [ ] `POST /wp-json/printpricepro/v1/license/deactivate` deactivates license (requires `manage_woocommerce`).
- [ ] License endpoints are not accessible to unauthenticated users.
- [ ] Health endpoint includes `license.plan` and `license.active` fields.

## Scheduled Tasks

- [ ] Daily license verification cron is scheduled on plugin init.
- [ ] Cron is cleared on plugin deactivation.
- [ ] Verification updates plan and status from remote API.
- [ ] Expired/revoked license is marked as "expired" after verification.

## Settings Sync

- [ ] Activating Connected Node plan auto-sets mode to "federated_node".
- [ ] Activating Pro plan auto-sets mode to "api" (if currently "local").
- [ ] Activating Preflight Add-on plan auto-enables preflight setting.

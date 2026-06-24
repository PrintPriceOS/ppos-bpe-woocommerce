# Phase WCP-3 — BPE API Integration — Smoke Test Checklist

## Settings

- [ ] Mode dropdown shows Local / API / Federated Node options.
- [ ] BPE API URL, License Key, Tenant ID fields save and persist correctly.
- [ ] License Key is rendered as `type="password"` — never visible in frontend source.

## Local mode (default)

- [ ] With mode set to **Local**, calculator works as before.
- [ ] Response includes `"source": "local"` field.
- [ ] Response includes `"offer_signature"` (64-char hex string).
- [ ] Price breakdown, unit price, and total are correct.

## API mode — no URL configured

- [ ] Set mode to **API** but leave BPE API URL empty.
- [ ] Calculator still works — falls back to local calculator.
- [ ] Response source is `"local"` (graceful fallback).
- [ ] If debug mode is on, fallback is logged in WooCommerce logs.

## API mode — with valid BPE API

- [ ] Set mode to **API**, enter a valid BPE API URL and license key.
- [ ] Calculator calls external API and returns pricing.
- [ ] Response includes `"source": "api"`.
- [ ] Response includes `"offer_signature"`.
- [ ] No license key, tenant ID, or API URL appears in frontend HTML/JS source.

## API mode — API unreachable

- [ ] Set mode to **API** with a non-responsive URL (e.g., `https://localhost:9999`).
- [ ] Calculator falls back to local pricing.
- [ ] Response source is `"local_fallback"`.
- [ ] Frontend shows yellow fallback notice: "Estimated price (service temporarily unavailable)".
- [ ] If debug mode is on, the failure and fallback are logged.

## API mode — API returns validation errors

- [ ] If the external API returns 400 with `errors` array, those errors are shown to the user.
- [ ] No fallback to local in this case (user input is invalid, not a service failure).

## Offer signature

- [ ] Changing any spec field and recalculating produces a different signature.
- [ ] Signature is deterministic — same inputs produce the same signature.
- [ ] `PPP_BPE_Offer_Signer::verify()` returns `true` for a valid data+signature pair.
- [ ] `PPP_BPE_Offer_Signer::verify()` returns `false` if any data field is tampered.

## Health endpoint

- [ ] `GET /wp-json/printpricepro/v1/health` returns current mode.
- [ ] In API/Federated Node mode, response includes `"api_configured": true/false`.
- [ ] No sensitive data (license key, tenant ID) in health response.

## Frontend

- [ ] `lastOffer` JS variable stores signature and source after calculation.
- [ ] Fallback notice only appears when source is `"local_fallback"`.
- [ ] Fallback notice disappears on next successful API calculation.
- [ ] Auto-recalculate (debounced) still works after first calculation.

## Security

- [ ] License key is NOT in `window.pppBpeCalc` or any inline script.
- [ ] API credentials are only sent server-to-server (PHP → BPE API).
- [ ] REST endpoint still validates specs server-side before calling API.
- [ ] Offer signature uses HMAC-SHA256 with site-specific key (wp_salt).

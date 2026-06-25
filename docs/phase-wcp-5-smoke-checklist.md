# Phase WCP-5 — PDF Upload Step — Smoke Checklist

## Upload UI Rendering

- [ ] After completing a WooCommerce order with a PrintPricePro item, the Thank You page shows the upload section
- [ ] The upload section shows title, description, status badge ("Files Required"), and two dropzones (Interior PDF, Cover PDF)
- [ ] In My Account > Orders > View Order, the same upload section appears
- [ ] Orders without PrintPricePro items do not show the upload section
- [ ] Upload styles are responsive — two-column on desktop, single-column on mobile

## File Selection

- [ ] Clicking a dropzone opens the file picker filtered to PDF
- [ ] Dragging a PDF onto a dropzone highlights it (dashed border turns blue)
- [ ] Dropping a PDF shows the filename in the dropzone
- [ ] Selecting a non-PDF file shows an inline error ("Only PDF files are accepted")
- [ ] Selecting a file larger than the configured max size shows an inline error with the size limit
- [ ] The "Upload Files" button remains disabled until at least one file is selected

## Upload Flow

- [ ] Clicking "Upload Files" shows "Uploading…" state, button is disabled
- [ ] Successful upload of both files: success message appears, status badge changes to "Files Uploaded"
- [ ] Uploading only one file (e.g., interior only): status stays "Files Required", uploaded file shows as "Uploaded: filename.pdf (size)"
- [ ] Uploading the second file afterward: status changes to "Files Uploaded"
- [ ] Re-uploading a file replaces the previous one (old file deleted from disk)
- [ ] After successful upload, dropzone shows "Replace" text instead of "Drag & drop"

## File Validation (Server-side)

- [ ] Uploading a non-PDF file (e.g., renamed .jpg to .pdf) returns an error — server checks MIME type
- [ ] Uploading a file exceeding `max_upload_size_mb` returns an error with the size limit
- [ ] Uploading to a non-existent order returns 404
- [ ] Uploading to an order that does not contain PrintPricePro items returns 400

## Permissions & Security

- [ ] Non-logged-in user cannot upload (REST endpoint returns 401/403)
- [ ] Logged-in user can only upload to their own orders
- [ ] Admin (`manage_woocommerce`) can access any order's upload endpoint
- [ ] Uploaded files are stored in `wp-content/uploads/ppp-bpe-files/{order_id}/`
- [ ] The upload directory has `.htaccess` with `Deny from all` — direct URL access returns 403
- [ ] Each order subdirectory has an `index.php` guard file
- [ ] File download endpoint (`GET /orders/{id}/files/interior`) requires authentication
- [ ] Customer can download their own order's files; cannot download other customers' files

## Admin Order View

- [ ] Admin order edit page shows "Production Files" section after billing address
- [ ] Section shows file status badge with correct color (amber = Required, green = Uploaded, red = Rejected)
- [ ] Uploaded files show filename, size, and "Download" button
- [ ] Missing files show "Not uploaded" placeholder
- [ ] Download button serves the PDF with correct filename and Content-Type headers

## REST API Endpoints

- [ ] `POST /wp-json/printpricepro/v1/orders/{id}/upload` — accepts multipart form with `interior_pdf` and/or `cover_pdf`
- [ ] `GET /wp-json/printpricepro/v1/orders/{id}/files` — returns file status and file info (filename, size, uploaded_at)
- [ ] `GET /wp-json/printpricepro/v1/orders/{id}/files/interior` — serves interior PDF as download
- [ ] `GET /wp-json/printpricepro/v1/orders/{id}/files/cover` — serves cover PDF as download
- [ ] Partial upload (one file succeeds, one fails) returns 207 with both `uploaded` and `errors` in response

## Order Status Tracking

- [ ] New order with PrintPricePro items gets `_ppp_bpe_file_status` = `files_required` on checkout
- [ ] After uploading both files, status changes to `files_uploaded`
- [ ] Successful upload adds an order note: "Production files uploaded: interior_pdf, cover_pdf"

## Settings

- [ ] Settings page shows "Max Upload Size (MB)" field with default value of 100
- [ ] Changing the value and saving persists correctly
- [ ] Value is clamped between 1 and 500 MB
- [ ] The configured max size is reflected in both server-side validation and frontend error messages

## Edge Cases

- [ ] Upload works on orders placed by guest users (customer_id = 0) — guest cannot upload, admin can
- [ ] Plugin activation creates the upload directory with protections on first upload
- [ ] Multiple rapid uploads of the same file type do not create orphaned files
- [ ] Large file upload (near the limit) completes without timeout — check server `max_execution_time`

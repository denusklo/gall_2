# Lessons Log

Append-only pitfall/lesson records. Format defined in `knowledge-iteration.md` §2. Compact when over 150 lines (see §3).

<!-- Append new entries below this line, newest last. -->

## 2026-09-27 — Storage/provider pitfalls (durable-journal work)
- **Laravel HTTP client merges headers recursively**: setting Content-Type in both `withHeaders()` and `withBody()` sends `"image/png, image/png"` → Supabase 415 InvalidMimeType. Use `withBody($bytes, $mime)` as the ONLY Content-Type source.
- **Supabase can return not-found as HTTP 400** with typed envelope `{error:"not_found"/"NoSuchKey", statusCode:"404"}`. Never treat bare 400/404 as absence; require the typed envelope AND proven service-role bucket access.
- **Supabase signed upload URLs bind upsert at issuance** (inside the JWT): `POST /object/upload/sign/{bucket}/{path}` with `x-upsert:false` → client-side x-upsert headers cannot override. This is the provider-enforced no-overwrite mechanism.
- **php-fpm-8.2 container pays ~6s TLS handshake to *.supabase.co** (host is fast). Use connection reuse (one Guzzle handler per account scope) and connect_timeout ≥8s; 2s deterministically fails.
- **Guzzle picks StreamHandler when `stream=>true` + allow_url_fopen**, silently bypassing CURLOPT_RESOLVE DNS pinning. Keep `stream=>false` with a bounded PSR-7 sink for pinned transports.
- **Vercel cron activation**: crons register from vercel.json only on PRODUCTION deployments; dashboard page stays empty until then. Declaring the cron entry early is safe if the route fails closed without CRON_SECRET.
- **`vercel.json` static routes are an allowlist**: only listed prefixes (css|js|images) serve from public/; anything else (favicon.ico!) falls through to Laravel. Add explicit routes for new static files.

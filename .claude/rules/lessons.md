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

### 2026-10-02 — php-fpm-8.2 container cannot reach api.github.com; composer dist downloads hang
- **Context:** Phase 0 composer patch bumps for the Laravel upgrade (issue #24), running `composer update` inside the container
- **Symptom:** `composer install`/`update` hangs indefinitely on "Downloading" dist zips; `api.github.com` connection times out from the container, while the host resolves and reaches it fine
- **Root cause / fix:** container's outbound network can't reach api.github.com (packagist metadata works, GitHub dist zips don't). Workaround: download the dist zips on the HOST into a temp composer cache laid out as `<vendor>/<package>/<sha1-of-dist-url>.zip`, then run composer in the container with `COMPOSER_CACHE_DIR` pointed at it; delete the temp cache after
- **Rule of thumb:** composer hanging in php-fpm-8.2 on "Downloading" → it's the container's GitHub egress, not composer; pre-seed the cache from the host instead of retrying
- **Cost:** ~2 hung composer runs (verified during Phase 0, 2026-10-02)

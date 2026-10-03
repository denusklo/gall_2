# Project Reference (detailed)

Detailed project knowledge extracted from the pre-2026-07-08 CLAUDE.md (full original preserved at `CLAUDE.md.bak`). Reference code by symbol name, never line number.

## 1. Database structure

Laravel 12.69.3 + Vue 3 hybrid, verified from `composer.lock` package `laravel/framework` during the email-verification implementation. Laravel 8 references elsewhere are historical. Many-to-many between galleries and images:

- `images`: individual uploaded files (this table was *formerly named `galleries`* — renamed in the Dec 2025 restructure)
- `galleries`: album/collection metadata
- `gallery_images`: pivot with `order` column; unique constraint on `(gallery_id, image_id)`
- `categories`: user-scoped, auto-slug from name, optional `category_id` on images
- `storage_credentials`: per-user storage provider credentials (added Feb 2026, on branch `dev`)
- Standard Laravel: `users`, `sessions`, `personal_access_tokens`

**Gallery–image gotcha**: `galleries.cover_image_id` is only a reference — it does NOT imply membership in `gallery_images`. `GalleryController::store()` compensates by auto-attaching the cover image to the pivot so new galleries don't show "0 images". If you touch gallery creation, preserve that behavior.

## 2. Storage providers

Provider is chosen **per image** via `images.storage_provider`.

**Supabase (primary)** — `storage_provider='supabase'`, plus `storage_bucket`, `storage_path`, `storage_url`.
- Signed URLs, 7-day expiry (604800s), created via `POST /object/sign/{bucket}/{path}` on the Supabase Storage API.
- Upload flow: backend generates signed upload URL → client uploads directly → backend updates the image record.
- URL refresh endpoint: `GET /apiv/_1/images/{id}/signed-url`.

**Vercel Blob (alternative)** — `storage_provider='vercel'`, `storage_url`.
- No official PHP SDK; `VercelBlobController` is a manual re-implementation reverse-engineered from Vercel's TypeScript `client.ts`. Treat it as fragile: verify against Vercel docs (context7) before modifying.
- Read-write token format: `vercel_blob_rw_{storeId}_{secret}`. Client token: `vercel_blob_client_{storeId}_{base64(signature.payload)}`, HMAC-SHA256 signed.
- Flow: `generateClientToken` → client uploads → `upload-callback` updates the record.

## 3. Frontend architecture

**Three separate Vue 3 apps**, not one SPA. Each has its own webpack bundle (see `webpack.mix.js`) and mounts to its own div in a Blade view:

| Bundle | Route | Mount | Root component |
|---|---|---|---|
| `galleryApp.js` | `/images` | `#gallery-app` | `GalleryIndex.vue` |
| `galleriesApp.js` | `/galleries/albums` | `#galleries-app` | `GalleriesIndex.vue` |
| `galleryDetailApp.js` | `/galleries/{id}` | `#gallery-detail-app` | `GalleryView.vue` |

(A fourth entry, `storageSettingsApp.js`, exists on branch `dev` for the storage-credentials UI.)

Shared per-app behavior: Pinia stores (`resources/js/stores/`: `image.js`, `gallery.js`, `category.js`, `storageCredentials.js`), API token fetched from `/apiv/_1/token` on mount, stored in localStorage keyed by `APP_URL`, refreshed every 15 min on visibility change, global Axios interceptors for 401/419.

## 4. API endpoint map

All under `/apiv/_1/` (see `routes/api.php` for the current truth).

- **Images** (`ImageController`): CRUD on `/images`; `POST /images/upload` (Supabase); `GET /images/stats`; `GET /images/{id}/signed-url`.
- **Galleries** (`GalleryController`): CRUD on `/galleries`; `POST|DELETE /galleries/{g}/images/{i}` (attach/detach); `PUT /galleries/{g}/cover`; `PUT /galleries/{g}/images/reorder`.
- **Vercel** (`VercelBlobController`): `POST /vercel/generate-client-token`; `POST /vercel/upload-callback`.
- **Supabase** (`GalleryStorageController`): `POST /storage/generate-upload-url`.
- **Storage credentials** (`StorageCredentialController`, branch `dev`): CRUD on `/storage-credentials`.

## 5. Authentication

Dual system:
1. **Laravel Sanctum** (primary for API): web session login → frontend requests token from `/apiv/_1/token` → Bearer header. Token auto-refresh as in §3.
2. **Firebase Auth** (parallel alternative, firebase-admin SDK). See `AUTHENTICATION_SYSTEM.md` in repo root for the long-form writeup.

**Self-service email verification:** `/user/edit` offers send/resend and explicit status refresh for self only. `EmailVerificationService::handle()` backs POST `/user/email-verification/send` and `/user/email-verification/status`. Both retain web CSRF but deliberately omit redirecting `firebase.auth`; the service requires Laravel authentication, the matching linked Firebase session UID, and a revocation-checked token subject. Only a fresh Firebase UserRecord determines recipient and verification. No email is sent on GET, login or registration. `UserSyncService::mirrorEmailVerification()` updates only an existing UID link, preserves repeated verified timestamps, and clears the timestamp on unverified or mismatched email without relinking. MySQL-to-Firebase creation always starts unverified. Name/phone and owner policies are unchanged.

**Limiter schema activated with explicit user approval:** migration `2026_10_11_000000_create_email_verification_cache_tables` creates dedicated cache and lock tables. Applied to shared production in batch 13 and local Docker `gallery_laravel` in batch 4; verified by guarded migration execution, ledger readback, and `information_schema` confirming both InnoDB tables and columns on 2026-10-03. PR #45 was merged at `785d128`; production email verification is user-confirmed on 2026-10-03, not inferred from isolated delivery tests. Evidence: user confirmation and `/tmp/gallery2-owner-activation-completed.md`. The named `email_verification` store in `config/cache.php` uses the default shared database, not the global cache store. All instances must use that same database and the same configuration. Missing tables or a non-database store fail closed for both status and send. After cheap session/linked-UID and payload checks, `EmailVerificationService::handle()` reserves a combined ten requests per rolling minute per trusted UID before any provider call, including revocation-aware token verification. Pre-provider failures omit the unknown verification verdict. `EmailVerificationService::reserve()` uses Laravel DatabaseStore/native locks in one short transaction, separately reserving one send attempt per 60 seconds and five per rolling hour before sending. Both reservation paths reject Laravel-managed and raw PDO enclosing transactions on the actual store and default connections, and reject a different lock connection. Failed/uncertain sends retain reservations. Final independent verification recorded 244 PHP checks, 30 JS checks and real Chromium fixture checks with stable application hashes (`/tmp/gallery2-email-final-verification.md`). Native MySQL safety verification also passed (`/tmp/gallery2-email-mysql-verification.md`): parallel reservations accepted 1/16 send attempts and 5/24 request attempts, within both upper bounds. Deadlocks and other contention errors failed closed with clean transaction state; this is safety evidence, not a throughput pass. The dedicated migration created both InnoDB tables only in a uniquely named scratch database; the restricted scratch user and database were dropped, with both absence counts confirmed zero. No existing database was modified by these email-verification tests. Do not clear the limiter tables during cooldowns. The email cache migration is activated as recorded above; PR #45 deployment and production verification are user-confirmed, while these isolated tests did not exercise live delivery.

The Kreait convenience sender re-resolves the freshly fetched email internally; concurrent external Firebase Console email reassignment is not atomic with the UID lookup. No ActionCodeSettings override is supplied. Console templates, authorized domains, hosted action handling and delivery require a separately approved test. Source and isolated `/tmp/gallery2-email-harness.php` route inspection establish these sender limits; those isolated checks exercised no production endpoint, migration or mail send. Later production verification was confirmed by the user on 2026-10-03.

## 6. Docker command recipes

Containers: `php-fpm-8.2` (app), `mysql-server` (db `gallery_laravel`, user root/root), `nginx-server`. Other containers on this host belong to OTHER projects — never touch them.

```bash
# Artisan
docker exec php-fpm-8.2 bash -c "cd /var/www/gallery_2 && php artisan migrate:status"

# SQL (often simpler than tinker for data fixes)
docker exec mysql-server mysql -uroot -proot gallery_laravel -e "SELECT id,title FROM galleries;"
```

**Tinker is broken under Docker TTY.** Use a PHP one-liner instead:

```bash
docker exec php-fpm-8.2 bash -c "cd /var/www/gallery_2 && php -r \"
require 'vendor/autoload.php';
\\\$app = require_once 'bootstrap/app.php';
\\\$app->make('Illuminate\\\\Contracts\\\\Console\\\\Kernel')->bootstrap();
\\\$gallery = App\\\\Models\\\\Gallery::find(3);
echo \\\$gallery->images()->count() . PHP_EOL;
\""
```

Escaping rules for the one-liner: `$` → `\\\$` (three backslashes); `\` in class names → `\\\\` (four); outer string double-quoted, inner strings single-quoted.

## 7. Build, env, deployment

- Frontend: Laravel Mix. `npm run dev` / `watch` / `hot` (HMR through nginx proxy for `gallery_2.localhost.dev`) / `production`. **The user runs `npm run watch` themselves — never start any npm build/watch command unless explicitly asked.**
- Required `.env`: `DB_*` (host=mysql, db=gallery_laravel), `SUPABASE_URL|KEY|SERVICE_ROLE_KEY|STORAGE_BUCKET`, `VERCEL_BLOB_READ_WRITE_TOKEN` (optional), `FIREBASE_CREDENTIALS`, `FIREBASE_DATABASE_URL`, `APP_URL` (also keys the frontend localStorage token).
- Deployment: Vercel (`vercel.json`), runtime `vercel-php@0.7.3`, entry `api/index.php`, cache paths under `/tmp`. Env vars set in the Vercel dashboard.

## 8. Testing reality

No real test suite. `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are untouched Laravel boilerplate. Do not claim "tests pass" as evidence of anything; use the verification checks in `judgment-matrix.md` §2. If you add the first real test, note it in `lessons.md` and update this section.

## 9. Historical context

**Dec 2025 restructure**: the original `galleries` table actually stored individual images. It was renamed `images`; a new `galleries` table (albums) and the `gallery_images` pivot were created (`database/migrations/2025_12_06_150200_create_new_gallery_structure.php`). This is why some old variable names/comments look inverted — "gallery" in old code may mean "image".

**Feb 2026 (branch `dev`)**: multi-credential storage system — `StorageCredential` model, `StorageCredentialController`, `CredentialNameService`, storage-settings Vue components and store.

**Sep 2026 (branch `dev`)**: durable storage journal. Every upload/delete commits an intent row (`storage_operations`) before any provider call; provider-enforced no-overwrite (Supabase signed-upload `upsert:false`, Vercel token `allowOverwrite:false`); deletion requires typed absence proof (Supabase may return not-found as HTTP 400 with `NoSuchKey` envelope — accepted only with proven service-role bucket access); env-namespaced paths `app/{env}/{owner}/{operation-uuid}/…` so environments sharing a store cannot collide. Key symbols: `StorageOperationService` (state machine), `StorageAccountService` (immutable account identity), `StorageInspectionService` (read-only, DNS-pinned, connection-reuse per account scope), `UploadReceiptService`. Tables: `storage_accounts`, `storage_operations`, `storage_maintenance_runs` (+ nullable link columns on `storage_credentials`/`images`; migrations refuse to drop populated tables). CLI: `storage:reconcile`, `storage:mapping-review` (proposal-only), `storage:recover-operations`, `storage:run-maintenance`. Daily cron: GET `/apiv/_1/internal/storage-maintenance` behind `AuthenticateStorageCron` (constant-time `CRON_SECRET` Bearer check, fails closed); `vercel.json` declares `0 16 * * *` UTC — activates only on production deployment with `CRON_SECRET` provisioned. Reconciliation is report-only; unmatched remote objects are NEVER auto-deleted (may belong to another environment sharing the store). Legacy pre-journal images with unprovable account bindings need per-proposal manual mapping review.

**Oct 2026 (branch `feat/owner-role-audit`)**: owner role. Authority is Firebase custom claim `owner===true` (owner implies admin; `admin===true` unchanged). No demotion/removal path exists for owners, so the app can never remove the last owner. `FirebaseRoleService` (`isOwner`/`isAdmin`, strict `=== true`, always refetches `getUser`, fails closed) is the single role check used by `FirebaseAdminMiddleware`, `FirebaseOwnerMiddleware` (alias `firebase.owner`, on the `admin.make-admin`/`admin.remove-admin` routes), `FcmController::callerIsFirebaseAdmin`, and the layout nav. `RoleChangeService` is the only claim mutator: cross-instance DB lock (`RoleMutationLock`: dedicated cloned connection holding `SELECT ... FOR UPDATE` on the seeded `role_mutation_lock` row; fails closed; sqlite refused unless both `roles.lock_unsafe_test_mode` and the `testing` app environment are active), refetch, durable `role_change_audits` pending row BEFORE the provider write, then succeeded/failed (reasons are short codes, never provider text); a final-audit failure is reported as partial and leaves the row pending. Bootstrap: `php artisan roles:bootstrap-owner` (dry run default; `--apply --confirm-email=<config('roles.bootstrap_owner_email')>`), fixed to `config/roles.php`. Migration `2026_10_10_000000_create_role_change_audits_table` (creates audits + lock table, seeds the lock row) was applied in the previously approved activation to shared production MySQL in batch 12 and separate Docker `gallery_laravel` in batch 3; schema, ledger and singleton lock-row readbacks succeeded (evidence: `/tmp/gallery2-owner-activation-result.md`). Owner activation subsequently completed on 2026-10-03: the configured account was email-verified, `roles:bootstrap-owner` applied the claim once, fresh Firebase readback confirmed `emailVerified=true`, `owner=true`, `admin=true`, and audit #1 recorded `bootstrap_owner` succeeded. Evidence: `/tmp/gallery2-owner-activation-completed.md` and user confirmation; the earlier unverified preview is historical. Retry verification via `/tmp/gallery2-owner-harness` with mocked Firebase Auth: `vendor/bin/phpunit -c /tmp/gh/phpunit.xml` returned `OK (50 tests, 571 assertions)` with sqlite configured before bootstrap; no durable suite was added. `SetVolunteerClaim::handle()` delegates to the same locked merge/refetch/audit path. `FirebaseUserController::delete()` and `update()` use `RoleChangeService::deleteUser()` / `updateProfile()`: nobody may delete an owner, only that owner may edit its profile, and ordinary admins cannot edit/delete other admins. Owners may edit/delete ordinary admins; ordinary self CRUD remains allowed. Profile/delete intent audits are mandatory before provider calls. `RoleMutationLock::assertHeld()` probes the original raw PDO immediately before a mutation, rejects replacement/disconnection or a missing PDO transaction without automatic reconnect, and fails closed. Audit writes refuse enclosing transactions; the lock and audit tables explicitly use InnoDB on MySQL. Provider exceptions report unknown outcomes with null after flags, not a claim that nothing changed. The layout uses the same Firebase role helper, with no MySQL `is_admin` fallback. Not protected: changes made directly in the Firebase console or session loss immediately after the last lock probe.

## 10. Push/PWA safety and rollout

Verified source and isolated reports on 2026-10-03:

- `FcmTokenService` uses single-record ETag conditional authority at `fcm_token_owners/{sha256(token)}`, generation-matched indexes at `users/{uid}/fcm_tokens/{sha256(token)}`, revocable `push_sessions/{id}` and bearer mappings at `push_bearers/{sha256(origin)}/{Sanctum_id}`. Cross-UID/session claims return 409, never implicit transfer; stale DELETE cannot revoke a newer generation. `AuthController::hasMatchingWebLogin()` requires the actual web-session guard login key and matching persisted Laravel/Firebase identity before minting a binding. Bearer-only issuance remains API-compatible but cannot bind push. Push authorization survives bearer expiry/refresh; `UnifiedAuthController::logout()` revokes only this push session/current bearer, preserving other devices.
- Legacy tokens require authenticated reenrollment; missing authority never falls back to legacy delivery. Distinct same-origin devices survive. `CleanupFcmTokens` old/domain-duplicate cleanup is a compatibility no-op; invalid cleanup preserves unknown failures and retires only structured UNREGISTERED evidence. `FcmNotificationService` protects `recipient_uid` and reports provider acceptance, not delivery. In-flight sends can pass their final check before logout and submit afterward; queued messages and failed revocation also prevent zero-post-logout guarantees. Session expiry alone does not unsubscribe.
- `PushOrigin` normalizes fixed `config/push.php` origin, preserving scheme/nondefault port. `PUSH_ORIGIN` is an explicit deployment-scoped canonical override; otherwise `VERCEL_ENV=preview` uses `https://VERCEL_URL`, with no production fallback when absent; production/non-Preview uses `APP_URL`. Client domain/forwarded headers cannot choose another environment. New RTDB authority/session/bearer paths must be server-only, with client access denied. Live rules, deployment values and provider behavior remain unchecked, not claimed configured. Staging requirements: `/tmp/gallery2-pwa-push-deployment.md`.
- `FcmService.requestPermissionAndGetToken()` in `resources/js/fcm.js` requests permission directly from a user gesture, not startup. iOS/iPadOS requires supported iOS 16.4+ Home Screen installation; supported Android browsers need not install. Explicit enable/retry handles 409 by retiring the SDK token and registering a new token. The one root `firebase-messaging-sw.js` remains online-only, with no fetch/auth-content cache; Firebase displays background notification payloads once, and clicks stay on same-origin `/images`.
- Evidence: `/tmp/gallery2-pwa-final-backend-verification.md` passed 137 checks plus one verdict; `/tmp/gallery2-pwa-final-frontend-verification.md` passed 35 Node groups, 16 UI and 88 PWA cases except the subsequently fixed worker await; `/tmp/gallery2-pwa-worker-final-verification.md` passed 23 worker groups and reran all 35 groups, hash-confirming other frontend sources unchanged. SQLite/fake ETag, mocked SDK and offline/CSP Chromium fixtures are not real provider or iPhone/Android delivery tests. User `npm run watch`, built-bundle/device/deployment verification remain required; no agent build ran.

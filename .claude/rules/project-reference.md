# Project Reference (detailed)

Detailed project knowledge extracted from the pre-2026-07-08 CLAUDE.md (full original preserved at `CLAUDE.md.bak`). Reference code by symbol name, never line number.

## 1. Database structure

Laravel 8 + Vue 3 hybrid. Many-to-many between galleries and images:

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

**Feb 2026 (branch `dev`, uncommitted at time of writing)**: multi-credential storage system — `StorageCredential` model, `StorageCredentialController`, `CredentialNameService`, storage-settings Vue components and store.

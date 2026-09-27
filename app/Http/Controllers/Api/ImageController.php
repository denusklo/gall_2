<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Category;
use App\Services\Storage\StorageCredentialService;
use App\Services\Storage\UploadReceiptService;
use App\Services\Storage\StorageOperationService;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller {
    protected StorageCredentialService $credentialService;

    protected UploadReceiptService $receipts;

    protected StorageOperationService $operations;

    public function __construct(StorageCredentialService $credentialService, UploadReceiptService $receipts,
        StorageOperationService $operations) {
        $this->credentialService = $credentialService;
        $this->receipts = $receipts;
        $this->operations = $operations;
    }

    /**
     * Display a listing of images.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $query = Image::where('user_id', auth()->id());

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('filename', 'like', '%' . $request->search . '%');
            });
        }

        // Apply file type filter
        if ($request->has('file_type') && !empty($request->file_type)) {
            $query->where('mime_type', 'like', $request->file_type . '%');
        }

        // Apply category filter
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'newest');
        switch ($sortBy) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'name_asc':
                $query->orderBy('title', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('title', 'desc');
                break;
            case 'size_asc':
                $query->orderBy('size', 'asc');
                break;
            case 'size_desc':
                $query->orderBy('size', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Paginate the results
        $perPage = $request->get('per_page', 12);
        $images = $query->with(['categories', 'galleries'])->paginate($perPage);

        return response()->json($images);
    }

    /**
     * Store a newly created image in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => [Rule::exists('categories', 'id')->where('user_id', auth()->id())],
            'receipt' => 'required|string',
            'storage_path' => 'required|string',
            'storage_bucket' => 'required|string',
            'storage_url' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string',
            'size' => 'required|integer',
            'storage_provider' => 'nullable|in:supabase',
            'credential_id' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $receipt = $this->receipts->read($request->receipt, $user, 'supabase');
        abort_unless(!empty($receipt['credential_id']), 422, 'A saved Supabase account is required.');
        $creds = $this->credentialService->getSupabaseCredentials($user, $receipt['credential_id']);
        $this->receipts->assertAccount($receipt, $creds);
        $url = rtrim($creds['url'], '/') . '/storage/v1/object/public/' . $receipt['bucket'] . '/' . $receipt['path'];
        $this->receipts->matches($request->storage_path === $receipt['path'], 'storage_path');
        $this->receipts->matches($request->storage_bucket === $receipt['bucket'], 'storage_bucket');
        $this->receipts->matches($request->storage_url === $url, 'storage_url');
        $this->receipts->matches($request->filename === $receipt['metadata']['filename'], 'filename');
        $this->receipts->matches($request->mime_type === $receipt['metadata']['content_type'], 'mime_type');
        $this->receipts->matches((int) $request->size === $receipt['metadata']['size'], 'size');
        if ($request->exists('credential_id')) {
            $this->receipts->matches($request->input('credential_id') === null
                ? $receipt['credential_id'] === null
                : (int) $request->credential_id === $receipt['credential_id'], 'credential_id');
        }

        $op = $this->operations->uploadForReceipt($user, $receipt['operation_id'] ?? null);
        abort_unless(is_string($creds['service_key']) && trim($creds['service_key']) !== '',
            422, 'A Supabase service key is required for upload verification.');
        // Browser completion is only a hint: verify the exact reserved object's provider metadata.
        $evidence = $this->operations->supabaseObjectInfo($op, $creds);
        if ($evidence['state'] !== 'present') {
            $this->operations->recordFailure($op, 'verification_' . $evidence['state']);
            return response()->json(['error' => $evidence['state'] === 'missing'
                ? 'The uploaded object was not found yet.' : 'Unable to verify uploaded object.'] + $op->publicFields(),
                $evidence['state'] === 'missing' ? 409 : 502);
        }

        $image = $this->receipts->register($user, $receipt, function ($operation) use ($request, $user, $receipt, $url, $evidence) {
            $image = Image::create([
                'title' => $request->title,
                'description' => $request->description,
                'storage_path' => $receipt['path'],
                'storage_bucket' => $receipt['bucket'],
                'storage_url' => $url,
                'filename' => $receipt['metadata']['filename'],
                'mime_type' => $receipt['metadata']['content_type'],
                'size' => $receipt['metadata']['size'],
                'storage_provider' => 'supabase',
                'storage_credential_id' => $receipt['credential_id'],
                'user_id' => $user->id,
            ]);
            $image->categories()->attach($request->input('category_ids', []) ?? []);
            $this->operations->markRegistered($operation, $image, $evidence['observed']);
            return $image->load('categories');
        });

        return response()->json($image, 201);
    }

    /**
     * Display the specified image.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id) {
        $image = Image::with(['categories', 'galleries'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        return response()->json($image);
    }

    /**
     * Update the specified image in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        $image = Image::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => [Rule::exists('categories', 'id')->where('user_id', auth()->id())],
        ]);

        try {
            $image->update($request->only([
                'title',
                'description'
            ]));

            // Sync categories if provided
            if ($request->has('category_ids')) {
                if (is_array($request->category_ids)) {
                    $image->categories()->sync($request->category_ids);
                } else {
                    $image->categories()->sync([]);
                }
            }

            // Load relationships for response
            $image->load(['categories', 'galleries']);

            return response()->json($image);
        } catch (\Exception $e) {
            Log::error('Error updating image: ' . $e->getMessage(), [
                'exception' => $e,
                'image_id' => $id,
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Failed to update image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified image from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        $image = Image::withTrashed()->where('user_id', auth()->id())->findOrFail($id);
        if ($image->trashed()) {
            // Old two-step clients: the provider wrapper already finalized this durable delete.
            $op = \App\Models\StorageOperation::where('delete_image_id', $image->id)
                ->whereIn('state', ['tombstoned', 'finalized'])->first();
            abort_unless($op !== null, 404);
            return response()->json(['message' => 'Image deleted successfully', 'replayed' => true] + $op->publicFields());
        }
        return $this->deleteDurably($image);
    }

    /** Shared by the image, Supabase and Vercel delete endpoints. */
    public function deleteDurably(Image $image) {
        abort_unless($image->isSupabaseStorage() || $image->isVercelStorage(), 422, 'Unsupported storage provider.');
        if ($image->isSupabaseStorage()) {
            $creds = $this->supabaseCredsForImage($image);
            abort_unless(is_string($creds['service_key']) && trim($creds['service_key']) !== '',
                422, 'A Supabase service key is required for deletion.');
        } else {
            abort_unless($image->storage_credential_id && $image->storage_path, 422,
                'A recorded Vercel account and path are required.');
            $creds = $this->credentialService->getVercelCredentials(auth()->user(), $image->storage_credential_id);
        }
        try {
            [$status, $body] = $this->operations->deleteImage(auth()->user(), $image, $creds);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error deleting image', ['image_id' => $image->id]);
            return response()->json(['error' => 'Storage deletion failed.'], 502);
        }
        return response()->json($body, $status);
    }

    /**
     * Get statistics about images.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats() {
        try {
            $userId = auth()->id();

            // Get total images count
            $totalImages = Image::where('user_id', $userId)->count();

            // Get total storage used in bytes
            $totalStorage = Image::where('user_id', $userId)->sum('size');

            // Get recent uploads (last 30 days)
            $recentUploads = Image::where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(30))->count();

            // Get file types distribution
            $fileTypes = Image::where('user_id', $userId)
                ->select(
                    DB::raw("SUBSTRING_INDEX(mime_type, '/', 1) as type"),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('type')
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => $item->type,
                        'count' => $item->count,
                    ];
                });

            // Get timeline of uploads (last 12 months) — one grouped query instead of 12 counts
            $monthlyCounts = Image::where('user_id', $userId)
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('ym')
                ->pluck('count', 'ym');

            $timeline = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $timeline[] = [
                    'month' => $month->format('M Y'),
                    'count' => (int) ($monthlyCounts[$month->format('Y-m')] ?? 0),
                ];
            }

            return response()->json([
                'totalImages' => $totalImages,
                'totalStorage' => $totalStorage,
                'recentUploads' => $recentUploads,
                'fileTypes' => $fileTypes,
                'timeline' => $timeline,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching image stats: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return response()->json(['error' => 'Failed to fetch image stats: ' . $e->getMessage()], 500);
        }
    }

    public function upload(Request $request) {
        // Validate the request
        $request->validate([
            'file' => 'required|file',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => [Rule::exists('categories', 'id')->where('user_id', auth()->id())],
            'credential_id' => 'nullable|integer|min:1',
        ]);
        $creds = $this->credentialService->getSupabaseCredentials(auth()->user(), $request->input('credential_id'));
        abort_unless(!empty($creds['credential_id']), 422, 'A saved Supabase account is required.');
        abort_unless(is_string($creds['service_key']) && trim($creds['service_key']) !== '',
            422, 'A Supabase service key is required for uploads.');

        $user = $request->user();
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        abort_if($size > 52428800, 422, 'The file may not be greater than 50 MB.');
        // Durable intent committed before any provider write.
        $op = $this->operations->reserveUpload($user, 'supabase', $creds, $creds['bucket'], $file->getClientOriginalName(),
            $mime, $size, ['title' => $request->title, 'description' => $request->description,
                'category_ids' => array_values($request->input('category_ids', []) ?? []),
                'filename' => $file->getClientOriginalName()],
            (int) config('storage_maintenance.lease_seconds', 60));
        $lease = $this->operations->claimLease($op);
        abort_unless($lease !== null, 409, 'This upload is already in progress.');
        $this->operations->fenced($op, $lease, ['state' => 'uploading', 'remote_started_at' => now(),
            'attempt_count' => \Illuminate\Support\Facades\DB::raw('attempt_count + 1')]);
        $outcome = $this->operations->supabaseServerUpload($op, $creds, (string) file_get_contents($file->getRealPath()), $mime);
        if ($outcome !== 'uploaded') {
            $this->operations->release($op, $lease, ['state' => $outcome, 'last_error_code' => $outcome, 'last_observed_at' => now()]);
            return response()->json(['message' => 'Supabase upload or signing failed.'] + $op->publicFields(), 500);
        }
        // Server-held bytes accepted by the provider for this exact reserved path; no overwrite.
        $this->operations->fenced($op, $lease, ['state' => 'uploaded', 'observed_size' => $size,
            'observed_mime' => $mime, 'last_observed_at' => now()]);

        try {
            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $creds['service_key'],
                'Authorization' => 'Bearer ' . $creds['service_key']
            ])->post(
                rtrim($creds['url'], '/') . '/storage/v1/object/sign/' . rawurlencode($op->bucket) . '/'
                    . StorageOperationService::encodePath($op->path),
                ['expiresIn' => 604800] // 7 days
            );
            if (!$signResponse->successful()) {
                throw new \RuntimeException('Supabase signing failed.');
            }
            $signedUrl = $this->normalizeSupabaseSignedUrl($signResponse->json('signedURL'), $creds, $op->bucket, $op->path);
            $image = $this->operations->registerFromOperation($user, $op, [
                'title' => $request->title,
                'description' => $request->description,
                'storage_url' => $signedUrl,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $size,
            ], ['size' => $size, 'content_type' => $mime]);
            return response()->json($image, 201);
        } catch (\Exception $e) {
            // The object exists at the reserved path; the journal keeps it recoverable.
            $op->refresh();
            if ($op->lease_token === $lease['token']) {
                $this->operations->release($op, $lease, ['last_error_code' => 'register_or_sign_failed']);
            }
            return response()->json(['message' => 'Supabase upload or signing failed.'] + $op->publicFields(), 500);
        }
    }

    /** Resolve only the recorded account; legacy unbound images need account mapping. */
    private function supabaseCredsForImage(Image $image): array
    {
        abort_unless($image->storage_credential_id && $image->storage_bucket && $image->storage_path,
            422, 'A recorded Supabase account, bucket and path are required.');
        return $this->credentialService->getSupabaseCredentials(auth()->user(), $image->storage_credential_id);
    }

    private function normalizeSupabaseSignedUrl($value, array $creds, string $bucket, string $path): string
    {
        $base = rtrim($creds['url'], '/');
        $account = parse_url($base);
        $url = is_string($value) ? parse_url($value) : false;
        if (!$url || !$account || !in_array($account['scheme'] ?? '', ['https', 'http'], true)
            || empty($account['host']) || isset($account['user']) || isset($account['pass'])
            || !empty($account['path']) || isset($account['query']) || isset($account['fragment'])
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $value)
            || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])) {
            throw new \RuntimeException('Invalid Supabase signed URL.');
        }
        if (isset($url['scheme']) || isset($url['host'])) {
            if (($url['scheme'] ?? '') !== $account['scheme']
                || strtolower($url['host'] ?? '') !== strtolower($account['host'])
                || ($url['port'] ?? null) !== ($account['port'] ?? null)) {
                throw new \RuntimeException('Invalid Supabase signed URL account.');
            }
        }
        $signedPath = '/' . ltrim($url['path'] ?? '', '/');
        if (strpos($signedPath, '/storage/v1/') === 0) {
            $signedPath = substr($signedPath, strlen('/storage/v1'));
        }
        $expected = '/object/sign/' . $bucket . '/' . $path;
        parse_str($url['query'] ?? '', $query);
        if (rawurldecode($signedPath) !== $expected || !is_string($query['token'] ?? null) || $query['token'] === '') {
            throw new \RuntimeException('Invalid Supabase signed object URL.');
        }
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $expected)));
        return $base . '/storage/v1' . $encodedPath . '?' . $url['query'];
    }

    // Create a route to generate a signed URL
    public function refreshSignedUrl(Request $request, $imageId) {
        $image = Image::where('user_id', auth()->id())->findOrFail($imageId);
        abort_unless($image->isSupabaseStorage(), 422, 'Signed URL refresh requires a Supabase image.');
        $creds = $this->supabaseCredsForImage($image);
        abort_unless(is_string($creds['service_key']) && trim($creds['service_key']) !== '',
            422, 'A Supabase service key is required for signed URLs.');

        try {

            // Make a request to Supabase to generate a signed URL
            $path = $image->storage_path;
            $bucket = $image->storage_bucket;

            $signResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $creds['service_key'],
                'Authorization' => 'Bearer ' . $creds['service_key']
            ])->post(
                rtrim($creds['url'], '/') . '/storage/v1/object/sign/' . rawurlencode($bucket) . '/'
                    . implode('/', array_map('rawurlencode', explode('/', $path))),
                ['expiresIn' => 604800] // 7 days
            );

            if (!$signResponse->successful()) {
                throw new \RuntimeException('Supabase signing failed.');
            }

            $signedUrl = $this->normalizeSupabaseSignedUrl($signResponse->json('signedURL'), $creds, $bucket, $path);
            $image->storage_url = $signedUrl;
            $image->save();

            return response()->json(['signedUrl' => $signedUrl]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh signed URL.'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageCredential;
use App\Services\CredentialNameService;
use App\Services\UserSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StorageCredentialController extends Controller
{
    /**
     * The credential name service.
     */
    protected CredentialNameService $nameService;

    /**
     * The user settings service for testing credentials.
     */
    protected UserSettingsService $settingsService;

    /**
     * Create a new controller instance.
     */
    public function __construct(CredentialNameService $nameService, UserSettingsService $settingsService)
    {
        $this->nameService = $nameService;
        $this->settingsService = $settingsService;
    }

    /**
     * Display a listing of the user's storage credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $credentials = Auth::user()
            ->storageCredentials()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($credential) {
                return [
                    'id' => $credential->id,
                    'name' => $credential->name,
                    'provider' => $credential->provider,
                    'is_default' => $credential->is_default,
                    'is_verified' => $credential->is_verified,
                    'verified_at' => $credential->verified_at?->toIso8601String(),
                    'created_at' => $credential->created_at->toIso8601String(),
                    'updated_at' => $credential->updated_at->toIso8601String(),
                    // Supabase info (masked)
                    'supabase_url' => $credential->supabase_url,
                    'supabase_bucket' => $credential->supabase_bucket,
                    'supabase_key_masked' => $credential->supabase_key
                        ? StorageCredential::maskKey($credential->supabase_key)
                        : null,
                    'supabase_service_key_masked' => $credential->supabase_service_key
                        ? StorageCredential::maskKey($credential->supabase_service_key)
                        : null,
                    // Vercel info (masked)
                    'vercel_blob_store_url' => $credential->vercel_blob_store_url,
                    'vercel_blob_token_masked' => $credential->vercel_blob_token
                        ? StorageCredential::maskKey($credential->vercel_blob_token)
                        : null,
                ];
            });

        return response()->json($credentials);
    }

    /**
     * Store a newly created storage credential.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => ['required', 'in:supabase,vercel'],
            'is_default' => ['boolean'],
            // Supabase fields
            'supabase_url' => ['required_if:provider,supabase', 'url'],
            'supabase_key' => ['required_if:provider,supabase', 'string', 'min:20'],
            'supabase_service_key' => ['nullable', 'string', 'min:20'],
            'supabase_bucket' => ['nullable', 'string'],
            // Vercel fields
            'vercel_blob_token' => ['required_if:provider,vercel', 'string', 'min:30'],
            'vercel_blob_store_url' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        // Generate unique random name
        $name = $this->nameService->generate();

        // Create credential
        $credential = new StorageCredential([
            'user_id' => $user->id,
            'name' => $name,
            'provider' => $request->provider,
            'is_default' => $request->boolean('is_default', false),
        ]);

        // Set provider-specific fields
        if ($request->provider === 'supabase') {
            $credential->supabase_url = $request->supabase_url;
            $credential->supabase_key = $request->supabase_key;
            $credential->supabase_service_key = $request->supabase_service_key;
            $credential->supabase_bucket = $request->supabase_bucket ?? 'images';
        } elseif ($request->provider === 'vercel') {
            $credential->vercel_blob_token = $request->vercel_blob_token;
            $credential->vercel_blob_store_url = $request->vercel_blob_store_url ?? 'https://blob.vercel-storage.com';
        }

        $credential->save();

        // Handle default credential
        if ($credential->is_default) {
            $this->ensureSingleDefault($user->id, $credential->id);
        }

        return response()->json([
            'id' => $credential->id,
            'name' => $credential->name,
            'provider' => $credential->provider,
            'is_default' => $credential->is_default,
            'message' => 'Storage credential created successfully',
        ], 201);
    }

    /**
     * Display the specified storage credential.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $credential = Auth::user()
            ->storageCredentials()
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'id' => $credential->id,
            'name' => $credential->name,
            'provider' => $credential->provider,
            'is_default' => $credential->is_default,
            'is_verified' => $credential->is_verified,
            'verified_at' => $credential->verified_at?->toIso8601String(),
            'created_at' => $credential->created_at->toIso8601String(),
            'updated_at' => $credential->updated_at->toIso8601String(),
            // Supabase info (masked)
            'supabase_url' => $credential->supabase_url,
            'supabase_bucket' => $credential->supabase_bucket,
            'supabase_key_masked' => $credential->supabase_key
                ? StorageCredential::maskKey($credential->supabase_key)
                : null,
            'supabase_service_key_masked' => $credential->supabase_service_key
                ? StorageCredential::maskKey($credential->supabase_service_key)
                : null,
            // Vercel info (masked)
            'vercel_blob_store_url' => $credential->vercel_blob_store_url,
            'vercel_blob_token_masked' => $credential->vercel_blob_token
                ? StorageCredential::maskKey($credential->vercel_blob_token)
                : null,
        ]);
    }

    /**
     * Update the specified storage credential.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $credential = Auth::user()
            ->storageCredentials()
            ->where('id', $id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'is_default' => ['boolean'],
            // Supabase fields
            'supabase_url' => ['nullable', 'url'],
            'supabase_key' => ['nullable', 'string', 'min:20'],
            'supabase_service_key' => ['nullable', 'string', 'min:20'],
            'supabase_bucket' => ['nullable', 'string'],
            // Vercel fields
            'vercel_blob_token' => ['nullable', 'string', 'min:30'],
            'vercel_blob_store_url' => ['nullable', 'url'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update fields
        $credential->fill($request->only([
            'supabase_url',
            'supabase_key',
            'supabase_service_key',
            'supabase_bucket',
            'vercel_blob_token',
            'vercel_blob_store_url',
        ]));

        // Mark as unverified if credentials changed
        if ($credential->isDirty([
            'supabase_url',
            'supabase_key',
            'supabase_service_key',
            'vercel_blob_token',
        ])) {
            $credential->is_verified = false;
            $credential->verified_at = null;
        }

        // Handle default credential
        if ($request->has('is_default')) {
            $credential->is_default = $request->boolean('is_default');
            if ($credential->is_default) {
                $this->ensureSingleDefault(Auth::id(), $credential->id);
            }
        }

        $credential->save();

        return response()->json([
            'id' => $credential->id,
            'name' => $credential->name,
            'provider' => $credential->provider,
            'is_default' => $credential->is_default,
            'is_verified' => $credential->is_verified,
            'message' => 'Storage credential updated successfully',
        ]);
    }

    /**
     * Remove the specified storage credential.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $credential = Auth::user()
            ->storageCredentials()
            ->where('id', $id)
            ->firstOrFail();

        $credential->delete();

        return response()->json([
            'message' => 'Storage credential deleted successfully',
        ]);
    }

    /**
     * Set a credential as default.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function setDefault(int $id): JsonResponse
    {
        $credential = Auth::user()
            ->storageCredentials()
            ->where('id', $id)
            ->firstOrFail();

        $credential->setAsDefault();

        return response()->json([
            'message' => 'Default credential updated successfully',
            'id' => $credential->id,
            'name' => $credential->name,
        ]);
    }

    /**
     * Test Supabase credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testSupabase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => ['required', 'url'],
            'key' => ['required', 'string', 'min:20'],
            'service_key' => ['nullable', 'string', 'min:20'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->settingsService->testSupabaseConnection(
            $request->url,
            $request->key,
            $request->service_key
        );

        return response()->json($result);
    }

    /**
     * Test Vercel Blob credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testVercel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'min:30'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->settingsService->testVercelBlobConnection($request->token);

        return response()->json($result);
    }

    /**
     * Test a saved credential using its stored (decrypted) keys and,
     * on success, mark it as verified.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function testCredential(int $id): JsonResponse
    {
        $credential = Auth::user()
            ->storageCredentials()
            ->where('id', $id)
            ->firstOrFail();

        if ($credential->provider === 'supabase') {
            $result = $this->settingsService->testSupabaseConnection(
                $credential->supabase_url,
                $credential->supabase_key,
                $credential->supabase_service_key
            );
        } else {
            $result = $this->settingsService->testVercelBlobConnection(
                $credential->vercel_blob_token
            );
        }

        if (!empty($result['success'])) {
            $credential->markAsVerified();
        } else {
            $credential->markAsUnverified();
        }

        return response()->json(array_merge($result, [
            'is_verified' => $credential->is_verified,
            'verified_at' => $credential->verified_at?->toIso8601String(),
        ]));
    }

    /**
     * Ensure only one credential is marked as default for the user.
     *
     * @param  int  $userId
     * @param  int  $exceptId
     * @return void
     */
    protected function ensureSingleDefault(int $userId, int $exceptId): void
    {
        StorageCredential::where('user_id', $userId)
            ->where('id', '!=', $exceptId)
            ->update(['is_default' => false]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    protected UserSettingsService $settingsService;

    public function __construct(UserSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Get the current user's storage settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $settings = $this->settingsService->getOrCreateSettings($request->user());

        // Return masked sensitive data for security
        $response = [
            'storage_provider' => $settings->storage_provider,
            'credentials_verified' => $settings->credentials_verified,
            'last_verified_at' => $settings->last_verified_at?->toIso8601String(),
            'has_supabase_config' => $settings->hasSupabaseConfig(),
            'has_vercel_config' => $settings->hasVercelConfig(),
        ];

        // Include non-sensitive details
        if ($settings->supabase_url) {
            $response['supabase_url'] = $settings->supabase_url;
        }
        if ($settings->supabase_bucket) {
            $response['supabase_bucket'] = $settings->supabase_bucket;
        }
        if ($settings->vercel_blob_store_url) {
            $response['vercel_blob_store_url'] = $settings->vercel_blob_store_url;
        }

        // Mask keys - show only first/last few characters
        if ($settings->supabase_key) {
            $response['supabase_key_masked'] = $this->maskKey($settings->supabase_key);
        }
        if ($settings->supabase_service_key) {
            $response['supabase_service_key_masked'] = $this->maskKey($settings->supabase_service_key);
        }
        if ($settings->vercel_blob_token) {
            $response['vercel_blob_token_masked'] = $this->maskKey($settings->vercel_blob_token);
        }

        return response()->json($response);
    }

    /**
     * Create initial storage settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->settings) {
            return response()->json([
                'error' => 'Settings already exist. Use PUT to update.',
            ], 400);
        }

        return $this->saveSettings($request);
    }

    /**
     * Update storage settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        return $this->saveSettings($request);
    }

    /**
     * Delete credentials for a specific provider.
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function deleteProvider(Request $request, string $provider): JsonResponse
    {
        $validProviders = ['supabase', 'vercel'];

        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'error' => 'Invalid provider. Must be "supabase" or "vercel".',
            ], 400);
        }

        try {
            $this->settingsService->deleteProviderCredentials($request->user(), $provider);

            return response()->json([
                'message' => "{$provider} credentials deleted successfully.",
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting provider credentials', [
                'exception' => $e->getMessage(),
                'provider' => $provider,
            ]);

            return response()->json([
                'error' => 'Failed to delete credentials: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Supabase connection before saving.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testSupabase(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'key' => 'required|string|min:20',
            'service_key' => 'nullable|string|min:20',
        ]);

        $result = $this->settingsService->testSupabaseConnection(
            $request->url,
            $request->key,
            $request->service_key
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Test Vercel Blob connection before saving.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testVercel(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|min:20',
        ]);

        $result = $this->settingsService->testVercelBlobConnection($request->token);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Save settings (shared by store and update).
     *
     * @param Request $request
     * @return JsonResponse
     */
    protected function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'storage_provider' => ['nullable', Rule::in(['supabase', 'vercel'])],

            // Supabase credentials
            'supabase_url' => 'nullable|url',
            'supabase_key' => 'nullable|string|min:20',
            'supabase_service_key' => 'nullable|string|min:20',
            'supabase_bucket' => 'nullable|string|max:255',

            // Vercel Blob credentials
            'vercel_blob_token' => 'nullable|string|min:20',
            'vercel_blob_store_url' => 'nullable|url|max:255',
        ]);

        try {
            $settings = $this->settingsService->updateSettings(
                $request->user(),
                $request->only([
                    'storage_provider',
                    'supabase_url',
                    'supabase_key',
                    'supabase_service_key',
                    'supabase_bucket',
                    'vercel_blob_token',
                    'vercel_blob_store_url',
                ])
            );

            return response()->json([
                'message' => 'Settings saved successfully.',
                'settings' => [
                    'storage_provider' => $settings->storage_provider,
                    'credentials_verified' => $settings->credentials_verified,
                    'has_supabase_config' => $settings->hasSupabaseConfig(),
                    'has_vercel_config' => $settings->hasVercelConfig(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving user settings', [
                'exception' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'error' => 'Failed to save settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mask a key for display (show first 6 and last 4 characters).
     *
     * @param string $key
     * @return string
     */
    protected function maskKey(string $key): string
    {
        $length = strlen($key);

        if ($length < 10) {
            return str_repeat('*', $length);
        }

        $start = substr($key, 0, 6);
        $end = substr($key, -4);
        $middleLength = $length - 10;

        return $start . str_repeat('*', $middleLength) . $end;
    }
}

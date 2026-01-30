<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UnifiedAuthController extends Controller
{
    protected $syncService;
    protected $firebaseAuth;

    public function __construct(UserSyncService $syncService)
    {
        $this->syncService = $syncService;
        $this->firebaseAuth = app('firebase.auth');
    }

    /**
     * Show the unified login form
     */
    public function showLoginForm()
    {
        return view('auth.unified.login');
    }

    /**
     * Handle unified login - accepts either Firebase or Laravel credentials
     * Automatically syncs users between both systems
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;

        // Strategy 1: Try Firebase authentication first
        try {
            // Check if user exists in Firebase
            $firebaseUser = $this->firebaseAuth->getUserByEmail($email);

            // Attempt Firebase sign in
            $signInResult = $this->firebaseAuth->signInWithEmailAndPassword($email, $password);
            $idToken = $signInResult->idToken();
            $firebaseUid = $firebaseUser->uid;

            // Verify the token
            $verifiedToken = $this->syncService->verifyFirebaseToken($idToken);

            if ($verifiedToken) {
                // Get Firebase user data
                $firebaseUserData = [
                    'email' => $firebaseUser->email,
                    'displayName' => $firebaseUser->displayName,
                    'emailVerified' => $firebaseUser->emailVerified,
                    'phoneNumber' => $firebaseUser->phoneNumber,
                ];

                // Sync to MySQL
                $mysqlUser = $this->syncService->syncFirebaseToMysql($firebaseUid, $idToken, $firebaseUserData);

                // Store both Firebase and Laravel sessions
                session()->put('verified_user_id', $firebaseUid);
                session()->put('idTokenString', $idToken);
                session()->put('displayName', $firebaseUser->displayName ?? $mysqlUser->name);

                // Log in via Laravel Auth (Sanctum)
                Auth::login($mysqlUser);

                // Generate Sanctum API token
                $sanctumToken = $mysqlUser->createToken('auth-token')->plainTextToken;
                session(['api_token' => $sanctumToken]);

                Log::info('Unified login successful via Firebase', [
                    'mysql_user_id' => $mysqlUser->id,
                    'firebase_uid' => $firebaseUid,
                ]);

                return redirect()->intended('home')->with('success', 'Logged in successfully');
            }
        } catch (\Kreait\Firebase\Auth\SignInFailed $e) {
            // Firebase sign in failed - try Laravel auth
            Log::info('Firebase sign in failed, trying Laravel auth', [
                'email' => $email,
                'firebase_error' => $e->getMessage(),
            ]);
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // User not in Firebase - try Laravel auth
            Log::info('User not found in Firebase, trying Laravel auth', [
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            // Other Firebase errors - try Laravel auth
            Log::info('Firebase error, trying Laravel auth', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        // Strategy 2: Try Laravel authentication
        if (Auth::attempt($request->only('email', 'password'))) {
            $mysqlUser = Auth::user();

            // Sync to Firebase
            try {
                $firebaseData = $this->syncService->syncMysqlToFirebase($mysqlUser, $password);

                // Store Firebase session data
                session()->put('verified_user_id', $firebaseData['firebase_uid']);
                session()->put('idTokenString', $firebaseData['id_token']);
                session()->put('displayName', $mysqlUser->name);

                // Update refresh token in user record
                $mysqlUser->firebase_refresh_token = $firebaseData['refresh_token'];
                $mysqlUser->save();

                Log::info('Unified login successful via Laravel, synced to Firebase', [
                    'mysql_user_id' => $mysqlUser->id,
                    'firebase_uid' => $firebaseData['firebase_uid'],
                ]);
            } catch (\Exception $e) {
                // Firebase sync failed, but Laravel login succeeded
                Log::warning('Laravel login succeeded but Firebase sync failed', [
                    'mysql_user_id' => $mysqlUser->id,
                    'error' => $e->getMessage(),
                ]);

                // Continue with Laravel-only login
                session()->put('displayName', $mysqlUser->name);
            }

            // Generate Sanctum API token
            $sanctumToken = $mysqlUser->createToken('auth-token')->plainTextToken;
            session(['api_token' => $sanctumToken]);

            return redirect()->intended('home')->with('success', 'Logged in successfully');
        }

        // Both authentication methods failed
        Log::warning('Unified login failed for both Firebase and Laravel', [
            'email' => $email,
        ]);

        return redirect()->route('login')
            ->withInput()
            ->with('error', 'Invalid credentials');
    }

    /**
     * Handle unified registration
     * Creates user in both Firebase and MySQL
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $name = $request->name;
        $email = $request->email;
        $password = $request->password;

        // Step 1: Create Firebase user
        try {
            $userProperties = [
                'email' => $email,
                'emailVerified' => false,
                'password' => $password,
                'displayName' => $name,
                'disabled' => false,
            ];

            $firebaseUser = $this->firebaseAuth->createUser($userProperties);

            // Sign in to get tokens
            $signInResult = $this->firebaseAuth->signInWithEmailAndPassword($email, $password);
            $idToken = $signInResult->idToken();
            $refreshToken = $signInResult->refreshToken();

            Log::info('Firebase user created', [
                'firebase_uid' => $firebaseUser->uid,
                'email' => $email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Firebase user', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('register')
                ->withInput()
                ->with('error', 'Registration failed: ' . $e->getMessage());
        }

        // Step 2: Create MySQL user
        try {
            $mysqlUser = \App\Models\User::create([
                'name' => $name,
                'email' => $email,
                'password' => bcrypt($password),
                'firebase_uid' => $firebaseUser->uid,
                'firebase_id_token' => $idToken,
                'firebase_refresh_token' => $refreshToken,
                'auth_provider' => 'both',
            ]);

            Log::info('MySQL user created', [
                'user_id' => $mysqlUser->id,
                'firebase_uid' => $firebaseUser->uid,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create MySQL user', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            // Rollback Firebase user creation
            try {
                $this->firebaseAuth->deleteUser($firebaseUser->uid);
            } catch (\Exception $rollbackError) {
                Log::error('Failed to rollback Firebase user', [
                    'firebase_uid' => $firebaseUser->uid,
                    'error' => $rollbackError->getMessage(),
                ]);
            }

            return redirect()->route('register')
                ->withInput()
                ->with('error', 'Registration failed: ' . $e->getMessage());
        }

        // Step 3: Log the user in
        Auth::login($mysqlUser);

        session()->put('verified_user_id', $firebaseUser->uid);
        session()->put('idTokenString', $idToken);
        session()->put('displayName', $name);

        // Generate Sanctum API token
        $sanctumToken = $mysqlUser->createToken('auth-token')->plainTextToken;
        session(['api_token' => $sanctumToken]);

        return redirect()->route('home')->with('success', 'Registration successful');
    }

    /**
     * Show unified registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.unified.register');
    }

    /**
     * Handle unified logout
     * Clears both Firebase and Laravel sessions
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        // Clear Laravel session
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear Firebase session data
        session()->forget('verified_user_id');
        session()->forget('idTokenString');
        session()->forget('displayName');
        session()->forget('api_token');
        session()->forget('api_token_firebase_uid');

        // Revoke Sanctum tokens
        if ($user) {
            $user->tokens()->delete();
        }

        return redirect()->route('login')->with('success', 'Logged out successfully');
    }

    /**
     * Get current authentication status
     * Returns both Firebase and Sanctum token status
     */
    public function status(Request $request)
    {
        $firebaseAuth = session()->get('verified_user_id') && session()->get('idTokenString');
        $sanctumAuth = Auth::check();

        return response()->json([
            'authenticated' => $firebaseAuth || $sanctumAuth,
            'firebase' => $firebaseAuth,
            'sanctum' => $sanctumAuth,
            'user' => Auth::user() ? [
                'id' => Auth::user()->id,
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'auth_provider' => Auth::user()->auth_provider,
                'firebase_uid' => Auth::user()->firebase_uid,
            ] : null,
            'api_token' => session('api_token'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Firebase;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FirebaseUserController extends Controller
{
    public function __construct()
    {
        $this->auth = app('firebase.auth');
        $this->database = app('firebase.database');
    }
    
    /**
     * Resolve the target uid. Defaults to the logged-in user; acting on a
     * different uid requires Firebase admin privileges.
     */
    protected function targetUid(Request $request): string
    {
        $own = (string) session()->get('verified_user_id');
        $uid = $request->input('uid');

        if (empty($uid) || $uid === $own) {
            return $own;
        }

        abort_unless(app(\App\Services\FirebaseRoleService::class)->isAdmin(session()->get('verified_user_id')), 403, 'Admin privileges required.');

        return (string) $uid;
    }

    public function index(Request $request)
    {
        $auth = $this->auth;
        try {
            $users = iterator_to_array($auth->listUsers(), false);
        } catch (\Throwable $e) {
            return response()->view('users', [
                'users' => [], 'totalRequests' => 0,
                'viewerUid' => session()->get('verified_user_id'), 'viewerIsOwner' => false,
                'loadError' => 'Could not fetch users. Please try again.',
            ], 503);
        }
        
        // Get the reference to the Requests node
        $requestsRef = $this->database->getReference('Requests');
        
        // Get the snapshot of all data under Requests
        $snapshot = $requestsRef->getSnapshot();
        
        // Initialize counter for total request items
        $totalRequests = 0;
        
        // Loop through each user node under Requests
        foreach ($snapshot->getValue() ?? [] as $userRequests) {
            // Each user node contains multiple request items
            // Add the count of these items to our total
            $totalRequests += count($userRequests);
        }
        
        // Pass the data to the view
        $viewerUid = session()->get('verified_user_id');
        $viewerIsOwner = app(\App\Services\FirebaseRoleService::class)->isOwner($viewerUid);

        return view('users', compact("users", 'totalRequests', 'viewerUid', 'viewerIsOwner'));
    }
    
    public function edit(Request $request)
    {
        $auth = $this->auth;

        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('login')->with('error', 'Login to access this page');
        }
        
        $uid = $this->targetUid($request);
        try {
            // Owner/admin profiles are protected from other actors (read-only gate here; update() re-checks under the lock).
            app(\App\Services\RoleChangeService::class)
                ->assertMayViewProfile(session()->get('verified_user_id'), $uid);
            try {
                $user = $auth->getUser($uid);
            } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
                throw $e;
            } catch (\Throwable $e) {
                // A provider outage is unknown, not evidence of an unverified email.
                // Only the linked self may see the local fallback; no profile save.
                if ($uid !== session()->get('verified_user_id') || $request->user()?->firebase_uid !== $uid) {
                    throw $e;
                }
                return view('user.edit', [
                    'name' => $request->user()->name, 'phone' => null, 'uid' => $uid,
                    'email' => $request->user()->email, 'emailVerified' => null,
                    'emailSelf' => true, 'profileUnavailable' => true,
                ]);
            }

            $name = $user->displayName;
            $phone = $user->phoneNumber;
            $email = $user->email;
            $emailVerified = $user->emailVerified;
            $emailSelf = $uid === session()->get('verified_user_id');
            return view('user.edit', compact('name', 'phone', 'uid', 'email', 'emailVerified', 'emailSelf'));
        } catch (\App\Exceptions\RoleChangeException $e) {
            // The read gate also needs Firebase. Its outage must not grant profile
            // access: expose only the linked self's local, disabled fallback.
            // firebase.auth still rejects requests when token verification fails.
            if ($e->httpStatus === 503 && $uid === session()->get('verified_user_id')
                && $request->user()?->firebase_uid === $uid) {
                return view('user.edit', [
                    'name' => $request->user()->name, 'phone' => null, 'uid' => $uid,
                    'email' => $request->user()->email, 'emailVerified' => null,
                    'emailSelf' => true, 'profileUnavailable' => true,
                ]);
            }
            if ($e->httpStatus === 403) {
                abort(403, $e->getMessage());
            }
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }
    }

    // These web actions use strict JSON self-auth in the service, not the legacy
    // firebase.auth middleware which redirects and permits Firebase-only sessions.
    public function sendEmailVerification(Request $request)
    {
        return app(\App\Services\EmailVerificationService::class)->handle($request, true);
    }

    public function emailVerificationStatus(Request $request)
    {
        return app(\App\Services\EmailVerificationService::class)->handle($request, false);
    }

    public function update(Request $request)
    {
        $auth = $this->auth;

        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('login')->with('error', 'Login to access this page');
        }

        $uid = $this->targetUid($request);

        // Validate phone number format (E.164 format: + followed by digits only)
        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|regex:/^\+[1-9]\d{1,14}$/'
        ], [
            'phone.regex' => 'Phone number must be in E.164 format (e.g., +1234567890). Only digits after the + sign are allowed.'
        ]);

        $properties = [
            'displayName' => $request->name,
            'phoneNumber' => $request->phone
        ];

        try {
            // Locked, actor/target roles re-read under the lock, audited. Never raw provider text to the user.
            app(\App\Services\RoleChangeService::class)
                ->updateProfile(session()->get('verified_user_id'), $uid, fn () => $auth->updateUser($uid, $properties));

            return redirect()->back()->with('success', 'User information updated successfully!');
        } catch (\App\Exceptions\RoleChangeException $e) {
            if ($e->httpStatus === 403) {
                abort(403, $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('login')->with('error', 'Login to access this page');
        }

        // Outside any try/catch: a 403 from targetUid() must stay a 403.
        $uid = $this->targetUid($request);
        $auth = $this->auth;

        try {
            // Owners are never deletable; admin accounts only by an owner or themselves (checked under the lock).
            app(\App\Services\RoleChangeService::class)
                ->deleteUser(session()->get('verified_user_id'), $uid, fn () => $auth->deleteUser($uid));
        } catch (\App\Exceptions\RoleChangeException $e) {
            if ($e->httpStatus === 403) {
                abort(403, $e->getMessage());
            }
            if ($e->httpStatus === 404) {
                return redirect()->back()->with('error', 'User not found!');
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        // If user is deleting their own account, log them out
        if ($uid === session()->get('verified_user_id')) {
            session()->forget('verified_user_id');
            return redirect()->route('login')->with('success', 'Your account has been deleted successfully!');
        }

        return redirect()->back()->with('success', 'User deleted successfully!');
    }
}

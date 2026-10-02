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

        abort_unless((new FirebaseAdminController())->isCurrentUserAdmin(), 403, 'Admin privileges required.');

        return (string) $uid;
    }

    public function index(Request $request)
    {
        $auth = $this->auth;
        $users = $auth->listUsers();
        
        // Get the reference to the Requests node
        $requestsRef = $this->database->getReference('Requests');
        
        // Get the snapshot of all data under Requests
        $snapshot = $requestsRef->getSnapshot();
        
        // Initialize counter for total request items
        $totalRequests = 0;
        
        // Loop through each user node under Requests
        foreach ($snapshot->getValue() as $userRequests) {
            // Each user node contains multiple request items
            // Add the count of these items to our total
            $totalRequests += count($userRequests);
        }
        
        // Pass the data to the view
        return view('users', compact("users", 'totalRequests'));
    }
    
    public function edit(Request $request)
    {
        $auth = $this->auth;

        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('login')->with('error', 'Login to access this page');
        }
        
        $uid = $this->targetUid($request);
        try {
            $user = $auth->getUser($uid);

            $name = $user->displayName;
            $phone = $user->phoneNumber;
            return view('user.edit', compact('name', 'phone', 'uid'));
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return redirect()->route('login')->with('error', $e->getMessage());
        }
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

        try {
            $properties = [
                'displayName' => $request->name,
                'phoneNumber' => $request->phone
            ];

            $updatedUser = $auth->updateUser($uid, $properties);

            return redirect()->back()->with('success', 'User information updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $auth = $this->auth;
        
        try {
            $uid = $this->targetUid($request);
            
            // Check if the user has permission to delete (you may want to add admin check here)
            if (empty(session()->get('verified_user_id'))) {
                return redirect()->route('login')->with('error', 'Login to access this page');
            }
            
            // Delete the user
            $auth->deleteUser($uid);
            
            // If user is deleting their own account, log them out
            if ($uid === session()->get('verified_user_id')) {
                session()->forget('verified_user_id');
                return redirect()->route('login')->with('success', 'Your account has been deleted successfully!');
            }
            
            return redirect()->back()->with('success', 'User deleted successfully!');
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return redirect()->back()->with('error', 'User not found!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}

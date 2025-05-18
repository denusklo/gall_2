<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;

class FirebaseAdminController extends Controller
{
    protected $auth;

    public function __construct()
    {
        $this->auth = app('firebase.auth');
        $this->database = app('firebase.database');
    }

    /**
     * Display a list of users to manage
     */
    public function manageUsers()
    {
        try {
            // List all users (paginated)
            $users = $this->auth->listUsers(1000);
            
            return view('admin.manage-users', compact('users'));
        } catch (FirebaseException $e) {
            return back()->with('error', 'Error fetching users: ' . $e->getMessage());
        }
    }

    /**
     * Set admin custom claim for a user
     */
    public function makeAdmin(Request $request, $uid)
    {
        try {
            // Set custom claim
            $this->auth->setCustomUserClaims($uid, ['admin' => true]);
            
            // Get user for flash message
            $user = $this->auth->getUser($uid);
            
            return back()->with('success', "Admin privileges granted to {$user->displayName} ({$user->email})");
        } catch (FirebaseException $e) {
            return back()->with('error', 'Error updating user: ' . $e->getMessage());
        }
    }

    /**
     * Remove admin custom claim from a user
     */
    public function removeAdmin(Request $request, $uid)
    {
        try {
            // Get existing claims
            $user = $this->auth->getUser($uid);
            $customClaims = $user->customClaims;
            
            // Remove admin claim if it exists
            if (isset($customClaims['admin'])) {
                unset($customClaims['admin']);
                $this->auth->setCustomUserClaims($uid, $customClaims);
            }
            
            return back()->with('success', "Admin privileges removed from {$user->displayName} ({$user->email})");
        } catch (FirebaseException $e) {
            return back()->with('error', 'Error updating user: ' . $e->getMessage());
        }
    }

    /**
     * Check if current user is admin
     */
    public function isCurrentUserAdmin()
    {
        $uid = session()->get('verified_user_id');
        if (!$uid) {
            return false;
        }
        
        try {
            $user = $this->auth->getUser($uid);
            $customClaims = $user->customClaims ?? [];
            
            return isset($customClaims['admin']) && $customClaims['admin'] === true;
        } catch (FirebaseException $e) {
            return false;
        }
    }
}
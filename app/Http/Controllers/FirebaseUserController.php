<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FirebaseUserController extends Controller
{
    public function __construct()
    {
        $this->auth = app('firebase.auth');
        $this->database = app('firebase.database');
    }
    
    public function index(Request $request)
    {
        $auth = $this->auth;

        $users = $auth->listUsers();
        $numchildren = $this->database->getReference('Requests')->getSnapshot()->numchildren();
        
        return view('users', compact("users", 'numchildren'));
    }

    public function edit(Request $request)
    {
        $auth = $this->auth;

        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('firebase.login.form')->with('error', 'Login to access this page');
        }
        
        $uid = $request->uid ?? session()->get('verified_user_id');
        try {
            $user = $auth->getUser($uid);

            $name = $user->displayName;
            $phone = $user->phoneNumber;
            return view('user.edit', compact('name', 'phone'));
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return redirect()->route('firebase.login.form')->with('error', $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        dd($request->all());
    }

    public function delete(Request $request)
    {
        dd($request->all());
    }

}

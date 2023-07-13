<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FirebaseUserController extends Controller
{
    public function __construct()
    {
        $this->auth = app('firebase.auth');
    }
    
    public function index(Request $request)
    {
        return view('users');
    }

    public function edit(Request $request)
    {
        $auth = $this->auth;

        if (empty(session()->get('verified_user_id'))) {
            return redirect()->route('firebase.login.form')->with('error', 'Login to access this page');
        }
        
        $uid = session()->get('verified_user_id');
        try {
            $user = $auth->getUser($uid);
            session()->put('display_name', $user->displayName);
            session()->put('phone', $user->phoneNumber);
            return view('user.edit');
        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            return redirect()->route('firebase.login.form')->with('error', $e->getMessage());
        }
    }

    public function update(Request $request)
    {
        dd($request->all());
    }

}

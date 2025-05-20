<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function showLoginForm(Request $request)
    {
        return view('auth.login');
    }

    public function register(Request $request)
    {
        return view('auth.register');
    }

    public function create(Request $request)
    {
        // dd($request->all());

        $data = $request->all();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect(route('home'));
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('web')->attempt($credentials)) {
            // Generate token for API access
            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Store token in session for JavaScript to access
            session(['api_token' => $token]);
            
            return redirect()->intended('home');
        }

        return redirect()->route('user.login.form')->with('error', 'User not found');
    }

}

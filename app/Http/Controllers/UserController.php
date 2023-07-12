<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
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

        if (Auth::attempt($credentials)) {
            // Authentication passed...
            return redirect()->intended('home');
        }

        return redirect()->route('login');

        // if(\Auth::guard('customer')->attempt(
        //     [
        //         'email' => $request->email,
        //         'password' => $request->password,
        //     ], $request->get('remember')
        // ))
        // {
        //     if(\Auth::guard('customer')->user()->is_active == 0)
        //     {
        //         \Auth::guard('customer')->logout();
        //     }

        //     return redirect()->route('customer.dashboard');
        // }

    }

}

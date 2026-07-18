<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthDispatchController extends Controller
{
    public function dispatchHome(Request $request)
    {
        // Check Laravel auth first - if session expired, go to login
        // (both auth flavors used to land on the removed requests page; images is the app's home)
        if (Auth::check()) {
            return redirect()->route('images.index');
        }

        // Laravel session expired - redirect to login
        return redirect()->route('login');
    }
}
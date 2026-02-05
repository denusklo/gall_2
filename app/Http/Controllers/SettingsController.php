<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Show the storage settings page.
     *
     * @return \Illuminate\View\View
     */
    public function storage()
    {
        return view('settings.storage');
    }
}

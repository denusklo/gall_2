<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function login(Request $request)
    {
        

        return response()->json([
            'data' => $request->all(),
        ]);
    }
    
    public function api(Request $request)
    {
        return response()->json([
            'data' => 'api testing',
        ]);
    }
}

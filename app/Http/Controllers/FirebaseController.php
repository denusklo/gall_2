<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase\Database;



class FirebaseController extends Controller
{
    public $database;

    public function __construct()
    {
        $database = app('firebase.database');

        $this->database = $database;
    }

    public function index()
    {
        $database = $this->database;

        $data = $database->getReference('Requests')->getvalue();

        return $data;
    }
    public function indexByUid()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;

        $data = $database->getReference('Requests/' . $uid)->getvalue();
        return $data;
    }

    public function a()
    {
        $database = $this->database;

        $database->getReference('tasks')->push('Hello!');
    }

    public function deleteUser()
    {
    }

    public function updateUser(Request $request)
    {
    }
}

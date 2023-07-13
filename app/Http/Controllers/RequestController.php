<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RequestController extends Controller
{

    public $database;

    public function __construct()
    {
        $this->database = app('firebase.database');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;

        $data = $database->getReference('Requests/' . $uid)->getvalue();
        return view('user.home', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('requests.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $database = $this->database;

        $this->validate($request, [
            'name' => 'required',
            'age' => 'required',
            'phone_no' => 'required',
            'email' => 'required',
            'location' => 'required',
            'description' => 'required'
        ]);

        $name = $request->name;
        $age = $request->age;
        $phone_no = $request->phone_no;
        $email = $request->email;
        $location = $request->location;
        $description = $request->description;
        // $user_id = rand(1000000, 9999999);

        $data = [
            'name' => $name,
            'age' => $age,
            'phone_no' => $phone_no,
            'email' => $email,
            'location' => $location,
            'description' => $description
        ];

        $database->getReference('Requests/' . session()->get('verified_user_id'))->push($data);

        return redirect()->route('user.home');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $database = $this->database;

        $this->validate($request, [
            'name' => 'required',
            'age' => 'required',
            'phone_no' => 'required',
            'email' => 'required',
            'location' => 'required',
            'description' => 'required'
        ]);

        $name = $request->name;
        $age = $request->age;
        $phone_no = $request->phone_no;
        $email = $request->email;
        $location = $request->location;
        $description = $request->description;
        $ref = "Requests/" . session()->get('verified_user_id') . '/' . $request->ref;

        $data = [
            'name' => $name,
            'age' => $age,
            'phone_no' => $phone_no,
            'email' => $email,
            'location' => $location,
            'description' => $description
        ];

        $updates = [
            $ref => $data
        ];

        $database->getReference()->update($updates);

        return redirect()->route('user.home');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $database = $this->database;
        $id = $request->ref;

        $ref = "Requests/" . session()->get('verified_user_id') . '/' . $id;
        $database->getReference($ref)->remove();

        return redirect()->route('user.home');
    }
}

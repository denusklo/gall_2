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
     * Redirects to the new unified requests interface.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return redirect()->route('requests.my');
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
            'description' => 'required',
            'allow_multiple_completers' => 'boolean',
            'required_completers' => 'nullable|integer|min:1|max:50'
        ]);

        $name = $request->name;
        $age = $request->age;
        $phone_no = $request->phone_no;
        $email = $request->email;
        $location = $request->location;
        $description = $request->description;
        $allowMultiple = $request->boolean('allow_multiple_completers', false);
        $requiredCompleters = $allowMultiple ? ($request->required_completers ?? 1) : 1;

        $data = [
            'name' => $name,
            'age' => $age,
            'phone_no' => $phone_no,
            'email' => $email,
            'location' => $location,
            'description' => $description,
            'created_by' => session()->get('verified_user_id'),
            'allow_multiple_completers' => $allowMultiple,
            'required_completers' => $requiredCompleters,
            'completers' => [],
            'status' => 'pending'
        ];

        $database->getReference('Requests/' . session()->get('verified_user_id'))->push($data);

        return redirect()->route('requests.my')->with('success', 'Request created successfully!');
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

        return redirect()->route('request.index')->with('success', 'Request updated successfully!');
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

        return redirect()->route('request.index')->with('success', 'Request deleted successfully!');
    }
}

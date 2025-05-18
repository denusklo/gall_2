<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RequestsController extends Controller
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
        
        // Check if user is admin using Firebase Auth SDK
        $isAdmin = false;
        
        try {
            $auth = app('firebase.auth');
            $user = $auth->getUser($uid);
            $customClaims = $user->customClaims ?? [];
            $isAdmin = isset($customClaims['admin']) && $customClaims['admin'] === true;
        } catch (\Exception $e) {
            // Handle error or log it
            // If getting user fails, default to non-admin
        }
        
        if ($isAdmin) {
            // Admin view logic - see all requests
            $allUsersData = $database->getReference('Requests/')->getValue();
            
            // Initialize array to hold all requests
            $data = [];
            
            // Iterate through each user's requests
            if ($allUsersData) {
                foreach ($allUsersData as $userId => $userRequests) {
                    if ($userRequests) {
                        foreach ($userRequests as $requestId => $requestData) {
                            // Add user ID and request ID to the data for reference
                            $requestData['user_id'] = $userId;
                            $requestData['request_id'] = $requestId;
                            $data[] = $requestData;
                        }
                    }
                }
            }
            
            return view('requests.index', compact('data'));
        } else {
            // Regular user view logic - see only their requests
            $data = $database->getReference('Requests/' . $uid)->getValue();
            
            // If $data is null, initialize as empty array
            if ($data === null) {
                $data = [];
            } else {
                // Reformat to match the format of the admin view
                $formattedData = [];
                foreach ($data as $requestId => $requestData) {
                    $requestData['user_id'] = $uid;
                    $requestData['request_id'] = $requestId;
                    $formattedData[] = $requestData;
                }
                $data = $formattedData;
            }
            
            return view('requests.index', compact('data'));
        }
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

        $data = [
            'name' => $name,
            'age' => $age,
            'phone_no' => $phone_no,
            'email' => $email,
            'location' => $location,
            'description' => $description
        ];

        $database->getReference('Requests/' . session()->get('verified_user_id'))->push($data);

        return redirect()->route('requests.index');
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
        $ref = "Requests/" . $request->user_id . '/' . $request->ref;

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

        try {
            $database->getReference()->update($updates);
            
            // Redirect with success message
            return redirect()->route('requests.index')
                ->with('success', 'Request updated successfully!');
        } catch (\Exception $e) {
            // Redirect with error message if something goes wrong
            return redirect()->route('requests.index')
                ->with('error', 'Failed to update request: ' . $e->getMessage());
        }
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
        $userId = $request->user_id;

        $ref = "Requests/" . $userId . '/' . $id;
        
        try {
            // Get request data before deletion (optional, for better messaging)
            $requestData = $database->getReference($ref)->getValue();
            $requestName = $requestData['name'] ?? 'Request';
            
            // Delete the record
            $database->getReference($ref)->remove();
            
            // Redirect with success message
            return redirect()->route('requests.index')
                ->with('success', "'{$requestName}' has been successfully deleted!");
        } catch (\Exception $e) {
            // Redirect with error message if something goes wrong
            return redirect()->route('requests.index')
                ->with('error', 'Failed to delete request: ' . $e->getMessage());
        }
    }
}

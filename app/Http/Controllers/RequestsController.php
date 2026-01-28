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
            
            // Paginate the data
            $perPage = 10;
            $currentPage = request()->get('page', 1);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($data, ($currentPage - 1) * $perPage, $perPage),
                count($data),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return view('requests.index', ['data' => $paginator]);
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

            // Paginate the data
            $perPage = 10;
            $currentPage = request()->get('page', 1);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($data, ($currentPage - 1) * $perPage, $perPage),
                count($data),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return view('requests.index', ['data' => $paginator]);
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

        return redirect()->route('requests.index')->with('success', 'Request created successfully!');
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

    /**
     * Complete a request
     *
     * @param  string  $userId
     * @param  string  $requestId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function complete($userId, $requestId, Request $request)
    {
        $database = $this->database;
        $currentUserId = session()->get('verified_user_id');

        $this->validate($request, [
            'completion_notes' => 'nullable|string|max:500'
        ]);

        $ref = "Requests/" . $userId . '/' . $requestId;

        try {
            // Get current request data
            $requestData = $database->getReference($ref)->getValue();

            if (!$requestData) {
                return redirect()->route('requests.index')
                    ->with('error', 'Request not found.');
            }

            // Check if already completed
            if (isset($requestData['status']) && $requestData['status'] === 'completed') {
                return redirect()->route('requests.index')
                    ->with('warning', 'Request is already completed.');
            }

            // Get current user's info for completion tracking
            $auth = app('firebase.auth');
            $currentUser = $auth->getUser($currentUserId);

            // Prepare completion data
            $completionData = [
                'status' => 'completed',
                'completion_data' => [
                    'completed_by_uid' => $currentUserId,
                    'completed_by_name' => $currentUser->displayName ?? 'Unknown',
                    'completed_at' => now()->toISOString(),
                    'completion_notes' => $request->completion_notes
                ]
            ];

            // Update the request with completion data
            $database->getReference($ref)->update($completionData);

            $requestName = $requestData['name'] ?? 'Request';

            return redirect()->route('requests.index')
                ->with('success', "Request '{$requestName}' has been marked as completed!");

        } catch (\Exception $e) {
            return redirect()->route('requests.index')
                ->with('error', 'Failed to complete request: ' . $e->getMessage());
        }
    }

    /**
     * Display completed requests
     *
     * @return \Illuminate\Http\Response
     */
    public function showCompleted()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;

        // Check if user is admin or volunteer
        $isAdmin = false;
        $isVolunteer = false;

        try {
            $auth = app('firebase.auth');
            $user = $auth->getUser($uid);
            $customClaims = $user->customClaims ?? [];
            $isAdmin = isset($customClaims['admin']) && $customClaims['admin'] === true;
            $isVolunteer = isset($customClaims['volunteer']) && $customClaims['volunteer'] === true;
        } catch (\Exception $e) {
            // Handle error or log it
        }

        if (!$isAdmin && !$isVolunteer) {
            return redirect()->route('user.home')
                ->with('error', 'You do not have permission to view completed requests.');
        }

        // Get all completed requests
        $allUsersData = $database->getReference('Requests/')->getValue();
        $completedRequests = [];

        if ($allUsersData) {
            foreach ($allUsersData as $userId => $userRequests) {
                if ($userRequests) {
                    foreach ($userRequests as $requestId => $requestData) {
                        if (isset($requestData['status']) && $requestData['status'] === 'completed') {
                            $requestData['user_id'] = $userId;
                            $requestData['request_id'] = $requestId;
                            $completedRequests[] = $requestData;
                        }
                    }
                }
            }
        }

        // Sort by completion date (newest first)
        usort($completedRequests, function($a, $b) {
            $dateA = $a['completion_data']['completed_at'] ?? '';
            $dateB = $b['completion_data']['completed_at'] ?? '';
            return strcmp($dateB, $dateA);
        });

        // Paginate the data
        $perPage = 10;
        $currentPage = request()->get('page', 1);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($completedRequests, ($currentPage - 1) * $perPage, $perPage),
            count($completedRequests),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('requests.completed', ['data' => $paginator]);
    }

    /**
     * Get only pending requests
     *
     * @return \Illuminate\Http\Response
     */
    public function pending()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;

        // Check if user is admin
        $isAdmin = false;

        try {
            $auth = app('firebase.auth');
            $user = $auth->getUser($uid);
            $customClaims = $user->customClaims ?? [];
            $isAdmin = isset($customClaims['admin']) && $customClaims['admin'] === true;
        } catch (\Exception $e) {
            // Handle error or log it
        }

        if ($isAdmin) {
            // Admin view logic - see all pending requests
            $allUsersData = $database->getReference('Requests/')->getValue();

            // Initialize array to hold all pending requests
            $pendingRequests = [];

            // Iterate through each user's requests
            if ($allUsersData) {
                foreach ($allUsersData as $userId => $userRequests) {
                    if ($userRequests) {
                        foreach ($userRequests as $requestId => $requestData) {
                            // Only include pending requests
                            if (!isset($requestData['status']) || $requestData['status'] !== 'completed') {
                                $requestData['user_id'] = $userId;
                                $requestData['request_id'] = $requestId;
                                $pendingRequests[] = $requestData;
                            }
                        }
                    }
                }
            }

            // Paginate the data
            $perPage = 10;
            $currentPage = request()->get('page', 1);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($pendingRequests, ($currentPage - 1) * $perPage, $perPage),
                count($pendingRequests),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return view('requests.pending', ['data' => $paginator]);
        } else {
            // Regular user view logic - see only their pending requests
            $data = $database->getReference('Requests/' . $uid)->getValue();

            // If $data is null, initialize as empty array
            if ($data === null) {
                $data = [];
            } else {
                // Filter only pending requests
                $formattedData = [];
                foreach ($data as $requestId => $requestData) {
                    if (!isset($requestData['status']) || $requestData['status'] !== 'completed') {
                        $requestData['user_id'] = $uid;
                        $requestData['request_id'] = $requestId;
                        $formattedData[] = $requestData;
                    }
                }
                $data = $formattedData;
            }

            // Paginate the data
            $perPage = 10;
            $currentPage = request()->get('page', 1);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($data, ($currentPage - 1) * $perPage, $perPage),
                count($data),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            return view('requests.pending', ['data' => $paginator]);
        }
    }
}

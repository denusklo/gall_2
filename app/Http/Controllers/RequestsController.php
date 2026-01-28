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
     * Redirects to my requests by default.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return redirect()->route('requests.my');
    }

    /**
     * Display user's own requests.
     *
     * @return \Illuminate\Http\Response
     */
    public function myRequests()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;
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
                // Ensure created_by is set
                if (!isset($requestData['created_by'])) {
                    $requestData['created_by'] = $uid;
                }
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

        return view('requests.index', [
            'data' => $paginator,
            'view' => 'my'
        ]);
    }

    /**
     * Display all pending requests (not created by current user).
     *
     * @return \Illuminate\Http\Response
     */
    public function allRequests()
    {
        $uid = session()->get('verified_user_id');
        $database = $this->database;

        // Get all requests
        $allUsersData = $database->getReference('Requests/')->getValue();

        // Initialize array to hold all pending requests
        $pendingRequests = [];

        // Iterate through each user's requests
        if ($allUsersData) {
            foreach ($allUsersData as $userId => $userRequests) {
                if ($userRequests) {
                    foreach ($userRequests as $requestId => $requestData) {
                        // Only include pending requests not created by current user
                        $isCompleted = isset($requestData['status']) && $requestData['status'] === 'completed';
                        $isMyRequest = isset($requestData['created_by']) && $requestData['created_by'] === $uid;
                        $isLegacyRequest = !isset($requestData['created_by']) && $userId === $uid;

                        if (!$isCompleted && !$isMyRequest && !$isLegacyRequest) {
                            $requestData['user_id'] = $userId;
                            $requestData['request_id'] = $requestId;
                            // Ensure created_by is set
                            if (!isset($requestData['created_by'])) {
                                $requestData['created_by'] = $userId;
                            }
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

        return view('requests.index', [
            'data' => $paginator,
            'view' => 'all'
        ]);
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
                return redirect()->route('requests.all')
                    ->with('error', 'Request not found.');
            }

            // Check if this is own request
            $createdBy = $requestData['created_by'] ?? $userId;
            if ($createdBy === $currentUserId) {
                return redirect()->route('requests.all')
                    ->with('warning', 'You cannot complete your own request.');
            }

            // Get current user's info
            $auth = app('firebase.auth');
            $currentUser = $auth->getUser($currentUserId);

            // Initialize completers array if not exists
            $completers = $requestData['completers'] ?? [];
            $allowMultiple = $requestData['allow_multiple_completers'] ?? false;
            $requiredCompleters = $requestData['required_completers'] ?? 1;

            // Check if already completed by this user
            if (in_array($currentUserId, $completers)) {
                return redirect()->route('requests.all')
                    ->with('warning', 'You have already completed this request.');
            }

            // Add current user to completers
            $completers[] = $currentUserId;

            // Prepare completion entry
            $completionEntry = [
                'uid' => $currentUserId,
                'name' => $currentUser->displayName ?? 'Unknown',
                'completed_at' => now()->toISOString(),
                'notes' => $request->completion_notes
            ];

            // Check if request is now fully completed
            $isFullyCompleted = count($completers) >= $requiredCompleters;

            // Prepare update data
            $updateData = [
                'completers' => $completers
            ];

            // Update completion_data based on multi-completer setting
            if ($allowMultiple) {
                // For multi-completer, store array of completions
                $existingCompletions = $requestData['completion_data'] ?? [];
                if (!is_array($existingCompletions)) {
                    $existingCompletions = [];
                }
                $existingCompletions[] = $completionEntry;
                $updateData['completion_data'] = $existingCompletions;
            } else {
                // For single completer, store single completion data
                $updateData['completion_data'] = $completionEntry;
            }

            // Only mark as completed if required number is reached
            if ($isFullyCompleted) {
                $updateData['status'] = 'completed';
            }

            // Update the request
            $database->getReference($ref)->update($updateData);

            $requestName = $requestData['name'] ?? 'Request';
            $completerName = $currentUser->displayName ?? 'Someone';

            // Send FCM Notifications
            $fcmNotification = app(\App\Services\FcmNotificationService::class);
            $fcmTokenService = app(\App\Services\FcmTokenService::class);

            // Get creator's FCM token
            $creatorToken = $fcmTokenService->getPrimaryToken($createdBy);

            // Get completer's FCM token
            $completerToken = $fcmTokenService->getPrimaryToken($currentUserId);

            if ($isFullyCompleted) {
                // Request fully completed
                if ($allowMultiple) {
                    // Multi-completer fully completed
                    if ($creatorToken) {
                        $fcmNotification->notifyFullyCompleted($creatorToken, $requestName, count($completers));
                    }
                } else {
                    // Single completer completed
                    if ($creatorToken) {
                        $fcmNotification->notifyRequestCompleted($creatorToken, $requestName, $completerName);
                    }
                }

                // Notify completer of full completion
                if ($completerToken) {
                    $fcmNotification->notifyCompletionConfirmation($completerToken, $requestName, count($completers), $requiredCompleters);
                }

                return redirect()->route('requests.all')
                    ->with('success', "Request '{$requestName}' has been fully completed! ({$requiredCompleters}/{$requiredCompleters})");
            } else {
                // Partial completion (multi-completer)
                $remaining = $requiredCompleters - count($completers);
                $completedCount = count($completers);

                // Notify creator of new completion
                if ($creatorToken && $allowMultiple) {
                    $fcmNotification->notifyNewCompletion($creatorToken, $requestName, $completedCount, $requiredCompleters, $completerName);
                }

                // Notify completer
                if ($completerToken) {
                    $fcmNotification->notifyCompletionConfirmation($completerToken, $requestName, $completedCount, $requiredCompleters);
                }

                return redirect()->route('requests.all')
                    ->with('success', "You've completed '{$requestName}'! ({$completedCount}/{$requiredCompleters} completed, {$remaining} more needed)");
            }

        } catch (\Exception $e) {
            return redirect()->route('requests.all')
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
                            // Ensure created_by is set
                            if (!isset($requestData['created_by'])) {
                                $requestData['created_by'] = $userId;
                            }
                            $completedRequests[] = $requestData;
                        }
                    }
                }
            }
        }

        // Sort by completion date (newest first)
        usort($completedRequests, function($a, $b) {
            // Handle both array (multi-completer) and object (single completer) formats
            $dateA = '';
            $dateB = '';

            if (isset($a['completion_data'])) {
                if (is_array($a['completion_data']) && isset($a['completion_data'][0])) {
                    $dateA = $a['completion_data'][0]['completed_at'] ?? '';
                } elseif (is_array($a['completion_data']) && isset($a['completion_data']['completed_at'])) {
                    $dateA = $a['completion_data']['completed_at'] ?? '';
                }
            }

            if (isset($b['completion_data'])) {
                if (is_array($b['completion_data']) && isset($b['completion_data'][0])) {
                    $dateB = $b['completion_data'][0]['completed_at'] ?? '';
                } elseif (is_array($b['completion_data']) && isset($b['completion_data']['completed_at'])) {
                    $dateB = $b['completion_data']['completed_at'] ?? '';
                }
            }

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

        return view('requests.completed', [
            'data' => $paginator,
            'view' => 'completed'
        ]);
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

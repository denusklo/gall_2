@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0">
                            @if($view ?? '' === 'all')
                                All Requests
                            @elseif($view ?? '' === 'completed')
                                Completed Requests
                            @else
                                My Requests
                            @endif
                        </h5>
                        <div class="mt-2 mt-md-0">
                            <a href="{{route('requests.my')}}" class="btn btn-sm @if(($view ?? '') === 'my') btn-primary @else btn-outline-primary @endif mr-1">My Requests</a>
                            <a href="{{route('requests.all')}}" class="btn btn-sm @if(($view ?? '') === 'all') btn-primary @else btn-outline-primary @endif mr-1">All Requests</a>
                            <a href="{{route('requests.completed')}}" class="btn btn-sm @if(($view ?? '') === 'completed') btn-primary @else btn-outline-primary @endif mr-1">Completed</a>
                            <a href="{{route('request.create')}}" class="btn btn-success btn-sm">Create New Request</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if ($data->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Status</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Location</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $index => $request)
                                        @php
                                            $status = $request['status'] ?? 'pending';
                                            $statusClass = $status === 'completed' ? 'success' : 'warning';
                                            $statusIcon = $status === 'completed' ? 'check' : 'clock';
                                            $allowMultiple = $request['allow_multiple_completers'] ?? false;
                                            $requiredCompleters = $request['required_completers'] ?? 1;
                                            $completers = $request['completers'] ?? [];
                                            $completersCount = is_array($completers) ? count($completers) : 0;
                                            $createdBy = $request['created_by'] ?? $request['user_id'] ?? null;
                                            $currentUserId = session()->get('verified_user_id');
                                            $isMyRequest = $createdBy === $currentUserId;
                                            $hasCompleted = in_array($currentUserId, $completers ?? []);
                                        @endphp
                                        <tr>
                                            <td>{{ $data->firstItem() + $index }}</td>
                                            <td>
                                                @if($allowMultiple)
                                                    <span class="badge badge-info">
                                                        <i class="fa fa-users"></i>
                                                        {{ $completersCount }}/{{ $requiredCompleters }}
                                                    </span>
                                                @else
                                                    <span class="badge badge-{{ $statusClass }}">
                                                        <i class="fa fa-{{ $statusIcon }}"></i>
                                                        {{ ucfirst($status) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td>{{ $request['name'] ?? 'N/A' }}</td>
                                            <td>{{ $request['age'] ?? 'N/A' }}</td>
                                            <td>{{ $request['email'] ?? 'N/A' }}</td>
                                            <td>{{ $request['phone_no'] ?? 'N/A' }}</td>
                                            <td>{{ Str::limit($request['location'] ?? 'N/A', 30) }}</td>
                                            <td>{{ Str::limit($request['description'] ?? 'N/A', 50) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-view"
                                                    data-name="{{ $request['name'] ?? '' }}"
                                                    data-age="{{ $request['age'] ?? '' }}"
                                                    data-email="{{ $request['email'] ?? '' }}"
                                                    data-phone="{{ $request['phone_no'] ?? '' }}"
                                                    data-location="{{ $request['location'] ?? '' }}"
                                                    data-description="{{ $request['description'] ?? '' }}"
                                                    data-status="{{ $request['status'] ?? 'pending' }}"
                                                    data-allow-multiple="{{ $allowMultiple ? 'true' : 'false' }}"
                                                    data-required-completers="{{ $requiredCompleters }}"
                                                    data-completers-count="{{ $completersCount }}"
                                                    @if(isset($request['completion_data']))
                                                        data-completion-data="{{ e(json_encode($request['completion_data'])) }}"
                                                    @endif>
                                                    <i class="fa fa-eye"></i>
                                                </button>

                                                @if($isMyRequest)
                                                    <button type="button" class="btn btn-sm btn-warning btn-edit"
                                                        data-id="{{ $request['request_id'] }}"
                                                        data-user-id="{{ $request['user_id'] }}"
                                                        data-name="{{ $request['name'] ?? '' }}"
                                                        data-age="{{ $request['age'] ?? '' }}"
                                                        data-email="{{ $request['email'] ?? '' }}"
                                                        data-phone="{{ $request['phone_no'] ?? '' }}"
                                                        data-location="{{ $request['location'] ?? '' }}"
                                                        data-description="{{ $request['description'] ?? '' }}">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                                                        data-id="{{ $request['request_id'] }}"
                                                        data-user-id="{{ $request['user_id'] }}"
                                                        data-name="{{ $request['name'] ?? '' }}">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                @endif

                                                @if(($view ?? '' !== 'completed') && !$isMyRequest && ($status === 'pending'))
                                                    @if(!$allowMultiple || !$hasCompleted)
                                                        <button type="button" class="btn btn-sm btn-success btn-complete"
                                                            data-id="{{ $request['request_id'] }}"
                                                            data-user-id="{{ $request['user_id'] }}"
                                                            data-name="{{ $request['name'] ?? '' }}"
                                                            data-allow-multiple="{{ $allowMultiple ? 'true' : 'false' }}"
                                                            data-completers-count="{{ $completersCount }}"
                                                            data-required-completers="{{ $requiredCompleters }}">
                                                            <i class="fa fa-check"></i> Complete
                                                        </button>
                                                    @else
                                                        <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                            <i class="fa fa-check"></i> Completed
                                                        </button>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $data->links('pagination::bootstrap-4') }}
                        </div>
                    @else
                        <div class="alert alert-info">
                            @if(($view ?? '') === 'all')
                                No pending requests available. <a href="{{ route('request.create') }}">Create a new request</a>
                            @elseif(($view ?? '') === 'completed')
                                No completed requests yet.
                            @else
                                You haven't created any requests yet. <a href="{{ route('request.create') }}">Create a new request</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Status</th>
                        <td id="view-status"></td>
                    </tr>
                    <tr id="multi-completer-info" style="display: none;">
                        <th>Completion Progress</th>
                        <td id="view-progress"></td>
                    </tr>
                    <tr>
                        <th>Name</th>
                        <td id="view-name"></td>
                    </tr>
                    <tr>
                        <th>Age</th>
                        <td id="view-age"></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td id="view-email"></td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td id="view-phone"></td>
                    </tr>
                    <tr>
                        <th>Location</th>
                        <td id="view-location"></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td id="view-description"></td>
                    </tr>
                    <tr id="completion-info" style="display: none;">
                        <th>Completion Info</th>
                        <td id="view-completion-list"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Single Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Request</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    @method('PUT')
                    @csrf
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <input type="hidden" name="ref" id="edit-ref">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" id="edit-name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Age</label>
                                <input type="number" name="age" id="edit-age" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone No</label>
                                <input type="text" name="phone_no" id="edit-phone" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" id="edit-email" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="edit-location" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit-description" rows="3" class="form-control" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Single Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Request</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the request from <strong id="delete-name"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline-block">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="user_id" id="delete-user-id">
                    <input type="hidden" name="ref" id="delete-ref">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Complete Request Modal -->
<div class="modal fade" id="completeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Complete Request</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="completeForm" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="complete-user-id">
                <input type="hidden" name="request_id" id="complete-request-id">
                <div class="modal-body">
                    <p id="complete-message"></p>
                    <div class="form-group">
                        <label for="completion_notes">Completion Notes (optional)</label>
                        <textarea name="completion_notes" id="completion_notes" rows="3" class="form-control" placeholder="Add any notes about how this request was completed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // View Modal
        $('.btn-view').on('click', function() {
            const status = $(this).data('status');
            const allowMultiple = $(this).data('allow-multiple') === 'true';
            const requiredCompleters = $(this).data('required-completers') || 1;
            const completersCount = $(this).data('completers-count') || 0;
            const completionData = $(this).data('completion-data');

            $('#view-name').text($(this).data('name'));
            $('#view-age').text($(this).data('age'));
            $('#view-email').text($(this).data('email'));
            $('#view-phone').text($(this).data('phone'));
            $('#view-location').text($(this).data('location'));
            $('#view-description').text($(this).data('description'));

            const statusElement = $('#view-status');

            if (allowMultiple) {
                $('#multi-completer-info').show();
                $('#view-progress').html(
                    '<span class="badge badge-info"><i class="fa fa-users"></i> ' +
                    completersCount + '/' + requiredCompleters + ' completed</span>'
                );
                if (completersCount >= requiredCompleters) {
                    statusElement.html('<span class="badge badge-success"><i class="fa fa-check"></i> Completed</span>');
                } else {
                    statusElement.html('<span class="badge badge-warning"><i class="fa fa-clock"></i> In Progress</span>');
                }
            } else {
                $('#multi-completer-info').hide();
                if (status === 'completed') {
                    statusElement.html('<span class="badge badge-success"><i class="fa fa-check"></i> Completed</span>');
                } else {
                    statusElement.html('<span class="badge badge-warning"><i class="fa fa-clock"></i> Pending</span>');
                }
            }

            // Show completion info if completed
            if (status === 'completed' || (allowMultiple && completersCount > 0)) {
                $('#completion-info').show();
                let completionHtml = '';

                if (completionData) {
                    try {
                        const data = JSON.parse(completionData);
                        if (Array.isArray(data)) {
                            data.forEach(function(completion) {
                                const date = new Date(completion.completed_at);
                                completionHtml += '<strong>' + completion.name + '</strong> - ' + date.toLocaleString();
                                if (completion.notes) {
                                    completionHtml += '<br><em>"' + completion.notes + '"</em>';
                                }
                                completionHtml += '<br><br>';
                            });
                        } else if (data.completed_at) {
                            const date = new Date(data.completed_at);
                            completionHtml = '<strong>Completed by:</strong> ' + (data.name || 'Unknown') + '<br>' +
                                           '<strong>Completed at:</strong> ' + date.toLocaleString();
                            if (data.notes) {
                                completionHtml += '<br><strong>Notes:</strong> ' + data.notes;
                            }
                        }
                    } catch(e) {
                        completionHtml = 'Error loading completion data';
                    }
                }

                $('#view-completion-list').html(completionHtml);
            } else {
                $('#completion-info').hide();
            }

            $('#viewModal').modal('show');
        });

        // Edit Modal
        $('.btn-edit').on('click', function() {
            const requestId = $(this).data('id');
            const userId = $(this).data('user-id');

            $('#edit-name').val($(this).data('name'));
            $('#edit-age').val($(this).data('age'));
            $('#edit-email').val($(this).data('email'));
            $('#edit-phone').val($(this).data('phone'));
            $('#edit-location').val($(this).data('location'));
            $('#edit-description').val($(this).data('description'));
            $('#edit-user-id').val(userId);
            $('#edit-ref').val(requestId);

            $('#editForm').attr('action', "{{ route('requests.update', ':id') }}".replace(':id', requestId));
            $('#editModal').modal('show');
        });

        // Delete Modal
        $('.btn-delete').on('click', function() {
            const requestId = $(this).data('id');
            const userId = $(this).data('user-id');
            const name = $(this).data('name');

            $('#delete-name').text(name);
            $('#delete-user-id').val(userId);
            $('#delete-ref').val(requestId);

            $('#deleteForm').attr('action', "{{ route('requests.destroy', ':id') }}".replace(':id', requestId));
            $('#deleteModal').modal('show');
        });

        // Complete Modal
        $('.btn-complete').on('click', function() {
            const requestId = $(this).data('id');
            const userId = $(this).data('user-id');
            const name = $(this).data('name');
            const allowMultiple = $(this).data('allow-multiple') === 'true';
            const completersCount = $(this).data('completers-count') || 0;
            const requiredCompleters = $(this).data('required-completers') || 1;

            $('#complete-name').text(name);
            $('#complete-user-id').val(userId);
            $('#complete-request-id').val(requestId);

            // Set form action
            $('#completeForm').attr('action', `/requests/${userId}/${requestId}/complete`);

            // Clear any previous completion notes
            $('#completion_notes').val('');

            // Set appropriate message
            if (allowMultiple) {
                const remaining = requiredCompleters - completersCount - 1;
                $('#complete-message').html(
                    'Complete the request from <strong>' + name + '</strong>?<br>' +
                    '<small class="text-muted">This will be your completion. After this, ' + remaining + ' more ' + (remaining === 1 ? 'person is' : 'people are') + ' needed.</small>'
                );
            } else {
                $('#complete-message').text('Are you sure you want to mark the request from "' + name + '" as completed?');
            }

            $('#completeModal').modal('show');
        });
    });

    // Show toast notifications based on flash messages
    @if(session('success'))
        iziToast.success({
            title: 'Success',
            message: '{{ session('success') }}',
            position: 'topRight',
            timeout: 3000
        });
    @endif

    @if(session('error'))
        iziToast.error({
            title: 'Error',
            message: '{{ session('error') }}',
            position: 'topRight',
            timeout: 3000
        });
    @endif

    @if(session('warning'))
        iziToast.warning({
            title: 'Warning',
            message: '{{ session('warning') }}',
            position: 'topRight',
            timeout: 3000
        });
    @endif
</script>
@endsection

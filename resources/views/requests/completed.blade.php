@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Completed Requests</span>
                    <div>
                        <a href="{{route('requests.index')}}" class="btn btn-primary btn-sm mr-2">All Requests</a>
                        <a href="{{route('requests.pending')}}" class="btn btn-info btn-sm mr-2">Pending Only</a>
                        <a href="{{route('request.create')}}" class="btn btn-success btn-sm">Create New Request</a>
                    </div>
                </div>
                <div class="card-body">
                    @if ($data->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Request Name</th>
                                        <th>Age</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Location</th>
                                        <th>Description</th>
                                        <th>Completed By</th>
                                        <th>Completed At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $index => $request)
                                        <tr>
                                            <td>{{ $data->firstItem() + $index }}</td>
                                            <td>{{ $request['name'] ?? 'N/A' }}</td>
                                            <td>{{ $request['age'] ?? 'N/A' }}</td>
                                            <td>{{ $request['email'] ?? 'N/A' }}</td>
                                            <td>{{ $request['phone_no'] ?? 'N/A' }}</td>
                                            <td>{{ Str::limit($request['location'] ?? 'N/A', 30) }}</td>
                                            <td>{{ Str::limit($request['description'] ?? 'N/A', 50) }}</td>
                                            <td>{{ $request['completion_data']['completed_by_name'] ?? 'Unknown' }}</td>
                                            <td>
                                                @if(isset($request['completion_data']['completed_at']))
                                                    {{ \Carbon\Carbon::parse($request['completion_data']['completed_at'])->format('M j, Y H:i') }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary btn-view-completed"
                                                    data-name="{{ $request['name'] ?? '' }}"
                                                    data-age="{{ $request['age'] ?? '' }}"
                                                    data-email="{{ $request['email'] ?? '' }}"
                                                    data-phone="{{ $request['phone_no'] ?? '' }}"
                                                    data-location="{{ $request['location'] ?? '' }}"
                                                    data-description="{{ $request['description'] ?? '' }}"
                                                    data-completed-by="{{ $request['completion_data']['completed_by_name'] ?? '' }}"
                                                    data-completed-at="{{ $request['completion_data']['completed_at'] ?? '' }}"
                                                    data-completion-notes="{{ $request['completion_data']['completion_notes'] ?? '' }}">
                                                    <i class="fa fa-eye"></i>
                                                </button>
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
                            No completed requests found.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Completed Request Modal -->
<div class="modal fade" id="viewCompletedModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Completed Request Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Requester Name</th>
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
                    <tr>
                        <th>Completed By</th>
                        <td id="view-completed-by"></td>
                    </tr>
                    <tr>
                        <th>Completed At</th>
                        <td id="view-completed-at"></td>
                    </tr>
                    @if(!empty($request['completion_data']['completion_notes']))
                    <tr>
                        <th>Completion Notes</th>
                        <td id="view-completion-notes"></td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // View Completed Request Modal
        $('.btn-view-completed').on('click', function() {
            $('#view-name').text($(this).data('name'));
            $('#view-age').text($(this).data('age'));
            $('#view-email').text($(this).data('email'));
            $('#view-phone').text($(this).data('phone'));
            $('#view-location').text($(this).data('location'));
            $('#view-description').text($(this).data('description'));
            $('#view-completed-by').text($(this).data('completed-by'));

            // Format the completion date
            const completedAt = $(this).data('completed-at');
            if (completedAt) {
                const date = new Date(completedAt);
                $('#view-completed-at').text(date.toLocaleString());
            }

            $('#view-completion-notes').text($(this).data('completion-notes') || 'No notes provided');

            $('#viewCompletedModal').modal('show');
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
    });
</script>
@endsection
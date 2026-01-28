@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0">Completed Requests</h5>
                        <div class="mt-2 mt-md-0">
                            <a href="{{route('requests.my')}}" class="btn btn-sm btn-outline-primary mr-1">My Requests</a>
                            <a href="{{route('requests.all')}}" class="btn btn-sm btn-outline-primary mr-1">All Requests</a>
                            <a href="{{route('requests.completed')}}" class="btn btn-sm btn-primary mr-1">Completed</a>
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
                                            $allowMultiple = $request['allow_multiple_completers'] ?? false;
                                            $requiredCompleters = $request['required_completers'] ?? 1;
                                            $completers = $request['completers'] ?? [];
                                            $completersCount = is_array($completers) ? count($completers) : 0;
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
                                                    <span class="badge badge-success">
                                                        <i class="fa fa-check"></i>
                                                        Completed
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
                                                    data-status="completed"
                                                    data-allow-multiple="{{ $allowMultiple ? 'true' : 'false' }}"
                                                    data-required-completers="{{ $requiredCompleters }}"
                                                    data-completers-count="{{ $completersCount }}"
                                                    @if(isset($request['completion_data']))
                                                        data-completion-data="{{ e(json_encode($request['completion_data'])) }}"
                                                    @endif>
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
                            No completed requests yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Request Modal -->
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
                    <tr id="completion-info">
                        <th>Completion Info</th>
                        <td id="view-completion-list"></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // View Modal
        $('.btn-view').on('click', function() {
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
                statusElement.html('<span class="badge badge-success"><i class="fa fa-check"></i> Completed</span>');
            } else {
                $('#multi-completer-info').hide();
                statusElement.html('<span class="badge badge-success"><i class="fa fa-check"></i> Completed</span>');
            }

            // Show completion info
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

            $('#viewModal').modal('show');
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

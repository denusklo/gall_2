@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">Registered User List</div>
                {{-- <div class="card-body">
                            <?php
                            use App\Http\Controllers\FirebaseController;
                                $controller = new FirebaseController();
                                $data = $controller->index();
                                if (is_null($data)) {
                                    echo "Please enter request";
                                } else {
                                $i = 0;
                                $arrayKeys = array_keys($data);
                                // echo $arrayKeys[0];
                                foreach ($data as $key) {
                                ?>
                    <table class="table table-bordered" style="table-layout: fixed">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Name</th>
                                <td><?php echo $key['name'];?></td>
                            </tr>
                            <tr>
                                <th>Age</th>
                                <td><?php echo $key['age'];?></td>
                            </tr>
                            <tr>
                                <th>Phone No</th>
                                <td><?php echo $key['phone_no'];?></td>
                            </tr>
                            <tr>
                                <th>Location</th>
                                <td><?php echo $key['location'];?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo $key['email'];?></td>
                            </tr>
                            <tr >
                                <th>Description</th>
                                <td><?php echo $key['description'];?></td>
                            </tr>
                            <tr >
                                <th>Action</th>
                                <td>
                                    <form action="{{route('user.delete')}}" method="POST" style="display: inline-block">
                                        {{ csrf_field() }}
                                        <input type="hidden" name="ref" value="<?php echo $arrayKeys[$i]; ?>">
                                        <button type="submit" name="delete_data" class="btn btn-danger" >Delete</button>
                                    </form>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#updateData<?php echo $i; ?>">Update Data</button>
                                    <div class="modal fade" id="updateData<?php echo $i; ?>" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="updateData<?php echo $i; ?>">Update data</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="{{route('updateRequest')}}" method="POST" enctype="multipart/form-data">
                                                        {{ csrf_field() }}
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label for="title"> Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?php echo $key['name'];?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cover">Age</label>
                                                                <input type="number" name="age" class="form-control" value="<?php echo $key['age'];?>">
                                                            </div>
                                                        </div>
                                                        <br>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label for="title">Phone No</label>
                                                                <input type="text" name="phone_no" class="form-control" value="<?php echo $key['phone_no'];?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cover">Email Address</label>
                                                                <input type="email" name="email" class="form-control" value="<?php echo $key['email'];?>">
                                                            </div>
                                                        </div>
                                                        <br>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <label for="title">Full address</label>
                                                                <input type="text" name="location" class="form-control" value="<?php echo $key['location'];?>">
                                                            </div>
                                                        </div>
                                                        <br>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <label for="description">Description of the request</label>
                                                                <textarea name="description" rows="3" class="form-control" ><?php echo $key['description'];?></textarea>
                                                            </div>
                                                        </div>
                                                        <br>
                                                        <input type="hidden" name="ref" value="Requests/<?php echo $arrayKeys[$i];?>">
                                                        <button class="btn btn-primary" type="submit">Submit update</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php
                                    $i++;
                            }
                                }
                            ?>
                        </tbody>
                    </table>
                </div> --}}
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>S1.no</th>
                                <th>Display Name</th>
                                <th>Phone</th>
                                <th>Email ID</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $auth =  new App\Http\Controllers\FirebaseAuthController;
                                $users = $auth->auth->listUsers();
                                $i = 1;
                                foreach ($users as $user)
                                {
                                    ?>
                                    <tr>
                                        <td><?=$i++;?></td>
                                        <td><?=$user->displayName?></td>
                                        <td><?=$user->phoneNumber?></td>
                                        <td><?=$user->email?></td>
                                        <td>
                                            <form action="{{route('user.edit')}}" method="get">
                                                {{ csrf_field() }}
                                                <input type="hidden" name='uid' value="<?php echo $user->uid;?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Edit</button>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="{{route('user.delete')}}" class="btn btn-danger btn-sm">Delete</a>
                                        </td>

                                    </tr>
                                    <?php
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <a href="{{route('request.create')}}" class="btn btn-success btn-block">Create New Request</a>
                </div>
                <div class="card-body">
                    There are total
                    <?php
                        use App\Http\Controllers\FirebaseController;
                        $controller = new FirebaseController();
                        $data = $controller->database->getReference('Requests')->getSnapshot()->numchildren();
                        echo $data;
                        // echo json_encode($data, JSON_PRETTY_PRINT);
                    ?>
                    user/s.
                </div>
            </div>
        </div>
    </div>
</div>



@endsection


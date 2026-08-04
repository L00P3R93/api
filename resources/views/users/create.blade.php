@extends('layouts.app')

@section('title')
    <title>New User | Kadi Kings</title>
@endsection

@section('style')
@endsection

@section('content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">User Management</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header">
                        Create User
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            @csrf
                            @method('POST')
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="name">Full Name</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                        </div>

                                        <input type="text" class="form-control" name="name" id="name" placeholder="Full Name" value="{{ old('name') }}" required autocomplete="off" />
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="username">Username</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                                        </div>

                                        <input type="text" class="form-control" name="username" id="username" placeholder="Username" value="{{ old('username') }}" required autocomplete="off" />
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="email">Email Address</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="email" id="email" placeholder="Email Address" value="{{ old('email') }}" required autocomplete="off" />
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="phone_no">Phone Number</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                        </div>
                                        <input type="number" class="form-control" name="phone_no" id="phone_no" value="{{ old('phone_no') }}" placeholder="Phone Number" required autocomplete="off" />
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="role">Role</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <select class="form-control" id="role" name="role">
                                            <option> -- Select Role --</option>
                                            @foreach($roles as $role)
                                                <option {{ selected($role->id, old('role'), 'selected') }} value="{{ $role->id }}">{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="status">Status</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <select class="form-control" id="status" name="status">
                                            <option> -- Select Status --</option>
                                            <option {{ selected(1, old('status'), 'selected') }} value="1">Active</option>
                                            <option {{ selected(2, old('status'), 'selected') }} value="2">Blocked</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary bg-gradient-primary">Save</button>
                        </form>
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('script')
    <script>
        $('#role,#status').select2({
            theme: 'bootstrap4',
            minimumResultsForSearch: 10
        })
    </script>
@endsection

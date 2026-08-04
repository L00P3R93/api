@extends('layouts.app')

@section('title')
    <title>Dashboard</title>
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
                        <h1 class="m-0">Dashboard</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                @include('layouts._message')
                <!-- Small boxes (Stat box) -->
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        Summary & Charts
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-3 col-6">
                                <!-- small box -->
                                <div class="small-box bg-gradient-teal">
                                    <div class="inner">
                                        <h4>0</h4>
                                        <p>Active Customers</p>
                                    </div>
                                    <div class="icon">
                                        <i class="ion ion-ios-people-outline"></i>
                                    </div>
                                    <a href="#" target="_blank" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                                </div>
                            </div>
                            <!-- ./col -->
                            <div class="col-md-3 col-6">
                                <!-- small box -->
                                <div class="small-box bg-gradient-orange">
                                    <div class="inner">
                                        <h4>0</h4>
                                        <p>Active Users</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fa fa-exclamation-triangle"></i>
                                    </div>
                                    <a href="{{ url('#') }}" target="_blank" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                                </div>
                            </div>
                            <!-- ./col -->
                            <div class="col-md-3 col-6">
                                <!-- small box -->
                                <div class="small-box bg-gradient-red">
                                    <div class="inner">
                                        <h4>KES {{ number_format(0) }}</h4>
                                        <p>House Wallet</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fa fa-exclamation-triangle"></i>
                                    </div>
                                    <a href="{{  url('#') }}" target="_blank" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <!-- small box -->
                                <div class="small-box bg-gradient-gray">
                                    <div class="inner">
                                        <h4>KES {{  number_format(0) }}</h4>
                                        <p>Income Today</p>
                                    </div>
                                    <div class="icon">
                                        <i class="fa fa-exclamation-triangle"></i>
                                    </div>
                                    <a href="{{ url('#') }}" target="_blank" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('script')
@endsection

@extends('layouts.app')

@section('title')
    <title>Customer: {{ $customer->name }} | Kadi Kings</title>
@endsection

@section('style')
    <!-- DataTables -->
    <link rel="stylesheet" href="{{ url('/assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ url('/assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') }}">
    <link rel="stylesheet" href="{{ url('/assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') }}">
    <!-- Lightbox2 -->
    <link rel="stylesheet" href="{{ url('/assets/plugins/lightbox2/css/lightbox.min.css') }}">
    <style>
        .user_profile_avatar {
            width: 30vh;height: 30vh;
            box-shadow: 0 3px 2px rgba(0, 0, 0, 0.034), 0 7px 5px rgba(0, 0, 0, 0.048), 0 13px 10px rgba(0, 0, 0, 0.06), 0 22px 18px rgba(0, 0, 0, 0.072), 0 42px 33px rgba(0, 0, 0, 0.086), 0 100px 80px rgba(0, 0, 0, 0.12);
            margin: 25px auto;
            border-radius: 5px;
        }
    </style>
@endsection

@section('content')
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Customer Details</h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Customers</li>
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
                    <div class="card card-primary card-outline card-outline-tabs">
                        <div class="card-header p-0 border-bottom-0">
                            <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="custom-tabs-four-biodata-tab" data-toggle="pill" href="#custom-tabs-four-biodata" role="tab" aria-controls="custom-tabs-four-biodata" aria-selected="true">Bio-Data</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="custom-tabs-four-wallet-tab" data-toggle="pill" href="#custom-tabs-four-wallet" role="tab" aria-controls="custom-tabs-four-wallet" aria-selected="false">Wallet Activity</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="custom-tabs-four-transactions-tab" data-toggle="pill" href="#custom-tabs-four-transactions" role="tab" aria-controls="custom-tabs-four-transactions" aria-selected="false">Transactions History</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="custom-tabs-four-files-tab" data-toggle="pill" href="#custom-tabs-four-files" role="tab" aria-controls="custom-tabs-four-files" aria-selected="false">Attachments</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="custom-tabs-four-tabContent">
                                <div class="tab-pane fade show active" id="custom-tabs-four-biodata" role="tabpanel" aria-labelledby="custom-tabs-four-home-tab">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <a href="{{ url('/assets/images/avatar.png') }}" data-lightbox="UserImage" data-title="{{ $customer->name }}">
                                                <img class="user_profile_avatar" src="{{ url('/assets/images/avatar.png') }}" alt="IMG_CUSTOMER" />
                                            </a>
                                        </div>
                                        <div class="col-md-9">
                                            <h3>PERSONAL DETAILS</h3>
                                            <hr/>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-bordered table-hover" style="width: 100%">
                                                    <tr>
                                                        <th>NAME:</th>
                                                        <td style="width: 40%">{{ $customer->name}}</td>
                                                        <th>NATIONAL ID:</th>
                                                        <td>{!! empty($customer->id_no) ? \App\Util\Badge::set('danger', 'NONE'): $customer->id_no !!}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Wallet</th>
                                                        <td>{!! $customer->wallet ? \App\Util\Badge::set('primary bg-gradient-navy', "KES ".number_format($customer->wallet->balance, 2)) : \App\Util\Badge::set('danger', "NONE") !!}</td>
                                                        <th>GENDER:</th>
                                                        <td>{{ getGender(rand(1, 2)) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <th>PHONE NO.:</th>
                                                        <td class="contacts">{!! "<a href='tel:+$customer->phone_no'>$customer->phone_no</a>" !!}</td>
                                                        <th>EMAIL:</th>
                                                        <td>{{ $customer->email }}</td>
                                                    </tr>

                                                </table>
                                            </div>
                                            <hr/>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-bordered table-hover">
                                                    <tr>
                                                        <th>STATUS:</th>
                                                        <td>{!! $customer->getStatusBadge() !!}</td>
                                                        <th>ADDED DATE:</th>
                                                        <td>{{ $customer->created_at }}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="custom-tabs-four-wallet" role="tabpanel" aria-labelledby="custom-tabs-four-settings-tab">
                                    <div class="row m-tb-10">
                                        <div class="col-md-4 col-sm-6 col-xs-12">
                                            <span style="font-size:16px;"><b>Customer Name:</b> {{ $customer->name }}</span>
                                        </div>
                                        <div class="col-md-4 col-sm-6 col-xs-12">
                                            <span style="font-size:16px;"><b>Customer Wallet:</b> {!! $customer->wallet ? \App\Util\Badge::set('primary bg-gradient-navy', "KES ".number_format($customer->wallet->balance, 2)) : \App\Util\Badge::set('danger', "NONE") !!}</span>
                                        </div>
                                    </div>
                                    <div class="m-tb-10">
                                        <table id="wallet_activity" class="table table-hover table-striped table-bordered">
                                            <thead class="bg-gradient-navy">
                                            <tr>
                                                <th>#</th>
                                                <th>Transaction Type</th>
                                                <th>Transaction Date</th>
                                                <th>Amount</th>
                                                <th>Wallet Balance</th>
                                                <th>Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="custom-tabs-four-transactions" role="tabpanel" aria-labelledby="custom-tabs-four-messages-tab">
                                    <div class="row m-tb-10">
                                        <div class="col-md-4 col-sm-6 col-xs-12">
                                            <span style="font-size:16px;"><b>Customer Name:</b> {{ $customer->name }}</span>
                                        </div>
                                        <div class="col-md-4 col-sm-6 col-xs-12">
                                            <span style="font-size:16px;"><b>Customer Wallet:</b> {!! $customer->wallet ? \App\Util\Badge::set('primary bg-gradient-navy', "KES ".number_format($customer->wallet->balance, 2)) : \App\Util\Badge::set('danger', "NONE") !!}</span>
                                        </div>
                                    </div>
                                    <div class="m-tb-10">
                                        <table id="customer_transactions" class="table table-hover table-striped table-bordered">
                                            <thead class="bg-gradient-navy">
                                            <tr>
                                                <th>#</th>
                                                <th>Transaction Ref</th>
                                                <th>Payment Method</th>
                                                <th>Payment Type</th>
                                                <th>Payment Date</th>
                                                <th>Amount</th>
                                                <th>Action</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($transactions as $transaction)
                                                <tr>
                                                    <td>{{ set_number($transaction->id) }}</td>
                                                    <td>{{ $transaction->payment_ref }}</td>
                                                    <td>{!! \App\Util\Badge::set('success bg-gradient-success', "M-PESA") !!}</td>
                                                    <td>{!! $transaction->getPaymentType() !!}</td>
                                                    <td>{{ $transaction->created_at }}</td>
                                                    <td>{{ $transaction->amount }}</td>
                                                    <td>
                                                        <a target="_blank" class="mr-3" href="{{ url('#') }}">
                                                            <img src="{{ url('/assets/images/icons/eye.svg') }}" alt="img">
                                                        </a>
                                                        <a class="confirm-text" onclick="return false">
                                                            <img src="{{ url('/assets/images/icons/delete.svg') }}" alt="img">
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="custom-tabs-four-files" role="tabpanel" aria-labelledby="custom-tabs-four-settings-tab">
                                    <div class="m-tb-20">
                                        <p class="text-muted">
                                            <i class="fas fa-exclamation-circle"></i>
                                            No Files Found.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.card -->
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
@endsection

@section('script')
    <!-- DataTables  & Plugins -->
    <script src="{{ url('/assets/plugins/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/jszip/jszip.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/pdfmake/pdfmake.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/pdfmake/vfs_fonts.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-buttons/js/buttons.print.min.js') }}"></script>
    <script src="{{ url('/assets/plugins/datatables-buttons/js/buttons.colVis.min.js') }}"></script>
    <!-- Lightbox2 -->
    <script src="{{ url('/assets/plugins/lightbox2/js/lightbox.min.js') }}"></script>
    <script>
        $('#wallet_activity, #customer_transactions').DataTable();
    </script>
@endsection

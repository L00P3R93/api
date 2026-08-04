<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4 bg-gradient-navy">
    <!-- Brand Logo -->
    <a href="{{ url('/') }}" class="brand-link text-center">
        <span class="brand-text font-weight-bolder">KADI KINGS</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- SidebarSearch Form -->
        <!--<div class="form-inline mt-2">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar search" type="search" placeholder="Search" aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>-->

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <!-- Add icons to the links using the .nav-icon class
                     with font-awesome or any other icon font library -->
                <li class="nav-item">
                    <a href="{{ url('/dashboard') }}" class="nav-link {{ selected(request()->path(), 'dashboard', 'active') }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <!-- Customers -->
                <li class="nav-item">
                    <a href="{{ url('/customers') }}" class="nav-link {{ selected(request()->path(), 'customers', 'active') }}">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Customers</p>
                    </a>
                </li>
                <!-- Games -->
                <li class="nav-item">
                    <a href="#" class="nav-link {{ selected(request()->path(), 'games', 'active') }}">
                        <i class="nav-icon fas fa-puzzle-piece"></i>
                        <p>Games</p>
                    </a>
                </li>
                <!-- Payments -->
                <li class="nav-item {{ selector(request()->path(), ['deposits','withdraws', 'transactions', 'transfers'], 'menu-open') }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-receipt"></i>
                        <p>Transactions</p>
                        <i class="fas fa-angle-left right"></i>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ url('#') }}" class="nav-link  {{ selected(request()->path(),'deposits', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Customer Deposits</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ url('#') }}" class="nav-link  {{ selected(request()->path(),'withdraws', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Customer Withdraws</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ url('#') }}" class="nav-link  {{ selected(request()->path(),'transactions', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Game Transactions</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ url('#') }}" class="nav-link  {{ selected(request()->path(),'transfers', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Transfers</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Settings -->
                <li class="nav-item {{ selector(request()->path(), ['shares','roles','users'], 'menu-open') }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Organization</p>
                        <i class="fas fa-angle-left right"></i>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ url('/users') }}" class="nav-link {{ selected(request()->path(), 'users', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Users</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ url('/roles') }}" class="nav-link {{ selected(request()->path(), 'roles', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Roles</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ url('/shares') }}" class="nav-link {{ selected(request()->path(), 'shares', 'active') }}">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Shares</p>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>

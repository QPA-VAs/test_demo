@extends('layout')

@section('title')
<?= get_label('dashboard', 'Dashboard') ?>
@endsection

@section('content')
@authBoth
<div class="container-fluid">
    <div class="col-lg-12 col-md-12 order-1">
        <div class="row mt-4">
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-briefcase-alt-2 bx-md text-success"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_projects', 'Total projects') ?></span>
                        <h3 class="card-title mb-2">{{is_countable($projects) && count($projects) > 0?count($projects):0}}</h3>
                        <a href="/projects"><small class="text-success fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-task bx-md text-primary"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_tasks', 'Total tasks') ?></span>
                        <h3 class="card-title mb-2">{{$tasks}}</h3>
                        <a href="/tasks"><small class="text-primary fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                    </div>
                </div>
            </div>
            @hasRole('admin|member')
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bxs-user-detail bx-md text-warning"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_users', 'Total users') ?></span>
                        <h3 class="card-title mb-2">{{is_countable($users) && count($users) > 0?count($users):0}}</h3>
                        <a href="/users"><small class="text-warning fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bxs-user-detail bx-md text-info"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_clients', 'Total clients') ?></span>
                        <h3 class="card-title mb-2"> {{is_countable($clients) && count($clients) > 0?count($clients):0}}</h3>
                        <a href="/clients"><small class="text-info fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                    </div>
                </div>
            </div>
            @else
            @endhasRole

        </div>
    </div>

    @if ($auth_user->can('manage_projects') || $auth_user->can('manage_tasks'))
    <div class="nav-align-top my-4">
        <ul class="nav nav-tabs" role="tablist">
            @if ($auth_user->can('manage_projects'))
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-projects" aria-controls="navs-top-projects" aria-selected="true">
                    <i class="menu-icon tf-icons bx bx-briefcase-alt-2 text-success"></i><?= get_label('projects', 'Projects') ?>
                </button>
            </li>
            @endif
            @if ($auth_user->can('manage_tasks'))
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-tasks" aria-controls="navs-top-tasks" aria-selected="false">
                    <i class="menu-icon tf-icons bx bx-task text-primary"></i><?= get_label('tasks', 'Tasks') ?>
                </button>
            </li>
            @endif
        </ul>
        <div class="tab-content">
            @if ($auth_user->can('manage_projects'))
            <div class="tab-pane fade active show" id="navs-top-projects" role="tabpanel">

                <div class="table-responsive text-nowrap">

                    <div class="d-flex justify-content-between">
                        <h4 class="fw-bold">{{$auth_user->first_name}}'s <?= get_label('projects', 'Projects') ?></h4>
                    </div>
                    @if (is_countable($projects) && count($projects) > 0)
                    <?php
                    $type = isUser() ? 'user' : 'client';
                    $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id;
                    ?>
                    <x-projects-card :projects="$projects" :id="$id" :users="$users" :clients="$clients" />
                    @else
                    <?php
                    $type = 'Projects'; ?>
                    <x-empty-state-card :type="$type" />

                    @endif
                </div>

            </div>
            @endif

            @if ($auth_user->can('manage_tasks'))
            <div class="tab-pane fade {{!$auth_user->can('manage_projects')?'active show':''}}" id="navs-top-tasks" role="tabpanel">

                <div class="table-responsive text-nowrap">

                    <div class="d-flex justify-content-between">
                        <h4 class="fw-bold">{{$auth_user->first_name}}'s <?= get_label('tasks', 'Tasks') ?></h4>
                    </div>
                    @if ($tasks > 0)
                    <?php
                    $type = isUser() ? 'user' : 'client';
                    $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id;
                    ?>
                    <x-tasks-card :tasks="$tasks" :id="$id" :users="$users" :clients="$clients" :projects="$projects" />
                    @else
                    <?php
                    $type = 'Tasks'; ?>
                    <x-empty-state-card :type="$type" />

                    @endif
                </div>

            </div>
            @endif

        </div>
    </div>
    @endif
    <!-- ------------------------------------------- -->
    <?php

    $titles = [];
    $project_counts = [];
    $task_counts = [];
    $bg_colors = [];
    $total_projects = 0;
    $total_tasks = 0;

    $total_todos = count($todos);
    $done_todos = 0;
    $pending_todos = 0;
    $todo_counts = [];
    $ran = array('#63ed7a', '#ffa426', '#fc544b', '#6777ef', '#FF00FF', '#53ff1a', '#ff3300', '#0000ff', '#00ffff', '#99ff33', '#003366', '#cc3300', '#ffcc00', '#ff00ff', '#ff9900', '#3333cc', '#ffff00');
    $backgroundColor = array_rand($ran);
    $d = $ran[$backgroundColor];
    //foreach ($statuses as $status) {
//        $project_count = isAdminOrHasAllDataAccess() ? count($status->projects) : $auth_user->status_projects($status->id)->count();
//        array_push($project_counts, $project_count);
//
//        $task_count = isAdminOrHasAllDataAccess() ? count($status->tasks) : $auth_user->status_tasks($status->id)->count();
//        array_push($task_counts, $task_count);
//
//        array_push($titles, "'" . $status->title . "'");
//
//        $k = array_rand($ran);
//        $v = $ran[$k];
//        array_push($bg_colors, "'" . $v . "'");
//
//        $total_projects += $project_count;
//        $total_tasks += $task_count;
//    }
    $titles = implode(",", $titles);
    $project_counts = implode(",", $project_counts);
    $task_counts = implode(",", $task_counts);
    $bg_colors = implode(",", $bg_colors);

    foreach ($todos as $todo) {
        $todo->is_completed ? $done_todos += 1 : $pending_todos += 1;
    }
    array_push($todo_counts, $done_todos);
    array_push($todo_counts, $pending_todos);
    $todo_counts = implode(",", $todo_counts);
    ?>
</div>
<script>
    var labels = [<?= $titles ?>];
    var project_data = [<?= $project_counts ?>];
    var task_data = [<?= $task_counts ?>];
    var bg_colors = [<?= $bg_colors ?>];
    var total_projects = [<?= $total_projects ?>];
    var total_tasks = [<?= $total_tasks ?>];
    var total_todos = [<?= $total_todos ?>];
    var todo_data = [<?= $todo_counts ?>];
    //labels
    var done = '<?= get_label('done', 'Done') ?>';
    var pending = '<?= get_label('pending', 'Pending') ?>';
    var total = '<?= get_label('total', 'Total') ?>';
</script>
<script src="{{asset('assets/js/apexcharts.js')}}"></script>
<script src="{{asset('assets/js/pages/dashboard.js')}}"></script>
@else
<div class="w-100 h-100 d-flex align-items-center justify-content-center"><span>You must <a href="/login">Log in</a> or <a href="/register">Register</a> to access {{$general_settings['company_title']}}!</span></div>
@endauth
@endsection

@extends('layout')

@section('title')
<?= get_label('project_details', 'Project details') ?>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between m-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('/home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{url('/projects')}}"><?= get_label('projects', 'Projects') ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{url('/projects/information/'.$project->id)}}">{{$project->title}}</a>
                    </li>
                    <li class="breadcrumb-item active"><?= get_label('view', 'View') ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{url('/projects/tasks/create/' . $project->id)}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_task', 'Create task') ?>"><i class="bx bx-plus"></i></button></a>
            <a href="{{url('/projects/tasks/draggable/' . $project->id)}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('tasks', 'Tasks') ?>"><i class="bx bx-task"></i></button></a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="demo-inline-spacing">
                                @foreach ($tags as $tag)
                                <span class="badge bg-{{$tag->color}}">{{$tag->title}}</span>
                                @endforeach
                            </div>
                            <h2 class="fw-bold mt-4 mb-4">{{ $project->title }} <a href="javascript:void(0);" class="mx-2">
                                    <i class='bx {{$project->is_favorite ? "bxs" : "bx"}}-star favorite-icon text-warning' data-id="{{$project->id}}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{$project->is_favorite ? get_label('add_favorite', 'Click to remove from favorite') : get_label('remove_favorite', 'Click to mark as favorite')}}" data-favorite="{{$project->is_favorite ? 1 : 0}}"></i>
                                </a></h2>
                        </div>
                        <div class="col-md-3 mt-4">
                            <div class="mb-3 text-center">
                                <label class="form-label" for="start_date"><?= get_label('users', 'Users') ?></label>
                                <?php
                                $users = $project->users;
                                if (count($users) > 0) { ?>
                                    <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap justify-content-center">
                                        @foreach($users as $user)
                                        <li class="avatar avatar-sm pull-up" title="{{$user->first_name}} {{$user->last_name}}"><a href="/users/profile/{{$user->id}}" target="_blank">
                                                <img src="{{$user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$user->first_name}} {{$user->last_name}}">
                                            </a></li>
                                        @endforeach
                                    </ul>
                                <?php } else { ?>
                                    <span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="col-md-3 mt-4">
                            <div class="mb-3 text-center">
                                <label class="form-label" for="end_date"><?= get_label('clients', 'Clients') ?></label>
                                <?php
                                $clients = $project->clients;
                                if (count($clients) > 0) { ?>
                                    <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap justify-content-center">
                                        @foreach($clients as $client)
                                        <li class="avatar avatar-sm pull-up" title="{{$client->first_name}} {{$client->last_name}}"><a href="/clients/profile/{{$client->id}}" target="_blank">
                                                <img src="{{$client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$client->first_name}} {{$client->last_name}}">
                                            </a></li>
                                        @endforeach
                                    </ul>
                                <?php } else { ?>
                                    <span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-0" />
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-4 col-md-12 col-6 mb-4">
                            <!-- "Starts at" card -->
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-iconsbx bx bx-calendar-check bx-md text-success"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('package', 'Package Type') ?></span>
                                    <h3 class="card-title mb-2">{{($project->package) }}</h3>
                                </div>
                            </div>
                            <!-- @php
                            use Carbon\Carbon;
                            $fromDate = Carbon::parse($project->from_date);
                            $toDate = Carbon::parse($project->to_date);
                            $duration = $fromDate->diffInDays($toDate) + 1;
                            @endphp -->
                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="card-title d-flex align-items-start justify-content-between">
                                        <div class="avatar flex-shrink-0">
                                            <i class="menu-icon tf-iconsbx bx bx-time bx-md text-primary"></i>
                                        </div>
                                    </div>
                                    <span class="fw-semibold d-block mb-1"><?= get_label('duration', 'Duration') ?></span>
                                    <h3 class="card-title mb-2">{{ $duration . ' day' . ($duration > 1 ? 's' : '') }}</h3>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12 col-6 mb-4">
    <div class="card mt-4">
        <div class="card-body">
            <div class="card-title d-flex align-items-start justify-content-between">
                <div class="avatar flex-shrink-0">
                    <i class="menu-icon tf-icons bx bx-time bx-md text-warning"></i> 
                </div>
            </div>
            <span class="fw-semibold d-block mb-1"><?= get_label('Hours Booked', 'Hours Booked') ?></span>
            <h3 class="card-title mb-2">{{ \Carbon\Carbon::createFromFormat('H:i:s', $project->hourly)->format('H:i') }}
</h3>
        </div>
    </div>
</div>
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title">
                                        <h5><?= get_label('description', 'Description') ?></h5>
                                    </div>
                                    <p>
                                        <!-- Add your project description here -->
                                        {{ ($project->description !== null && $project->description !== '') ? $project->description : '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
       
        
        
        @if ($auth_user->can('manage_activity_log'))
       

                        
                        </div>

                       
                    </div>
                </div>
            </div>
        </div>
        @endif
        <div class="modal fade" id="create_milestone_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document">
                <form class="modal-content form-submit-event" action="{{url('/projects/store-milestone')}}" method="POST">
                    <input type="hidden" name="project_id" value="{{$project->id}}">
                    <input type="hidden" name="dnr">
                    <input type="hidden" name="table" value="project_milestones_table">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('create_milestone', 'Create milestone') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-body">
                            <div class="row">

                                <div class="col-12 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('title', 'Title') ?> <span class="asterisk">*</span></label>
                                    <input type="text" name="title" class="form-control" placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('starts_at', 'Starts at') ?> <span class="asterisk">*</span></label>
                                    <input type="text" id="start_date" name="start_date" class="form-control" placeholder="" autocomplete="off">
                                </div>

                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('ends_at', 'Ends at') ?> <span class="asterisk">*</span></label>
                                    <input type="text" id="end_date" name="end_date" class="form-control" placeholder="" autocomplete="off">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('cost', 'Cost') ?> <span class="asterisk">*</span></label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text">{{$general_settings['currency_symbol']}}</span>
                                        <input type="text" name="cost" class="form-control" placeholder="<?= get_label('please_enter_cost', 'Please enter cost') ?>">
                                    </div>
                                    <p class="text-danger text-xs mt-1 error-message"></p>
                                </div>

                            </div>
                            <label for="description" class="form-label"><?= get_label('description', 'Description') ?></label>
                            <textarea class="form-control" name="description" placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= get_label('close', 'Close') ?>
                        </button>
                        <button type="submit" id="submit_btn" class="btn btn-primary"><?= get_label('create', 'Create') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="edit_milestone_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document">
                <form class="modal-content form-submit-event" action="{{url('/projects/update-milestone')}}" method="POST">
                    <input type="hidden" name="id" id="milestone_id">
                    <input type="hidden" name="project_id" value="{{$project->id}}">
                    <input type="hidden" name="dnr">
                    <input type="hidden" name="table" value="project_milestones_table">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('update_milestone', 'Update milestone') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-body">
                            <div class="row">

                                <div class="col-12 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('title', 'Title') ?> <span class="asterisk">*</span></label>
                                    <input type="text" name="title" id="milestone_title" class="form-control" placeholder="<?= get_label('please_enter_title', 'Please enter title') ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('starts_at', 'Starts at') ?> <span class="asterisk">*</span></label>
                                    <input type="text" id="update_milestone_start_date" name="start_date" class="form-control" placeholder="" autocomplete="off">
                                </div>

                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('ends_at', 'Ends at') ?> <span class="asterisk">*</span></label>
                                    <input type="text" id="update_milestone_end_date" name="end_date" class="form-control" placeholder="" autocomplete="off">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('cost', 'Cost') ?> <span class="asterisk">*</span></label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text">{{$general_settings['currency_symbol']}}</span>
                                        <input type="text" name="cost" id="milestone_cost" class="form-control" placeholder="<?= get_label('please_enter_cost', 'Please enter cost') ?>">
                                    </div>
                                    <p class="text-danger text-xs mt-1 error-message"></p>
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="nameBasic" class="form-label"><?= get_label('progress', 'Progress') ?></label>
                                    <input type="range" name="progress" id="milestone_progress" class="form-range">
                                    <h6 class="mt-2 milestone-progress"></h6>
                                    <p class="text-danger text-xs mt-1 error-message"></p>
                                </div>

                            </div>
                            <label for="description" class="form-label"><?= get_label('description', 'Description') ?></label>
                            <textarea class="form-control" name="description" id="milestone_description" placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <?= get_label('close', 'Close') ?>
                        </button>
                        <button type="submit" id="submit_btn" class="btn btn-primary"><?= get_label('update', 'Update') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php

$titles = [];
$task_counts = [];
$bg_colors = [];
$total_tasks = 0;

$ran = array('#63ed7a', '#ffa426', '#fc544b', '#6777ef', '#FF00FF', '#53ff1a', '#ff3300', '#0000ff', '#00ffff', '#99ff33', '#003366', '#cc3300', '#ffcc00', '#ff00ff', '#ff9900', '#3333cc', '#ffff00');
$backgroundColor = array_rand($ran);
$d = $ran[$backgroundColor];
$titles = implode(",", $titles);
$task_counts = implode(",", $task_counts);
$bg_colors = implode(",", $bg_colors);
?>

<script>
    var labels = [<?= $titles ?>];
    var task_data = [<?= $task_counts ?>];
    var bg_colors = [<?= $bg_colors ?>];
    var total_tasks = [<?= $total_tasks ?>];
    //labels
    var total = '<?= get_label('total', 'Total') ?>';
    var add_favorite = '<?= get_label('add_favorite', 'Click to mark as favorite') ?>';
    var remove_favorite = '<?= get_label('remove_favorite', 'Click to remove from favorite') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_download = '<?= get_label('download', 'Download') ?>';
</script>

<script src="{{asset('assets/js/apexcharts.js')}}"></script>
<script src="{{asset('assets/js/pages/project-information.js')}}"></script>
@endsection

@extends('layout')

@section('title')
<?= get_label('update_project', 'Update project') ?>

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
                    <li class="breadcrumb-item active">
                        <?= get_label('update', 'Update') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>



    <div class="card mt-4">
        <div class="card-body">
            <form action="{{url('/projects/update/' . $project->id)}}" class="form-submit-event" method="POST">
                <input type="hidden" name="redirect_url" value="/projects">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="mb-3">
                        <label for="title" class="form-label"><?= get_label('project / business title', 'Project / Business title') ?> <span class="asterisk">*</span></label>
                        <input class="form-control" type="text" id="title" name="title" placeholder="<?= get_label('project / business title', 'project / business title') ?>" value="{{ $project->title }}">
                        @error('title')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="row">
                    <div class="mb-3 col-md-6">
                        <label for="hourly" class="form-label"><?= get_label('hourly', 'hourly') ?></label>
                        <div class="input-group input-group-merge">
                        <span class="input-group-text">hrs</span>
                            <input class="form-control" type="number" id="hourly" name="hourly" placeholder="<?= get_label('please_enter_hourly', 'Please enter hourly') ?>" value="{{ $project->budget??'' }}">
                        </div>
                        <p class="text-danger text-xs mt-1 error-message"></p>
                    </div>
                    <div class="mb-3 col-md-6">
                        <label for="package_type"  class="form-label"><?= get_label('Package Type', 'Package Type') ?><span class="text-danger">*</span></label>
                        <div class="input-group input-group-merge">
                        <select class="form-control" id="package" name="package">
    <option value=""><?= get_label('please_select_package_type', 'Please select package type') ?></option>
    <option value="hourly" {{ $project->package == 'hourly' ? 'selected' : '' }}>
        <?= get_label('hourly', 'Hourly') ?>
    </option>
    <option value="fixed" {{ $project->package == 'fixed' ? 'selected' : '' }}>
        <?= get_label('fixed', 'Fixed') ?>
    </option>
</select>

        </div>
                    
                </div>

                <div class="row">
                    <div class="mb-3">
                        <label class="form-label" for="user_id"><?= get_label('select_users', 'Select users') ?></label>
                        <div class="input-group">
                            <select id="" class="form-control js-example-basic-multiple" name="user_id[]" multiple="multiple" data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                                <?php
                                $project_users = $project->users;
                                ?>
                                @foreach($users as $user)
                                <?php $selected = $user->id == $project->created_by ? "selected" : "" ?>
                                <option value="{{$user->id}}" <?= $selected ?> <?php if ($project_users->contains($user)) {
                                                                                    echo "selected";
                                                                                } ?>>{{$user->first_name}} {{$user->last_name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="mb-3">
                        <label class="form-label" for="client_id"><?= get_label('select_clients', 'Select clients') ?></label>
                        <div class="input-group">
                            <select id="" class="form-control js-example-basic-single" name="client_id" data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                                <?php
                                $project_clients = $project->clients;
                                ?>
                                @foreach($clients as $client)
                                <option value="{{$client->id}}" <?php if ($project_clients->contains($client)) {
                                                                    echo "selected";
                                                                } ?>>{{$client->first_name}} {{$client->last_name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-3 col-md-12">
                        <label class="form-label" for=""><?= get_label('select_tags', 'Select tags') ?></label>
                        <div class="input-group">
                            <select id="" class="form-control js-example-basic-multiple" name="tag_ids[]" multiple="multiple" data-placeholder="<?= get_label('type_to_search', 'Type to search') ?>">
                                <?php
                                $project_tags = $project->tags;
                                ?>
                                @foreach($tags as $tag)
                                <option value="{{$tag->id}}" <?php if ($project_tags->contains($tag)) {
                                                                    echo "selected";
                                                                } ?>>{{$tag->title}}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-2">
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_tag_modal"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title=" <?= get_label('create_tag', 'Create tag') ?>"><i class="bx bx-plus"></i></button></a>
                            <a href="/tags/manage"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="<?= get_label('manage_tags', 'Manage tags') ?>"><i class="bx bx-list-ul"></i></button></a>
                        </div>
                    </div>
                </div>

                <div class="row">

                    <div class="mb-3">
                        <label for="description" class="form-label"><?= get_label('description', 'Description') ?> <span class="asterisk">*</span></label>
                        <textarea class="form-control" id="description" name="description" placeholder="<?= get_label('please_enter_description', 'Please enter description') ?>" rows="5">{{ $project->description }}</textarea>
                        @error('description')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>


                <div class="mt-2">
                    <button type="submit" class="btn btn-primary me-2" id="submit_btn"><?= get_label('update', 'Update') ?></button>
                    <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

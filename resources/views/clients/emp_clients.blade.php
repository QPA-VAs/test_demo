@extends('layout')

@section('title')
<?= get_label('clients', 'Clients') ?>
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
                    <li class="breadcrumb-item active">
                        <?= get_label('clients', 'Clients') ?>
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{url('/clients/create')}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_client', 'Create client') ?>"><i class='bx bx-plus'></i></button></a>
        </div>
    </div>
    @if (is_countable($clients) && count($clients) > 0)
    <div class="card mt-4">
        <div class="card-body">
            <input type="hidden" id="data_type" value="clients">
            <div class="table-responsive text-nowrap">
                <div class="row">
                    @foreach ($clients as $client)
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex flex-row align-items-center mb-3">
                                        <img class="avatar avatar-md pull-up mr-20" src="{{ asset('storage/' . $client->photo) }}" alt="Client Photo">
                                        <div><h5 class="card-title">{{ $client->first_name }} {{ $client->last_name }}</h5></div>
                                    </div>
                                    <h6 class="card-subtitle mb-2 text-muted">{{ $client->company }}</h6>
                                    <p class="card-text">{{ $client->phone }}</p>
                                    <p class="card-text">{{ $client->assigned }}</p>
                                    <p class="card-text">{{ $client->created_at }}</p>
                                    <p class="card-text">{{ $client->updated_at }}</p>
                                    <a href="/clients/profile/{{$client->id}}"  class="card-link">view</a>
                                    <a href="/clients/edit/{{$client->id}}" class="card-link">edit</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @else
    <?php
    $type = 'Clients'; ?>
    <x-empty-state-card :type="$type" />
    @endif
</div>
<script>
    var label_update = '<?= get_label('update', 'Update') ?>';
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
    var label_projects = '<?= get_label('projects', 'Projects') ?>';
    var label_tasks = '<?= get_label('tasks', 'Tasks') ?>';
</script>
<script src="{{asset('assets/js/pages/clients.js')}}"></script>
@endsection

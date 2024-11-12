<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Project;
use App\Models\Workspace;
use App\Models\Milestone;
use App\Models\ProjectUser;
use Illuminate\Http\Request;
use App\Models\ProjectClient;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Exception;

class ProjectsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for ProjectsController
     * 
     * Applies middleware to fetch and set workspace and authenticated user information
     * for use throughout the controller.
     * 
     * The middleware:
     * - Retrieves workspace from session using workspace_id
     * - Gets currently authenticated user
     * - Makes these available via $this->workspace and $this->user
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(session()->get('workspace_id'));
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * Display a paginated list of projects with optional filtering and sorting.
     *
     * @param Request $request The HTTP request object
     * @param string|null $type Optional type parameter to filter favorite projects
     * @return \Illuminate\View\View Returns view with paginated projects and related data
     *
     * Filters available:
     * - status: Filter projects by status_id
     * - tags: Filter projects by tag IDs
     * - type='favorite': Show only favorite projects
     *
     * Sorting options:
     * - newest: Sort by creation date (desc)
     * - oldest: Sort by creation date (asc)
     * - recently-updated: Sort by update date (desc)
     * - earliest-updated: Sort by update date (asc)
     * 
     * The method:
     * 1. Processes filter parameters from request
     * 2. Builds query conditions based on filters
     * 3. Applies tag filtering if specified
     * 4. Includes total time spent calculation for each project
     * 5. Returns paginated results (6 items per page) with project data
     */
    public function index(Request $request, $type = null)
    {
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $selectedTags = (request('tags')) ? request('tags') : [];
        $where = [];
        if ($status != '') {
            $where['status_id'] = $status;
        }
        $is_favorite = 0;
        if ($type === 'favorite') {
            $where['is_favorite'] = 1;
            $is_favorite = 1;
        }
        $sort = (request('sort')) ? request('sort') : "id";
        $order = 'desc';
        if ($sort == 'newest') {
            $sort = 'created_at';
            $order = 'desc';
        } elseif ($sort == 'oldest') {
            $sort = 'created_at';
            $order = 'asc';
        } elseif ($sort == 'recently-updated') {
            $sort = 'updated_at';
            $order = 'desc';
        } elseif ($sort == 'earliest-updated') {
            $sort = 'updated_at';
            $order = 'asc';
        }
        $projects = $this->workspace->projects();

$projects->where($where);

if (!empty($selectedTags)) {
    $projects->whereHas('tags', function ($q) use ($selectedTags) {
        $q->whereIn('tags.id', $selectedTags);
    });
}

// Calculate total time spent for each project
$projects = $projects->with(['tasks' => function ($q) {
    $q->selectRaw('project_id, SUM(time_spent) as total_time_spent')
        ->groupBy('project_id');
}]);

$projects = $projects->orderBy($sort, $order)->paginate(6);

return view('projects.grid_view', [
    'projects' => $projects,
    'auth_user' => $this->user,
    'selectedTags' => $selectedTags,
    'is_favorite' => $is_favorite
]);

    }

    /**
     * Display the list view of projects.
     *
     * @param \Illuminate\Http\Request $request The HTTP request instance
     * @param string|null $type The type of projects to display (e.g., 'favorite')
     * @return \Illuminate\View\View Returns the projects view with projects, users, clients and favorites flag
     */
    public function list_view(Request $request, $type = null)
    {
        $projects = $this->workspace->projects;
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $is_favorites = 0;
        if ($type === 'favorite') {
            $is_favorites = 1;
        }
        return view('projects.projects', ['projects' => $projects, 'users' => $users, 'clients' => $clients, 'is_favorites' => $is_favorites]);
    }

    /**
     * Display the project creation form.
     *
     * Retrieves users and clients associated with the current workspace
     * and passes them to the view for project creation.
     *
     * @return \Illuminate\View\View Returns the create project view with users, clients and authenticated user data
     */
    public function create()
    {
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;

        return view('projects.create_project', ['users' => $users, 'clients' => $clients, 'auth_user' => $this->user]);
    }

    /**
     * Store a newly created project in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     * 
     * This method handles the creation of a new project with the following steps:
     * 1. Validates required input fields (title, description, package)
     * 2. Formats hourly time input from minutes to HH:MM:SS format
     * 3. Associates project with current workspace and creator
     * 4. Creates project record in database
     * 5. Handles assignment of users, clients and tags to project
     * 6. Automatically adds creator as participant
     * 7. Sets flash message for success
     * 
     * @return JSON response with error status and new project ID
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
//            'status_id' => ['required'],
            // 'start_date' => ['nullable', 'before_or_equal:end_date'],
            // 'end_date' => ['nullable'],
            'description' => ['required'],
            'package' => ['required', 'string'],
        ]);
       
        // $start_date = $request->input('start_date');
        // $end_date = $request->input('end_date');
        // $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        // $formFields['end_date'] = format_date($end_date, null, "Y-m-d");
        $formFields['package'] = $request->input('package'); 
        // $formFields['hourly'] = $request->input('hourly'); 
        
        
        $hourly = $request->input('hourly');
        $hours = floor($hourly / 60); // Get the number of hours
        $minutes = $hourly % 60; // Get the remaining minutes

        // Format hours and minutes as HH:MM:SS
        $time_formatted = sprintf("%02d:%02d:00", $hours, $minutes);

        $formFields['hourly'] = $time_formatted;
       



        
        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = $this->user->id;


        $new_project = Project::create($formFields);

        $userIds = $request->input('user_id') ?? [];
        $clientIds = $request->input('client_id') ?? [];
        $tagIds = $request->input('tag_ids') ?? [];
        // Set creator as a participant automatically
        if (Auth::guard('client')->check() && !in_array($this->user->id, $clientIds)) {
            array_splice($clientIds, 0, 0, $this->user->id);
        } else if (Auth::guard('web')->check() && !in_array($this->user->id, $userIds)) {
            array_splice($userIds, 0, 0, $this->user->id);
        }

        $project_id = $new_project->id;
        $project = Project::find($project_id);
        $project->users()->attach($userIds);
        $project->clients()->attach($clientIds);
        $project->tags()->attach($tagIds);
       
        Session::flash('message', 'Project created successfully.');
        return response()->json(['error' => false, 'id' => $new_project->id]);


        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * Display the specified project details.
     * 
     * This method retrieves a specific project and its related information including:
     * - Associated tags
     * - Workspace users
     * - Workspace clients
     * - Available controller types
     * 
     * @param int $id The ID of the project to display
     * @return \Illuminate\View\View Returns view with project information and related data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project is not found
     */
    public function show($id)
    {

        $project = Project::findOrFail($id);
        $tags = $project->tags;
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $types = getControllerNames();
        return view('projects.project_information', ['project' => $project, 'tags' => $tags, 'users' => $users, 'clients' => $clients, 'types' => $types, 'auth_user' => $this->user]);
    }

    /**
     * Show the form for editing the specified project.
     *
     * @param  int  $id The ID of the project to edit
     * @return \Illuminate\View\View Returns view with project, users and clients data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project is not found
     */
    public function edit($id)
    {
        $project = Project::findOrFail($id);
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        // dd($project->package);
        return view('projects.update_project', ["project" => $project, "users" => $users, "clients" => $clients]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            // 'status_id' => ['required'],
            // 'hourly' => ['nullable'],
            // 'start_date' => ['nullable', 'before_or_equal:end_date'],
            // 'end_date' => ['nullable'],
            'description' => ['required'],

        ]);

        // $start_date = $request->input('start_date');
        // $end_date = $request->input('end_date');
        // $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        // $formFields['end_date'] = format_date($end_date, null, "Y-m-d");

        $formFields['package'] = $request->input('package'); 
      
        $hourly = $request->input('hourly');
        $hours = floor($hourly / 60); // Get the number of hours
        $minutes = $hourly % 60; // Get the remaining minutes

        // Format hours and minutes as HH:MM:SS
        $time_formatted = sprintf("%02d:%02d:00", $hours, $minutes);

        $formFields['hourly'] = $time_formatted;


        $userIds = $request->input('user_id') ?? [];
        $clientIds = $request->input('client_id') ?? [];
        $tagIds = $request->input('tag_ids') ?? [];
        $project = Project::findOrFail($id);
        // Set creator as a participant automatically
        if (User::where('id', $project->created_by)->exists() && !in_array($project->created_by, $userIds)) {
            array_splice($userIds, 0, 0, $project->created_by);
        } elseif (Client::where('id', $project->created_by)->exists() && !in_array($project->created_by, $clientIds)) {
            array_splice($clientIds, 0, 0, $project->created_by);
        }
        $project->update($formFields);
        $project->users()->sync($userIds);
        $project->clients()->sync($clientIds);
        $project->tags()->sync($tagIds);

        Session::flash('message', 'Project updated successfully.');
        return response()->json(['error' => false, 'id' => $id]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {
        $response = DeletionService::delete(Project::class, $id, 'Project');
        return $response;
    }

    /**
     * Delete multiple projects simultaneously.
     *
     * @param \Illuminate\Http\Request $request The request object containing project IDs
     * @return \Illuminate\Http\JsonResponse JSON response with deletion status
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * Request body should contain:
     * {
     *     "ids": [1, 2, 3] // Array of project IDs to delete
     * }
     *
     * Response format:
     * {
     *     "error": false,
     *     "message": "Project(s) deleted successfully.",
     *     "id": [1, 2, 3], // Array of successfully deleted project IDs
     *     "titles": ["Project 1", "Project 2", "Project 3"] // Array of deleted project titles
     * }
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:projects,id' // Ensure each ID in 'ids' is an integer and exists in the 'projects' table
        ]);

        $ids = $validatedData['ids'];
        $deletedProjects = [];
        $deletedProjectTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $project = Project::find($id);
            if ($project) {
                $deletedProjectTitles[] = $project->title;
                DeletionService::delete(Project::class, $id, 'Project');
                $deletedProjects[] = $id;
            }
        }

        return response()->json(['error' => false, 'message' => 'Project(s) deleted successfully.', 'id' => $deletedProjects, 'titles' => $deletedProjectTitles]);
    }



    /**
     * Lists and filters projects based on various parameters.
     *
     * @param Request $request The HTTP request object
     * @param string $id Optional. Format: '{type}_{id}' where type can be 'user' or 'client'
     * @param string $type Optional. Type parameter (unused in current implementation)
     * @return JsonResponse Returns JSON with filtered projects and total count
     *
     * Request parameters:
     * @param string $search Optional. Search term for project title
     * @param string $sort Optional. Column to sort by (defaults to "id")
     * @param string $order Optional. Sort order (defaults to "DESC")
     * @param string $status Optional. Filter by status ID
     * @param string $user_id Optional. Filter by user ID
     * @param string $client_id Optional. Filter by client ID
     * @param string $project_start_date_from Optional. Start date range begin
     * @param string $project_start_date_to Optional. Start date range end
     * @param string $project_end_date_from Optional. End date range begin
     * @param string $project_end_date_to Optional. End date range end
     * @param string $is_favorites Optional. Filter favorite projects only
     * @param int $limit Optional. Number of items per page
     *
     * JSON Response format:
     * {
     *   "rows": [
     *     {
     *       "id": int,
     *       "title": string,
     *       "users": array,
     *       "clients": array,
     *       "start_date": string,
     *       "end_date": string,
     *       "hourly": string,
     *       "created_at": string,
     *       "updated_at": string
     *     }
     *   ],
     *   "total": int
     * }
     */

    /**
     * Updates the favorite status of a project.
     *
     * @param Request $request The HTTP request object
     * @param int $id The project ID
     * @return JsonResponse Returns JSON with error status
     *
     * Request parameters:
     * @param bool $is_favorite The new favorite status
     *
     * JSON Response format:
     * {
     *   "error": bool,
     *   "message"?: string
     * }
     */
    public function list(Request $request, $id = '', $type = '')
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";
        $start_date_from = (request('project_start_date_from')) ? request('project_start_date_from') : "";
        $start_date_to = (request('project_start_date_to')) ? request('project_start_date_to') : "";
        $end_date_from = (request('project_end_date_from')) ? request('project_end_date_from') : "";
        $end_date_to = (request('project_end_date_to')) ? request('project_end_date_to') : "";
        $is_favorites = (request('is_favorites')) ? request('is_favorites') : "";
        $where = [];
        if ($status != '') {
            $where['status_id'] = $status;
        }

        if ($is_favorites) {
            $where['is_favorite'] = 1;
        }

        if ($id) {
            $id = explode('_', $id);
            $belongs_to = $id[0];
            $belongs_to_id = $id[1];
            if ($belongs_to == 'user') {
                $belongs_to = User::find($belongs_to_id);
            }
            if ($belongs_to == 'client') {
                $belongs_to = Client::find($belongs_to_id);
            }
            $projects = $belongs_to->projects();
        } else {
            $projects = $this->workspace->projects();
        }
        if ($user_id) {
            $user = User::find($user_id);
            $projects = $user->projects();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $projects = $client->projects();
        }
        if ($start_date_from && $start_date_to) {
            $projects->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $projects->whereBetween('end_date', [$end_date_from, $end_date_to]);
        }
        $projects->when($search, function ($query) use ($search) {
            return $query->where('title', 'like', '%' . $search . '%');
        });
        $projects->where($where);
        $totalprojects = $projects->count();

        $projects = $projects->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($project) => [
                    'id' => $project->id,
                    'title' => "<a href='/projects/information/" . $project->id . "' target='_blank'><strong>" . $project->title . "</strong></a> <a href='javascript:void(0);' class='mx-2'><i class='bx " . ($project->is_favorite ? 'bxs' : 'bx') . "-star favorite-icon text-warning' data-favorite=" . $project->is_favorite . " data-id=" . $project->id . " title='" . ($project->is_favorite ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')) . "'></i></a>",
                    'users' => $project->users,
                    'clients' => $project->clients,
                    'start_date' => format_date($project->start_date),
                    'end_date' => format_date($project->end_date),
                    'hourly' => !empty($project->hourly) && $project->hourly !== null ? format_currency($project->hourly) : '-',
//                    'status_id' => "<span class='badge bg-label-" . $project->status->color . " me-1'>" . $project->status->title . "</span>",
                    'created_at' => format_date($project->created_at, 'H:i:s'),
                    'updated_at' => format_date($project->updated_at, 'H:i:s'),
                ]
            );
        foreach ($projects->items() as $project => $collection) {
            foreach ($collection['clients'] as $i => $client) {
                $collection['clients'][$i] = "<a href='/clients/profile/" . $client->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $client['first_name'] . " " . $client['last_name'] . "'>
                <img src='" . ($client['photo'] ? asset('storage/' . $client['photo']) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                </li></a>";
            };
        }

        foreach ($projects->items() as $project => $collection) {
            foreach ($collection['users'] as $i => $user) {
                $collection['users'][$i] = "<a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user['first_name'] . " " . $user['last_name'] . "'>
                <img src='" . ($user['photo'] ? asset('storage/' . $user['photo']) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                </li></a>";
            };
        }

        return response()->json([
            "rows" => $projects->items(),
            "total" => $totalprojects,
        ]);
    }

    public function update_favorite(Request $request, $id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json(['error' => true, 'message' => 'Project not found']);
        }

        $isFavorite = $request->input('is_favorite');

        // Update the project's favorite status
        $project->is_favorite = $isFavorite;
        $project->save();
        return response()->json(['error' => false]);
    }

    /**
     * Duplicates a project and its related data
     * 
     * This method creates a copy of an existing project including relationships
     * with users, clients, tasks and tags. It uses the duplicateRecord helper
     * function to handle the duplication process.
     *
     * @param int $id The ID of the project to duplicate
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: status message
     *         - id: original project ID if successful
     * @throws \Exception When duplication fails
     */
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients', 'tasks', 'tags']; // Include related tables as needed

        // Use the general duplicateRecord function
        $duplicate = duplicateRecord(Project::class, $id, $relatedTables);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Project duplication failed.']);
        }

        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Project duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Project duplicated successfully.', 'id' => $id]);
    }

    /**
     * Upload media files to a specific project.
     * 
     * This method handles the upload of multiple media files to a project using Laravel's media library.
     * It sanitizes filenames, generates unique identifiers, and associates the files with the project.
     *
     * @param \Illuminate\Http\Request $request The request object containing:
     *                                         - id: The project ID (integer)
     *                                         - media_files: Array of uploaded files
     * 
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *                                      - error: boolean indicating success/failure
     *                                      - message: Status message
     *                                      - id: Array of media IDs (on success)
     *                                      - type: 'media' (on success)
     *                                      - parent_type: 'project' (on success)
     * 
     * @throws \Exception When validation fails or file upload encounters an error
     */
    public function upload_media(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'integer|exists:projects,id'
            ]);

            $mediaIds = [];

            if ($request->hasFile('media_files')) {
                $project = Project::find($validatedData['id']);
                $mediaFiles = $request->file('media_files');

                foreach ($mediaFiles as $mediaFile) {
                    $mediaItem = $project->addMedia($mediaFile)
                        ->sanitizingFileName(function ($fileName) use ($project) {
                            // Replace special characters and spaces with hyphens
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));

                            // Generate a unique identifier based on timestamp and random component
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);

                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('project-media');

                    $mediaIds[] = $mediaItem->id;
                }


                Session::flash('message', 'File(s) uploaded successfully.');
                return response()->json(['error' => false, 'message' => 'File(s) uploaded successfully.', 'id' => $mediaIds, 'type' => 'media', 'parent_type' => 'project']);
            } else {
                Session::flash('error', 'No file(s) chosen.');
                return response()->json(['error' => true, 'message' => 'No file(s) chosen.']);
            }
        } catch (Exception $e) {
            // Handle the exception as needed
            Session::flash('error', 'An error occurred during file upload: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'An error occurred during file upload: ' . $e->getMessage()]);
        }
    }





    /**
     * Retrieves and formats media files associated with a specific project.
     *
     * This method handles fetching, searching, sorting and formatting media files:
     * - Retrieves media files for a given project ID
     * - Supports searching through media items by ID, filename or creation date
     * - Allows sorting by specified field and order
     * - Formats media items for display, handling both images and other file types
     * - Generates HTML for preview/download links and action buttons
     *
     * @param int $id The project ID to fetch media for
     * @return \Illuminate\Http\JsonResponse JSON containing:
     *         - rows: Array of formatted media items with:
     *           - id: Media item ID
     *           - file: HTML formatted preview/link
     *           - file_name: Original filename
     *           - file_size: Formatted file size
     *           - created_at: Formatted creation timestamp
     *           - updated_at: Formatted update timestamp
     *           - actions: HTML for download/delete buttons
     *         - total: Total count of media items
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project is not found
     */
    public function get_media($id)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $project = Project::findOrFail($id);
        $media = $project->getMedia('project-media');

        if ($search) {
            $media = $media->filter(function ($mediaItem) use ($search) {
                return (
                    // Check if ID contains the search query
                    stripos($mediaItem->id, $search) !== false ||
                    // Check if file name contains the search query
                    stripos($mediaItem->file_name, $search) !== false ||
                    // Check if date created contains the search query
                    stripos($mediaItem->created_at->format('Y-m-d'), $search) !== false
                );
            });
        }
        $formattedMedia = $media->map(function ($mediaItem) {
            // Check if the disk is public
            $isPublicDisk = $mediaItem->disk == 'public' ? 1 : 0;

            // Generate file URL based on disk visibility
            $fileUrl = $isPublicDisk
                ? asset('storage/project-media/' . $mediaItem->file_name)
                : $mediaItem->getFullUrl();

            $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);

            // Check if file extension corresponds to an image type
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
            $isImage = in_array(strtolower($fileExtension), $imageExtensions);

            if ($isImage) {
                $html = '<a href="' . $fileUrl . '" data-lightbox="project-media">';
                $html .= '<img src="' . $fileUrl . '" alt="' . $mediaItem->file_name . '" width="50">';
                $html .= '</a>';
            } else {
                $html = '<a href="' . $fileUrl . '" title=' . get_label('download', 'Download') . '>' . $mediaItem->file_name . '</a>';
            }

            return [
                'id' => $mediaItem->id,
                'file' => $html,
                'file_name' => $mediaItem->file_name,
                'file_size' => formatSize($mediaItem->size),
                'created_at' => format_date($mediaItem->created_at, 'H:i:s'),
                'updated_at' => format_date($mediaItem->updated_at, 'H:i:s'),
                'actions' => [
                    '<a href="' . $fileUrl . '" title=' . get_label('download', 'Download') . ' download>' .
                        '<i class="bx bx-download bx-sm"></i>' .
                        '</a>' .
                        '<button title=' . get_label('delete', 'Delete') . ' type="button" class="btn delete" data-id="' . $mediaItem->id . '" data-type="project-media">' .
                        '<i class="bx bx-trash text-danger"></i>' .
                        '</button>'
                ],
            ];
        });


        if ($order == 'asc') {
            $formattedMedia = $formattedMedia->sortBy($sort);
        } else {
            $formattedMedia = $formattedMedia->sortByDesc($sort);
        }

        return response()->json([
            'rows' => $formattedMedia->values()->toArray(),
            'total' => $formattedMedia->count(),
        ]);
    }

    /**
     * Delete a media item from database and disk storage.
     * 
     * @param int $mediaId The ID of the media item to be deleted
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: string describing the result of operation
     *         - id: int the ID of deleted media item
     *         - title: string the filename of deleted media
     *         - parent_id: int the model ID associated with media
     *         - type: string constant 'media'
     *         - parent_type: string constant 'project'
     * @throws \Exception If media deletion fails
     */
    public function delete_media($mediaId)
    {
        $mediaItem = Media::find($mediaId);

        if (!$mediaItem) {
            // Handle case where media item is not found
            return response()->json(['error' => true, 'message' => 'File not found.']);
        }

        // Delete media item from the database and disk
        $mediaItem->delete();

        return response()->json(['error' => false, 'message' => 'File deleted successfully.', 'id' => $mediaId, 'title' => $mediaItem->file_name, 'parent_id' => $mediaItem->model_id,  'type' => 'media', 'parent_type' => 'project']);
    }

    /**
     * Delete multiple media files based on provided IDs.
     *
     * This method handles bulk deletion of media files. It validates the incoming IDs,
     * ensures they exist in the media table, and performs the deletion operation.
     * It keeps track of deleted items' information for response purposes.
     *
     * @param \Illuminate\Http\Request $request The request object containing media IDs to delete
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error (boolean) - Operation status flag
     *         - message (string) - Success message
     *         - id (array) - Array of deleted media IDs
     *         - titles (array) - Array of deleted file names
     *         - parent_id (array) - Array of parent model IDs
     *         - type (string) - Type of deleted items ('media')
     *         - parent_type (string) - Parent model type ('project')
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function delete_multiple_media(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:media,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $media = Media::find($id);
            if ($media) {
                $deletedIds[] = $id;
                $deletedTitles[] = $media->file_name;
                $parentIds[] = $media->model_id;
                $media->delete();
            }
        }

        return response()->json(['error' => false, 'message' => 'Files(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'parent_id' => $parentIds, 'type' => 'media', 'parent_type' => 'project']);
    }

    /**
     * Store a new milestone in the database.
     *
     * Validates and processes milestone data from request, including:
     * - Project ID
     * - Title
     * - Status
     * - Start and end dates
     * - Hourly rate
     * - Description
     *
     * Formats dates to Y-m-d format and adds workspace ID and creator info before saving.
     *
     * @param  \Illuminate\Http\Request  $request Request containing milestone data
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: false on success
     *         - message: Success message
     *         - id: Created milestone ID
     *         - type: 'milestone'
     *         - parent_type: 'project'
     */
    public function store_milestone(Request $request)
    {
        $formFields = $request->validate([
            'project_id' => ['required'],
            'title' => ['required'],
            'status' => ['required'],
            'start_date' => ['nullable', 'before_or_equal:end_date'],
            'end_date' => ['nullable'],
            'hourly' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'description' => ['nullable'],
        ]);

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        $formFields['end_date'] = format_date($end_date, null, "Y-m-d");

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;


        $milestone = Milestone::create($formFields);

        return response()->json(['error' => false, 'message' => 'Milestone created successfully.', 'id' => $milestone->id, 'type' => 'milestone', 'parent_type' => 'project']);
    }

    /**
     * Retrieve and format project milestones with optional filtering and sorting
     *
     * @param int $id The project ID
     * @return \Illuminate\Http\JsonResponse Returns JSON containing:
     *         - rows: Array of milestone objects with formatted fields:
     *           - id: Milestone ID
     *           - title: Milestone title 
     *           - status: HTML formatted status badge
     *           - progress: HTML formatted progress bar
     *           - hourly: Formatted hourly rate
     *           - start_date: Formatted start date
     *           - end_date: Formatted end date
     *           - created_by: Creator's full name
     *           - description: Milestone description
     *           - created_at: Formatted creation time
     *           - updated_at: Formatted update time
     *         - total: Total count of filtered milestones
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project not found
     *
     * Supports query parameters:
     * @param string search Optional search term for title, id, hourly rate or description
     * @param string sort Field to sort by (default: "id")
     * @param string order Sort direction (default: "DESC") 
     * @param string status Filter by status
     * @param string start_date_from Start date range begin
     * @param string start_date_to Start date range end
     * @param string end_date_from End date range begin
     * @param string end_date_to End date range end
     * @param int limit Number of items per page
     */
    public function get_milestones($id)
    {
        $project = Project::findOrFail($id);
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $milestones =  $project->milestones();
        if ($search) {
            $milestones = $milestones->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhere('hourly', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        if ($start_date_from && $start_date_to) {
            $milestones = $milestones->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $milestones  = $milestones->whereBetween('to_date', [$end_date_from, $end_date_to]);
        }
        if ($status) {
            $milestones  = $milestones->where('status', $status);
        }
        $total = $milestones->count();
        $milestones = $milestones->orderBy($sort, $order)


            ->paginate(request("limit"))
            ->through(function ($milestone) {
                if (strpos($milestone->created_by, 'u_') === 0) {
                    // The ID corresponds to a user
                    $creator = User::find(substr($milestone->created_by, 2)); // Remove the 'u_' prefix
                } elseif (strpos($milestone->created_by, 'c_') === 0) {
                    // The ID corresponds to a client
                    $creator = Client::find(substr($milestone->created_by, 2)); // Remove the 'c_' prefix
                }
                if ($creator !== null) {
                    $creator = $creator->first_name . ' ' . $creator->last_name;
                } else {
                    $creator = '-';
                }

                $statusBadge = '';

                if ($milestone->status == 'incomplete') {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('incomplete', 'Incomplete') . '</span>';
                } elseif ($milestone->status == 'complete') {
                    $statusBadge = '<span class="badge bg-success">' . get_label('complete', 'Complete') . '</span>';
                }
                $progress = '<div class="demo-vertical-spacing">
                <div class="progress">
                  <div class="progress-bar" role="progressbar" style="width: ' . $milestone->progress . '%" aria-valuenow="' . $milestone->progress . '" aria-valuemin="0" aria-valuemax="100">

                  </div>
                </div>
              </div> <h6 class="mt-2">' . $milestone->progress . '%</h6>';

                return [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'status' => $statusBadge,
                    'progress' => $progress,
                    'hourly' => format_currency($milestone->hourly),
                    'start_date' => format_date($milestone->start_date),
                    'end_date' => format_date($milestone->end_date),
                    'created_by' => $creator,
                    'description' => $milestone->description,
                    'created_at' => format_date($milestone->created_at, 'H:i:s'),
                    'updated_at' => format_date($milestone->updated_at, 'H:i:s'),
                ];
            });



        return response()->json([
            "rows" => $milestones->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieves a specific milestone by its ID
     * 
     * @param int $id The ID of the milestone to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the milestone data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When milestone is not found
     */
    public function get_milestone($id)
    {
        $ms = Milestone::findOrFail($id);
        return response()->json(['ms' => $ms]);
    }

    /**
     * Updates a milestone record in the database.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing milestone data
     * @return \Illuminate\Http\JsonResponse JSON response indicating success/failure
     *
     * Validates and updates the following milestone fields:
     * - title (required)
     * - status (required) 
     * - start_date (optional, must be before or equal to end_date)
     * - end_date (optional)
     * - hourly (required, must be numeric with optional decimal)
     * - progress (required)
     * - description (optional)
     * 
     * Returns JSON with:
     * - error: boolean indicating success/failure
     * - message: status message
     * - id: milestone ID (on success)
     * - type: 'milestone' (on success)
     * - parent_type: 'project' (on success)
     */
    public function update_milestone(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'status' => ['required'],
            'start_date' => ['nullable', 'before_or_equal:end_date'],
            'end_date' => ['nullable'],
            'hourly' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'progress' => ['required'],
            'description' => ['nullable'],
        ]);

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        $formFields['end_date'] = format_date($end_date, null, "Y-m-d");

        $ms = Milestone::findOrFail($request->id);

        if ($ms->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Milestone updated successfully.', 'id' => $ms->id, 'type' => 'milestone', 'parent_type' => 'project']);
        } else {
            return response()->json(['error' => true, 'message' => 'Milestone couldn\'t updated.']);
        }
    }
    /**
     * Delete a milestone by its ID.
     *
     * This method removes a milestone from the database using the DeletionService.
     * It first verifies the milestone exists, then deletes it and returns a JSON response
     * with the operation status and deleted milestone details.
     *
     * @param int $id The ID of the milestone to delete
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error: boolean indicating if operation failed
     *         - message: success message
     *         - id: deleted milestone ID
     *         - title: deleted milestone title
     *         - type: constant string 'milestone'
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When milestone is not found
     */
    public function delete_milestone($id)
    {
        $ms = Milestone::findOrFail($id);
        DeletionService::delete(Milestone::class, $id, 'Milestone');
        return response()->json(['error' => false, 'message' => 'Milestone deleted successfully.', 'id' => $id, 'title' => $ms->title, 'type' => 'milestone']);
    }
    /**
     * Delete multiple milestones based on provided IDs.
     *
     * This method handles bulk deletion of milestones. It validates the input array of IDs,
     * ensures each ID exists in the milestones table, and performs the deletion operation.
     * It keeps track of deleted milestone IDs and titles for the response.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing milestone IDs to delete
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: success message
     *         - id: array of deleted milestone IDs
     *         - titles: array of deleted milestone titles
     *         - type: string indicating the type of deleted resources
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When a milestone is not found
     */
    public function delete_multiple_milestones(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:milestones,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $ms = Milestone::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $ms->title;
            DeletionService::delete(Milestone::class, $id, 'Milestone');
        }

        return response()->json(['error' => false, 'message' => 'Milestone(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'milestone']);
    }
}

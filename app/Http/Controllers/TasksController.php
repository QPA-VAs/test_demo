<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use PDO;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Project;
use App\Models\Workspace;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Exception;
use Carbon\Carbon;

class TasksController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for TasksController.
     * Initializes middleware to set workspace and user context for all controller actions.
     * 
     * The middleware:
     * - Retrieves the current workspace from session
     * - Sets authenticated user
     * - Makes workspace and user available throughout the controller
     * 
     * @return void 
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
     * Display a listing of tasks.
     *
     * This method retrieves and displays tasks based on project ID or user permissions.
     * If a project ID is provided, it shows tasks for that specific project.
     * Otherwise, it shows all workspace tasks for admin/all-access users or user-specific tasks.
     *
     * @param string $id Optional project ID to filter tasks
     * @return \Illuminate\View\View Returns tasks view with project, tasks count, users, clients and projects data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project with given ID is not found
     */
    public function index($id = '')
    {
        $project = (object)[];
        if ($id) {
            $project = Project::findOrFail($id);
            $tasks = $project->tasks;
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks();
        }
        $tasks = $tasks->count();
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
        return view('tasks.tasks', ['project' => $project, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients, 'projects' => $projects]);
    }

    /**
     * Display the task creation form.
     * 
     * This method handles the display of the task creation form based on whether a specific project is selected.
     * It also implements a time-based restriction system that disables task creation between Sunday 11 PM and Monday 9 AM.
     *
     * @param string $id Optional project ID. If provided, loads specific project data and its users.
     *                   If not provided, loads all workspace projects and users.
     * 
     * @return \Illuminate\View\View Returns view 'tasks.create_task' with:
     *         - project: Selected project data or empty object
     *         - projects: List of all workspace projects (if no specific project selected)
     *         - users: List of users associated with project or workspace
     *         - currentDate: Current date in 'YYYY-MM-DD' format
     *         - loggedInUserId: ID of the authenticated user
     *         - isDisabled: Boolean indicating if task creation should be disabled based on time restrictions
     */
    public function create($id = '')
{
    $project = (object)[];
    $projects = [];
    $currentDate = Carbon::now()->toDateString(); // Get current date in 'YYYY-MM-DD' format

    // Check if project ID is provided, and retrieve project data accordingly
    if ($id) {
        $project = Project::find($id);
        $users = $project->users;
    } else {
        // If no specific project is being worked on, get all workspace projects and users
        $projects = $this->workspace->projects;
        $users = $this->workspace->users;
    }

    $loggedInUserId = auth()->id(); // Get the logged-in user ID

    // Get the current time
    $currentTime = Carbon::now();

    // Define the start and end times for disabling the button
    $sundayEnd = Carbon::now()->next(Carbon::SUNDAY)->setTime(23, 0, 0); // 11:00 PM on Sunday
    $mondayStart = $sundayEnd->copy()->addDay(); // 12:00 AM on Monday
    $mondayEnd = Carbon::now()->next(Carbon::MONDAY)->setTime(9, 0, 0); // 9:00 AM on Monday

    // Log the current time and defined times
    \Log::info('Current Time: ' . $currentTime);
    \Log::info('Sunday End: ' . $sundayEnd);
    \Log::info('Monday Start: ' . $mondayStart);
    \Log::info('Monday End: ' . $mondayEnd);

    // Determine if the button should be disabled
    $isDisabled = ($currentTime->greaterThanOrEqualTo($sundayEnd) && $currentTime->lessThan($mondayEnd));

    // Log the value of isDisabled
    \Log::info('Is Disabled: ' . ($isDisabled ? 'true' : 'false'));

    // Return the view with all necessary data
    return view('tasks.create_task', [
        'project' => $project,
        'projects' => $projects,
        'users' => $users,
        'currentDate' => $currentDate,  // Passing the current date to the view
        'loggedInUserId' => $loggedInUserId, // Passing the logged-in user ID to the view
        'isDisabled' => $isDisabled      // Pass whether the button should be disabled
    ]);
}


    /**
     * Store a newly created task in storage.
     *
     * This method handles the creation of a new task with the following steps:
     * 1. Validates the incoming request data
     * 2. Formats the time spent from minutes to HH:MM:SS format
     * 3. Formats the start date to Y-m-d format
     * 4. Updates the project's hourly budget if it exists
     * 5. Creates the task record
     * 6. Associates users with the task if provided
     *
     * @param  \Illuminate\Http\Request  $request Contains task data including:
     *         - title (required)
     *         - status_id (required)
     *         - start_date (required)
     *         - time_spent (required, in minutes)
     *         - description (nullable)
     *         - project (required)
     *         - user_id (optional, array of user IDs to associate with the task)
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error: boolean indicating if operation failed
     *         - id: ID of the newly created task
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'status_id' => ['required'],
            'start_date' => ['required'],
            'time_spent' => ['required'],
            'description' => 'nullable',
            'project' => ['required']
        ]);

        $project_id = $request->input('project');

        $start_date = $request->input('start_date');
        $time_spent = $request->input('time_spent');
        $hours = floor($time_spent / 60); // Get the number of hours
        $minutes = $time_spent % 60; // Get the remaining minutes

        // Format hours and minutes as HH:MM:SS
        $time_formatted = sprintf("%02d:%02d:00", $hours, $minutes);

        $formFields['time_spent'] = $time_formatted;
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = $this->user->id;

      // Retrieve the project object using the project ID
$project = Project::find($project_id);

$formFields['project_id'] = $project_id;

if ($project && !is_null($project->hourly)) {
    // Convert hourly (assumed to be in HH:MM:SS format) to total minutes
    $hourly_parts = explode(':', $project->hourly);
    $hourly_minutes = ($hourly_parts[0] * 60) + $hourly_parts[1];

    // Convert time_spent (HH:MM:SS) to total minutes
    $spent_parts = explode(':', $time_formatted);
    $spent_minutes = ($spent_parts[0] * 60) + $spent_parts[1];

    // Deduct time_spent from hourly (allow negative result)
    $new_hourly_minutes = $hourly_minutes - $spent_minutes;

    // Convert back to HH:MM:SS format (this can be negative if time spent exceeds hourly)
    $new_hours = floor(abs($new_hourly_minutes) / 60);
    $new_minutes = abs($new_hourly_minutes) % 60;
    $sign = $new_hourly_minutes < 0 ? '-' : ''; // Add minus sign if result is negative
    $project->hourly = sprintf("%s%02d:%02d:00", $sign, $new_hours, $new_minutes);

    // Save the updated project
    $project->save();
}



        $formFields['created_by'] = $this->user->id;

        $new_task = Task::create($formFields);

        $userIds = $request->input('user_id', []);
        if (!empty($userIds)) {
            // Detach any existing users, if you want to clear previous associations
            $new_task->users()->detach();
            // Attach the new users
            $new_task->users()->attach($userIds);
        }
//        $userIds = $request->input('user_id');
//
//        $new_task = Task::create($formFields);
//        $task_id = $new_task->id;
//        $task = Task::find($task_id);
//        $task->users()->attach($userIds);
//        Log::info('User IDs to attach:', (array) $userIds);
        Session::flash('message', 'Task created successfully.');
        return response()->json(['error' => false, 'id' => $new_task->id]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $task = Task::findOrFail($id);
        return view('tasks.task_information', ['task' => $task]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $task = Task::findOrFail($id);
        $project = $task->project;
        $users = $task->project->users;
        $task_users = $task->users;
        return view('tasks.update_task', ["project" => $project, "task" => $task, "users" => $users, "task_users" => $task_users]);
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
            'status_id' => ['required'],
            'start_date' => ['required'],
            'time_spent' => ['required'],
            'description' => 'nullable',
        ]);

        $start_date = $request->input('start_date');
        $time_spent = $request->input('time_spent');
        $hours = floor($time_spent / 60); // Get the number of hours
        $minutes = $time_spent % 60; // Get the remaining minutes

// Format hours and minutes as HH:MM:SS
        $time_formatted = sprintf("%02d:%02d:00", $hours, $minutes);

        $formFields['time_spent'] = $time_formatted;
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");


        $userIds = $request->input('user_id');

        $task = Task::findOrFail($id);
        $task->update($formFields);
        $task->users()->sync($userIds);

        Session::flash('message', 'Task updated successfully.');
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
        $task = Task::find($id);
        DeletionService::delete(Task::class, $id, 'Task');
        return response()->json(['error' => false, 'message' => 'Task deleted successfully.', 'id' => $id, 'title' => $task->title, 'parent_id' => $task->project_id, 'parent_type' => 'project']);
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:tasks,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedTasks = [];
        $deletedTaskTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $task = Task::find($id);
            if ($task) {
                $deletedTaskTitles[] = $task->title;
                DeletionService::delete(Task::class, $id, 'Task');
                $deletedTasks[] = $id;
                $parentIds[] = $task->project_id;
            }
        }

        return response()->json(['error' => false, 'message' => 'Task(s) deleted successfully.', 'id' => $deletedTasks, 'titles' => $deletedTaskTitles, 'parent_id' => $parentIds, 'parent_type' => 'project']);
    }


    /**
     * Retrieves and formats a list of tasks based on various filter criteria.
     *
     * This method handles task listing with multiple filter options including:
     * - Search by title
     * - Sort by any field
     * - Filter by status
     * - Filter by user
     * - Filter by client
     * - Filter by project
     * - Filter by date range
     * - Filter by time spent
     *
     * The method supports pagination and returns tasks with formatted data including:
     * - Task details with linked titles
     * - Project information with favorite status
     * - User avatars with profile links
     * - Client avatars with profile links
     * - Formatted dates and status badges
     *
     * @param string $id Optional parameter in format 'type_id' (e.g., 'project_1', 'user_1', 'client_1')
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *                                      - rows: Array of formatted task data
     *                                      - total: Total number of tasks matching criteria
     */
    public function list($id = '')
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";
        $project_id = (request('project_id')) ? request('project_id') : "";
        $start_date_from = (request('task_start_date_from')) ? request('task_start_date_from') : "";
        $time_spent = (request('time_spent')) ? request('time_spent') : "";
        $start_date_to = (request('task_start_date_to')) ? request('task_start_date_to') : "";
        $where = [];
        if ($status != '') {
            $where['status_id'] = $status;
        }
        if ($id) {
            $id = explode('_', $id);
            $belongs_to = $id[0];
            $belongs_to_id = $id[1];
            if ($belongs_to == 'project') {
                $belongs_to = Project::find($belongs_to_id);
            }
            if ($belongs_to == 'user') {
                $belongs_to = User::find($belongs_to_id);
            }
            if ($belongs_to == 'client') {
                $belongs_to = Client::find($belongs_to_id);
            }
            $tasks = $belongs_to->tasks();
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();
        }
        if ($user_id) {
            $user = User::find($user_id);
            $tasks = $user->tasks();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $tasks = $client->tasks();
        }
        if ($project_id) {
            $where['project_id'] = $project_id;
        }
        if ($start_date_from && $start_date_to) {
            $tasks->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($time_spent) {
            $where['time_spent'] = $time_spent;
        }
        if ($search) {
            $tasks = $tasks->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            });
        }
        $tasks->where($where);
        $totaltasks = $tasks->count();

        $tasks = $tasks->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($task) => [
                    'id' => $task->id,
                    'title' => "<a href='/tasks/information/" . $task->id . "' target='_blank'><strong>" . $task->title . "</strong></a>",
                    'project_id' => "<a href='/projects/information/" . $task->project->id . "' target='_blank'><strong>" . $task->project->title . "</strong></a> <a href='javascript:void(0);' class='mx-2'><i class='bx " . ($task->project->is_favorite ? 'bxs' : 'bx') . "-star favorite-icon text-warning' data-favorite=" . $task->project->is_favorite . " data-id=" . $task->project->id . " title='" . ($task->project->is_favorite ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')) . "'></i></a>",
                    'users' => $task->users,
                    'clients' => $task->project->clients,
                    'start_date' => format_date($task->start_date),
                    'time_spent' => $task->time_spent,
                    'status_id' => "<span class='badge bg-label-" . $task->status->color . " me-1'>" . $task->status->title . "</span>",
                    'created_at' => format_date($task->created_at,  'H:i:s'),
                    'updated_at' => format_date($task->updated_at, 'H:i:s'),
                ]
            );

        foreach ($tasks->items() as $task => $collection) {
            foreach ($collection['users'] as $i => $user) {
                $collection['users'][$i] = "<a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user['first_name'] . " " . $user['last_name'] . "'>
                <img src='" . ($user['photo'] ? asset('storage/' . $user['photo']) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                </li></a>";
            };
        }

        foreach ($tasks->items() as $task => $collection) {
            foreach ($collection['clients'] as $i => $client) {
                $collection['clients'][$i] = "<a href='/clients/profile/" . $client->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $client['first_name'] . " " . $client['last_name'] . "'>
                <img src='" . ($client['photo'] ? asset('storage/' . $client['photo']) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                </li></a>";
            };
        }

        return response()->json([
            "rows" => $tasks->items(),
            "total" => $totaltasks,
        ]);
    }

    /**
     * Display tasks in a board view using dragula interface.
     * 
     * This method retrieves tasks based on project ID and user permissions.
     * If a project ID is provided, it fetches tasks for that specific project.
     * Otherwise, it retrieves all tasks for the workspace.
     * 
     * @param string $id Optional project ID
     * @return \Illuminate\View\View Returns board view with project, tasks and total task count
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When project is not found
     */
    public function dragula($id = '')
    {
        $project = (object)[];
        if ($id) {
            $project = Project::findOrFail($id);
            // $tasks = $project->tasks;
            $tasks = isAdminOrHasAllDataAccess() ? $project->tasks : $this->user->project_tasks($id)->get();
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks()->get();
        }
        $total_tasks = $tasks->count();
        return view('tasks.board_view', ['project' => $project, 'tasks' => $tasks, 'total_tasks' => $total_tasks]);
    }

    /**
     * Update the status of a specific task.
     *
     * @param int $id The ID of the task to update
     * @param int $newStatus The ID of the new status to be set
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: string describing the result of operation
     *         - id: int task ID (only on success)
     *         - activity_message: string describing the status change (only on success)
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When task is not found
     */
    public function updateStatus($id, $newStatus)
    {
        $task = Task::findOrFail($id);
        $current_status = $task->status->title;
        $task->status_id = $newStatus;
        if ($task->save()) {
            $task->refresh();
            $new_status = $task->status->title;
            return response()->json(['error' => false, 'message' => 'Task status updated successfully.', 'id' => $id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' updated task status from ' . $current_status . ' to ' . $new_status]);
        } else {
            return response()->json(['error' => true, 'message' => 'Task status couldn\'t updated.']);
        }
    }

    /**
     * Duplicate a task record and its relationships.
     * 
     * This method creates a copy of an existing task record along with its related user associations.
     * It uses a general duplicateRecord helper function to handle the duplication process.
     *
     * @param int $id The ID of the task to duplicate
     * @return \Illuminate\Http\JsonResponse Returns JSON response containing:
     *                                      - error: boolean indicating if operation failed
     *                                      - message: string containing operation result message
     *                                      - id: int original task ID if successful
     * 
     * @throws \Exception When duplication process fails
     */
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users']; // Include related tables as needed

        // Use the general duplicateRecord function
        $duplicate = duplicateRecord(Task::class, $id, $relatedTables);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Task duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Task duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Task duplicated successfully.', 'id' => $id]);
    }

    /**
     * Upload media files for a specific task.
     *
     * This method handles the upload of media files associated with a task.
     * It validates the task ID, processes multiple files, sanitizes filenames,
     * and stores them in the 'task-media' collection.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing:
     *                                         - id: integer (task ID)
     *                                         - media_files: array of files
     *
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *                                      - error: boolean
     *                                      - message: string
     *                                      - id: array of media IDs (on success)
     *                                      - type: string 'media' (on success)
     *                                      - parent_type: string 'task' (on success)
     *
     * @throws \Exception When file upload fails
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function upload_media(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'integer|exists:tasks,id'
            ]);

            $mediaIds = [];

            if ($request->hasFile('media_files')) {
                $task = Task::find($validatedData['id']);
                $mediaFiles = $request->file('media_files');

                foreach ($mediaFiles as $mediaFile) {
                    $mediaItem = $task->addMedia($mediaFile)
                        ->sanitizingFileName(function ($fileName) use ($task) {
                            // Replace special characters and spaces with hyphens
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));

                            // Generate a unique identifier based on timestamp and random component
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);

                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('task-media');

                    $mediaIds[] = $mediaItem->id;
                }


                Session::flash('message', 'File(s) uploaded successfully.');
                return response()->json(['error' => false, 'message' => 'File(s) uploaded successfully.', 'id' => $mediaIds, 'type' => 'media', 'parent_type' => 'task']);
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
     * Retrieve and format media files associated with a specific task.
     * 
     * This method fetches media files attached to a task, applies search filters if provided,
     * and formats the media items for display in the frontend. It supports:
     * - Searching by ID, filename, or creation date
     * - Sorting by any field in ascending or descending order
     * - Special handling for image files with lightbox integration
     * - File download and delete actions
     * 
     * @param int $id The ID of the task to get media from
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of formatted media items with properties:
     *           - id: Media item ID
     *           - file: HTML markup for displaying/linking the file
     *           - file_name: Original filename
     *           - file_size: Formatted file size
     *           - created_at: Formatted creation timestamp
     *           - updated_at: Formatted update timestamp
     *           - actions: HTML markup for download/delete actions
     *         - total: Total count of media items
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When task is not found
     */
    public function get_media($id)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $task = Task::findOrFail($id);
        $media = $task->getMedia('task-media');

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
                ? asset('storage/task-media/' . $mediaItem->file_name)
                : $mediaItem->getFullUrl();


            $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);

            // Check if file extension corresponds to an image type
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
            $isImage = in_array(strtolower($fileExtension), $imageExtensions);

            if ($isImage) {
                $html = '<a href="' . $fileUrl . '" data-lightbox="task-media">';
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
                    '<a href="' . $fileUrl . '" title="' . get_label('download', 'Download') . '" download>' .
                        '<i class="bx bx-download bx-sm"></i>' .
                        '</a>' .
                        '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $mediaItem->id . '" data-type="task-media">' .
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
     * Delete a media item
     *
     * @param int $mediaId The ID of the media item to delete
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: status message
     *         - id: deleted media ID
     *         - title: deleted file name
     *         - parent_id: ID of parent model
     *         - type: type of deleted item ('media')
     *         - parent_type: type of parent model ('task')
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When media item not found
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

        return response()->json(['error' => false, 'message' => 'File deleted successfully.', 'id' => $mediaId, 'title' => $mediaItem->file_name, 'parent_id' => $mediaItem->model_id,  'type' => 'media', 'parent_type' => 'task']);
    }

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

        return response()->json(['error' => false, 'message' => 'Files(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'parent_id' => $parentIds, 'type' => 'media', 'parent_type' => 'task']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class WorkspacesController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for WorkspacesController
     * 
     * Initializes middleware that:
     * - Sets the current workspace based on workspace_id from session
     * - Sets authenticated user
     * - Makes workspace and user data available throughout the controller
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
     * Display a listing of all workspaces.
     * 
     * Retrieves all workspaces, users, and clients from the database 
     * and passes them to the workspaces view.
     *
     * @return \Illuminate\View\View Returns the workspaces view with workspaces, users and clients data
     */
    public function index()
    {
        $workspaces = Workspace::all();
        $users = User::all();
        $clients = Client::all();
        return view('workspaces.workspaces', compact('workspaces', 'users', 'clients'));
    }
    /**
     * Show the form for creating a new workspace.
     * 
     * Retrieves all users and clients from the database and passes them to the view
     * along with the authenticated user to populate form selection fields.
     * 
     * @return \Illuminate\View\View Returns the workspace creation form view with users, clients and auth user data
     */
    public function create()
    {

        $users = User::all();
        $clients = Client::all();
        $auth_user = $this->user;

        return view('workspaces.create_workspace', compact('users', 'clients', 'auth_user'));
    }
    /**
     * Store a newly created workspace in the database.
     *
     * This method handles the creation of a new workspace and associates it with users and clients.
     * The creator (authenticated user) is automatically added as a participant.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing workspace data
     * 
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if there was an error
     *         - id: integer ID of the newly created workspace
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * The request should contain:
     * - title: string (required)
     * - user_ids: array (optional) - IDs of users to be associated with workspace
     * - client_ids: array (optional) - IDs of clients to be associated with workspace
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required']

        ]);
        $formFields['user_id'] = $this->user->id;
        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];

        // Set creator as a participant automatically

        if (Auth::guard('client')->check() && !in_array($this->user->id, $clientIds)) {
            array_splice($clientIds, 0, 0, $this->user->id);
        } else if (Auth::guard('web')->check() && !in_array($this->user->id, $userIds)) {
            array_splice($userIds, 0, 0, $this->user->id);
        }

        $new_workspace = Workspace::create($formFields);
        $workspace_id = $new_workspace->id;
        $workspace = Workspace::find($workspace_id);
        $workspace->users()->attach($userIds);
        $workspace->clients()->attach($clientIds);

        Session::flash('message', 'Workspace created successfully.');
        return response()->json(['error' => false, 'id' => $workspace_id]);
    }
    /**
     * Retrieve and format a paginated list of workspaces with search and sorting capabilities.
     * 
     * This method handles listing workspaces with the following features:
     * - Searching by title or ID
     * - Sorting by specified column and order
     * - Filtering by user_id or client_id
     * - Access control based on user permissions
     * - Pagination with customizable limit
     * - Formatting workspace data including users and clients with avatar images
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of workspace data with formatted HTML for titles, users, and clients
     *         - total: Total count of workspaces matching the criteria
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When specified user_id or client_id not found
     * 
     * Query Parameters:
     * @param string|null $search Search term for filtering workspaces
     * @param string $sort Column name for sorting (default: "id")
     * @param string $order Sort direction (default: "DESC")
     * @param int|null $user_id Filter workspaces by user ID
     * @param int|null $client_id Filter workspaces by client ID
     * @param int $limit Number of items per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";

        $workspaces = isAdminOrHasAllDataAccess() ? $this->workspace : $this->user->workspaces();

        if ($user_id) {
            $user = User::find($user_id);
            $workspaces = $user->workspaces();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $workspaces = $client->workspaces();
        }
        $workspaces = $workspaces->when($search, function ($query) use ($search) {
            return $query->where('title', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        });
        $totalworkspaces = $workspaces->count();

        $workspaces = $workspaces->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($workspace) => [
                    'id' => $workspace->id,
                    'title' => '<a href="workspaces/switch/' . $workspace->id . '">' . $workspace->title . '</a>',
                    'users' => $workspace->users,
                    'clients' => $workspace->clients,
                    'created_at' => format_date($workspace->created_at,  'H:i:s'),
                    'updated_at' => format_date($workspace->updated_at, 'H:i:s'),
                ]
            );
        foreach ($workspaces->items() as $workspace => $collection) {
            foreach ($collection['clients'] as $i => $client) {
                $collection['clients'][$i] = "<a href='/clients/profile/" . $client->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $client['first_name'] . " " . $client['last_name'] . "'>
                <img src='" . ($client['photo'] ? asset('storage/' . $client['photo']) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                </li></a>";
            };
        }

        foreach ($workspaces->items() as $workspace => $collection) {
            foreach ($collection['users'] as $i => $user) {
                $collection['users'][$i] = "<a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user['first_name'] . " " . $user['last_name'] . "'>
                <img src='" . ($user['photo'] ? asset('storage/' . $user['photo']) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                </li></a>";
            };
        }

        return response()->json([
            "rows" => $workspaces->items(),
            "total" => $totalworkspaces,
        ]);
    }

    /**
     * Display the form for editing the specified workspace.
     *
     * This method retrieves a workspace by its ID along with all users and clients
     * to populate the edit form.
     *
     * @param int $id The ID of the workspace to edit
     * @return \Illuminate\View\View Returns the workspace edit view with workspace, users and clients data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When workspace is not found
     */
    public function edit($id)
    {
        $workspace = Workspace::findOrFail($id);
        $users = User::all();
        $clients = Client::all();
        return view('workspaces.update_workspace', compact('workspace', 'users', 'clients'));
    }

    /**
     * Update the specified workspace in storage.
     * 
     * This method updates a workspace with new information including title and participants.
     * It ensures that the workspace creator remains a participant after the update.
     * 
     * @param \Illuminate\Http\Request $request The HTTP request containing the workspace data
     * @param int $id The ID of the workspace to update
     * @return \Illuminate\Http\JsonResponse A JSON response indicating success or failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When workspace is not found
     * 
     * Request parameters:
     * - title: string (required) The new title for the workspace
     * - user_ids: array (optional) Array of user IDs to be workspace participants
     * - client_ids: array (optional) Array of client IDs to be workspace participants
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'title' => ['required']
        ]);

        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];
        $workspace = Workspace::findOrFail($id);

        // Set creator as a participant automatically
        if (User::where('id', $workspace->user_id)->exists() && !in_array($workspace->user_id, $userIds)) {
            array_splice($userIds, 0, 0, $workspace->user_id);
        } elseif (Client::where('id', $workspace->user_id)->exists() && !in_array($workspace->user_id, $clientIds)) {
            array_splice($clientIds, 0, 0, $workspace->user_id);
        }

        $workspace->update($formFields);
        $workspace->users()->sync($userIds);
        $workspace->clients()->sync($clientIds);

        Session::flash('message', 'Workspace updated successfully.');
        return response()->json(['error' => false, 'id' => $id]);
    }

    /**
     * Delete a workspace
     *
     * This method handles the deletion of a workspace by its ID. It prevents deletion
     * of the currently active workspace.
     *
     * @param int $id The ID of the workspace to delete
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success or error
     * 
     * @throws \Exception If deletion fails
     */
    public function destroy($id)
    {
        if ($this->workspace->id != $id) {
            $response = DeletionService::delete(Workspace::class, $id, 'Workspace');
            return $response;
        } else {
            return response()->json(['error' => true, 'message' => 'Current workspace couldn\'t deleted.']);
        }
    }

    /**
     * Delete multiple workspaces based on provided IDs.
     *
     * This method handles bulk deletion of workspaces. It validates the incoming IDs,
     * checks for their existence in the database, and performs the deletion operation
     * using the DeletionService.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing workspace IDs
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: success message
     *         - id: array of successfully deleted workspace IDs
     *         - titles: array of deleted workspace titles
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:workspaces,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedWorkspaces = [];
        $deletedWorkspaceTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $workspace = Workspace::find($id);
            if ($workspace) {
                $deletedWorkspaces[] = $id;
                $deletedWorkspaceTitles[] = $workspace->title;
                DeletionService::delete(Workspace::class, $id, 'Workspace');
            }
        }

        return response()->json(['error' => false, 'message' => 'Workspace(s) deleted successfully.', 'id' => $deletedWorkspaces, 'titles' => $deletedWorkspaceTitles]);
    }

    /**
     * Switch the current workspace context for the user's session.
     *
     * This method changes the active workspace by storing the workspace ID in the session.
     * If the workspace exists, it updates the session and returns to the previous page with a success message.
     * If the workspace is not found, it returns to the previous page with an error message.
     *
     * @param  int  $id  The ID of the workspace to switch to
     * @return \Illuminate\Http\RedirectResponse Returns redirect response with success/error message
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When workspace is not found
     */
    public function switch($id)
    {
        if (Workspace::findOrFail($id)) {
            session()->put('workspace_id', $id);
            return back()->with('message', 'Workspace changed successfully.');
        } else {
            return back()->with('error', 'Workspace not found.');
        }
    }

    /**
     * Removes the current user from the workspace.
     * 
     * This method handles the removal of a participant (user or client) from the current workspace.
     * If the participant is a client, they are detached from the workspace's clients relationship.
     * If the participant is a user, they are detached from the workspace's users relationship.
     * After removal, the session is updated with a new workspace ID (if available) and a success message is flashed.
     *
     * @return \Illuminate\Http\JsonResponse Returns a JSON response indicating success
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the workspace is not found
     */
    public function remove_participant()
    {
        $workspace = Workspace::findOrFail(session()->get('workspace_id'));
        if (isClient()) {
            $workspace->clients()->detach($this->user->id);
        } else {
            $workspace->users()->detach($this->user->id);
        }
        $workspace_id = isset($this->user->workspaces[0]['id']) && !empty($this->user->workspaces[0]['id']) ? $this->user->workspaces[0]['id'] : 0;
        $data = ['workspace_id' => $workspace_id];
        session()->put($data);
        Session::flash('message', 'Removed from workspace successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Duplicates a workspace and its related records.
     * 
     * This method creates a copy of an existing workspace along with its associated
     * users and clients relationships. It uses a general duplicateRecord helper function
     * to handle the duplication process.
     *
     * @param int $id The ID of the workspace to duplicate
     * @return \Illuminate\Http\JsonResponse Returns a JSON response containing:
     *         - error: boolean indicating if operation failed (true) or succeeded (false)
     *         - message: string describing the result of the operation
     *         - id: int the ID of the original workspace that was duplicated
     *         Additionally, if reload=true is passed in the request, sets a flash message
     */
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients']; // Include related tables as needed

        // Use the general duplicateRecord function
        $duplicate = duplicateRecord(Workspace::class, $id, $relatedTables);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Workspace duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Workspace duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Workspace duplicated successfully.', 'id' => $id]);
    }
}

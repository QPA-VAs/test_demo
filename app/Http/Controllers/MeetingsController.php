<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MeetingsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for MeetingsController.
     * 
     * Initializes middleware that:
     * - Fetches the current workspace from session
     * - Gets the authenticated user
     * These values are then available throughout the controller.
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
     * Display a list of meetings.
     * 
     * If the user is an admin or has all data access, returns all workspace meetings.
     * Otherwise, returns only the meetings associated with the current user.
     * 
     * @return \Illuminate\View\View Returns meetings view with meetings, users and clients data
     */
    public function index()
    {
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings : $this->user->meetings;
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        return view('meetings.meetings', compact('meetings', 'users', 'clients'));
    }

    /**
     * Display the meeting creation form.
     * 
     * Retrieves workspace users, clients and authenticated user information
     * to populate the meeting creation form.
     * 
     * @return \Illuminate\View\View Returns the create meeting view with users, clients and auth user data
     */
    public function create()
    {
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $auth_user = $this->user;

        return view('meetings.create_meeting', compact('users', 'clients', 'auth_user'));
    }

    /**
     * Store a newly created meeting in the database.
     *
     * This method validates the meeting form data, creates a new meeting record,
     * and associates the selected users and clients with the meeting.
     * The creator of the meeting is automatically added as a participant.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing meeting data
     * @return \Illuminate\Http\JsonResponse       JSON response with meeting ID or error status
     *
     * @throws \Illuminate\Validation\ValidationException
     *
     * Validation Rules:
     * - title: required
     * - start_date: required, must be before or equal to end_date
     * - end_date: required, must be after or equal to start_date
     * - start_time: required
     * - end_time: required
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'end_time' => ['required']

        ]);

        $start_date = $request->input('start_date');
        $start_time = $request->input('start_time');
        $end_date = $request->input('end_date');
        $end_time = $request->input('end_time');

        $formFields['start_date_time'] = date("Y-m-d H:i:s", strtotime($start_date . ' ' . $start_time));
        $formFields['end_date_time'] = date("Y-m-d H:i:s", strtotime($end_date . ' ' . $end_time));

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['user_id'] =  $this->user->id;
        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];

        // Set creator as a participant automatically

        if (Auth::guard('client')->check() && !in_array($this->user->id, $clientIds)) {
            array_splice($clientIds, 0, 0, $this->user->id);
        } else if (Auth::guard('web')->check() && !in_array($this->user->id, $userIds)) {
            array_splice($userIds, 0, 0, $this->user->id);
        }

        $new_meeting = Meeting::create($formFields);
        $meeting_id = $new_meeting->id;
        $meeting = Meeting::find($meeting_id);
        $meeting->users()->attach($userIds);
        $meeting->clients()->attach($clientIds);

        Session::flash('message', 'Meeting created successfully.');
        return response()->json(['error' => false, 'id' => $meeting_id]);
    }

    /**
     * List and filter meetings based on various parameters.
     * 
     * This method handles the retrieval and filtering of meetings with the following capabilities:
     * - Search by title or ID
     * - Filter by user
     * - Filter by client
     * - Filter by start date range
     * - Filter by end date range
     * - Filter by status (ongoing, yet_to_start, ended)
     * - Sort results by specified column and order
     * - Paginate results
     * 
     * The method also formats the output data including:
     * - Meeting details (ID, title, dates)
     * - Associated users with their avatars
     * - Associated clients with their avatars
     * - Dynamic status calculation
     * - Formatted dates
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of formatted meeting data
     *         - total: Total count of meetings matching the criteria
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings() : $this->user->meetings();
        if ($search) {
            $meetings = $meetings->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if ($user_id) {
            $user = User::find($user_id);
            $meetings = $user->meetings();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $meetings = $client->meetings();
        }
        if ($start_date_from && $start_date_to) {
            $start_date_from = $start_date_from . ' 00:00:00';
            $start_date_to = $start_date_to . ' 23:59:59';
            $meetings = $meetings->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $end_date_from = $end_date_from . ' 00:00:00';
            $end_date_to = $end_date_to . ' 23:59:59';
            $meetings  = $meetings->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
        }
        if ($status) {
            if ($status === 'ongoing') {
                $meetings = $meetings->where('start_date_time', '<=', Carbon::now(config('app.timezone')))
                    ->where('end_date_time', '>=', Carbon::now(config('app.timezone')));
            } elseif ($status === 'yet_to_start') {
                $meetings = $meetings->where('start_date_time', '>', Carbon::now(config('app.timezone')));
            } elseif ($status === 'ended') {
                $meetings = $meetings->where('end_date_time', '<', Carbon::now(config('app.timezone')));
            }
        }
        $totalmeetings = $meetings->count();
        $currentDateTime = Carbon::now(config('app.timezone'));
        $meetings = $meetings->orderBy($sort, $order)


            ->paginate(request("limit"))
            ->through(
                fn ($meeting) => [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start_date_time' => format_date($meeting->start_date_time, 'H:i:s', null, false),
                    'end_date_time' => format_date($meeting->end_date_time, 'H:i:s', null, false),
                    'users' => $meeting->users,
                    'clients' => $meeting->clients,
                    'status' => (($currentDateTime < \Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone'))) ? 'Will start in ' . $currentDateTime->diff(\Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone')))->format('%a days %H hours %I minutes %S seconds') : (($currentDateTime > \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone')) ? 'Ended before ' . \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone'))->diff($currentDateTime)->format('%a days %H hours %I minutes %S seconds') : 'Ongoing'))),
                    'created_at' => format_date($meeting->created_at,  'H:i:s'),
                    'updated_at' => format_date($meeting->updated_at, 'H:i:s'),
                ]
            );
        foreach ($meetings->items() as $meeting => $collection) {
            foreach ($collection['clients'] as $i => $client) {
                $collection['clients'][$i] = "<a href='/clients/profile/" . $client->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $client['first_name'] . " " . $client['last_name'] . "'>
                    <img src='" . ($client['photo'] ? asset('storage/' . $client['photo']) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' />
                </li></a>";
            };
        }

        foreach ($meetings->items() as $meeting => $collection) {
            foreach ($collection['users'] as $i => $user) {
                $collection['users'][$i] = "<a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user['first_name'] . " " . $user['last_name'] . "'>
                    <img src='" . ($user['photo'] ? asset('storage/' . $user['photo']) : asset('storage/photos/no-image.jpg')) . "' class='rounded-circle' />
                </li></a>";
            };
        }


        return response()->json([
            "rows" => $meetings->items(),
            "total" => $totalmeetings,
        ]);
    }

    /**
     * Show the form for editing the specified meeting.
     *
     * @param int $id The ID of the meeting to edit
     * @return \Illuminate\View\View Returns the meeting edit view with meeting, users and clients data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When meeting is not found
     */
    public function edit($id)
    {
        $meeting = Meeting::findOrFail($id);
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        return view('meetings.update_meeting', compact('meeting', 'users', 'clients'));
    }

    /**
     * Update the specified meeting in the database.
     * 
     * This method handles the update of an existing meeting record, including:
     * - Validation of required fields
     * - Processing of date and time inputs
     * - Managing meeting participants (users and clients)
     * - Ensuring the meeting creator remains a participant
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing meeting data
     * @param int $id The ID of the meeting to update
     * @return \Illuminate\Http\JsonResponse JSON response containing error status and meeting ID
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When meeting is not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required'],
            'start_time' => ['required'],
            'end_time' => ['required']
        ]);
        $start_date = $request->input('start_date');
        $start_time = $request->input('start_time');
        $end_date = $request->input('end_date');
        $end_time = $request->input('end_time');
        $formFields['start_date_time'] = date("Y-m-d H:i:s", strtotime($start_date . ' ' . $start_time));
        $formFields['end_date_time'] = date("Y-m-d H:i:s", strtotime($end_date . ' ' . $end_time));

        $userIds = $request->input('user_ids') ?? [];
        // dd($userIds);
        $clientIds = $request->input('client_ids') ?? [];
        $meeting = Meeting::findOrFail($id);
        // Set creator as a participant automatically

        if (User::where('id', $meeting->user_id)->exists() && !in_array($meeting->user_id, $userIds)) {
            array_splice($userIds, 0, 0, $meeting->user_id);
        } elseif (Client::where('id', $meeting->user_id)->exists() && !in_array($meeting->user_id, $clientIds)) {
            array_splice($clientIds, 0, 0, $meeting->user_id);
        }
        $meeting->update($formFields);
        $meeting->users()->sync($userIds);
        $meeting->clients()->sync($clientIds);

        Session::flash('message', 'Meeting updated successfully.');
        return response()->json(['error' => false, 'id' => $id]);
    }


    /**
     * Delete the specified meeting from storage.
     *
     * This method uses the DeletionService to handle the deletion process
     * of a meeting record.
     *
     * @param  int  $id The ID of the meeting to be deleted
     * @return \Illuminate\Http\JsonResponse Returns the response from DeletionService
     */
    public function destroy($id)
    {

        $response = DeletionService::delete(Meeting::class, $id, 'Meeting');
        return $response;
    }

    /**
     * Delete multiple meetings from the database.
     * 
     * This method handles bulk deletion of meetings by their IDs. It validates the input,
     * ensures all IDs exist in the database, and processes the deletion using the DeletionService.
     * It also keeps track of deleted meeting IDs and titles for the response.
     *
     * @param \Illuminate\Http\Request $request The request containing array of meeting IDs to delete
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: boolean indicating if any error occurred
     *         - message: success message
     *         - id: array of successfully deleted meeting IDs
     *         - titles: array of deleted meeting titles
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:meetings,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedMeetings = [];
        $deletedMeetingTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $meeting = Meeting::find($id);
            if ($meeting) {
                $deletedMeetings[] = $id;
                $deletedMeetingTitles[] = $meeting->title;
                DeletionService::delete(Meeting::class, $id, 'Meeting');
            }
        }

        return response()->json(['error' => false, 'message' => 'Meetings(s) deleted successfully.', 'id' => $deletedMeetings, 'titles' => $deletedMeetingTitles]);
    }

    /**
     * Handles joining a meeting based on the meeting ID.
     *
     * This method checks:
     * 1. If the meeting exists
     * 2. If the meeting time is valid (not too early/late)
     * 3. If the user is authorized to join the meeting
     *
     * If all conditions are met, renders the join meeting view with necessary parameters.
     *
     * @param int $id The meeting ID
     * @return \Illuminate\Http\Response|\Illuminate\View\View Returns either:
     *         - Redirect with error message if conditions not met
     *         - Join meeting view with meeting parameters if authorized
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When meeting not found
     */
    public function join($id)
    {

        $meeting = Meeting::findOrFail($id);
        $currentDateTime = Carbon::now(config('app.timezone'));
        if ($currentDateTime < $meeting->start_date_time) {
            return redirect('/meetings')->with('error', 'Meeting is yet to start');
        } elseif ($currentDateTime > $meeting->end_date_time) {
            return redirect('/meetings')->with('error', 'Meeting has been ended');
        } else {
            if ($meeting->users->contains($this->user->id) || isAdminOrHasAllDataAccess()) {
                $is_meeting_admin =  $this->user->id == $meeting['user_id'];
                $meeting_id = $meeting['id'];
                $room_name = $meeting['title'];
                $user_email =  $this->user->email;
                $user_display_name =  $this->user->first_name . ' ' .  $this->user->last_name;
                return view('meetings.join_meeting', compact('is_meeting_admin', 'meeting_id', 'room_name', 'user_email', 'user_display_name'));
            } else {
                return redirect('/meetings')->with('error', 'You are not authorized to join this meeting');
            }
        }
    }

    /**
     * Duplicates a meeting record along with its related data.
     * 
     * This method creates a copy of an existing meeting including relationships
     * with users and clients tables. It uses a general duplicateRecord helper function
     * to handle the duplication process.
     *
     * @param int $id The ID of the meeting to duplicate
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: string with operation result message
     *         - id: int original meeting ID if successful
     * @throws \Exception When duplication process fails
     */
    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients']; // Include related tables as needed

        // Use the general duplicateRecord function
        $duplicateMeeting = duplicateRecord(Meeting::class, $id, $relatedTables);
        if (!$duplicateMeeting) {
            return response()->json(['error' => true, 'message' => 'Meeting duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Meeting duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Meeting duplicated successfully.', 'id' => $id]);
    }
}

<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\Workspace;
use App\Models\TimeTracker;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TimeTrackerController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for TimeTrackerController
     * 
     * Initializes middleware that:
     * - Sets the current workspace based on session workspace_id
     * - Sets the authenticated user
     * 
     * The middleware runs before any controller action to ensure workspace 
     * and user context are available throughout the controller.
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
     * Display the timesheet page with tracked time entries
     * 
     * Shows tracked time entries based on user permissions:
     * - Admin/All Data Access users see all workspace timesheets
     * - Regular users see only their own timesheets
     *
     * @return \Illuminate\View\View Timesheet view with timesheet data and workspace users
     */
    public function index()
    {
        $timesheet = isAdminOrHasAllDataAccess() ? $this->workspace->timesheets : $this->user->timesheets;
        $users = $this->workspace->users;
        return view('time_trackers.timesheet', compact('timesheet', 'users'));
    }


    /**
     * Store a new time tracker record.
     * 
     * Creates a new time tracking entry in the database with the current workspace,
     * user ID and start time. Optionally includes a message if provided.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request object
     * @return \Illuminate\Http\JsonResponse       JSON response containing:
     *         - error (boolean) - False on success
     *         - message (string) - Success message
     *         - id (integer) - ID of the created record
     *         - activity_message (string) - Formatted activity message with user name and time
     *         - type (string) - Activity type identifier
     *         - operation (string) - Type of operation performed
     */
    public function store(Request $request)
    {

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['user_id'] =  $this->user->id;
        $formFields['start_date_time'] =  date('Y-m-d H:i:s');
        if ($request->has('message') && !empty($request->input('message'))) {
            $formFields['message'] = $request->input('message');
        }

        $new_record = TimeTracker::create($formFields);
        $recorded_id = $new_record->id;
        return response()->json(['error' => false, 'message' => 'Timer has been started successfully.', 'id' => $recorded_id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' started time tracker ' . format_date($formFields['start_date_time'],  'H:i:s'), 'type' => 'time_tracker', 'operation' => 'started']);
    }

    /**
     * Updates the time tracker record with end time and optional message.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing record_id and optional message
     * @return \Illuminate\Http\JsonResponse JSON response with operation status and activity details
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When record is not found
     */
    public function update(Request $request)
    {
        $formFields['end_date_time'] =  date('Y-m-d H:i:s');
        if ($request->has('message') && !empty($request->input('message'))) {
            $formFields['message'] = $request->input('message');
        }
        $time_tracker = TimeTracker::findOrFail($request->input('record_id'));
        $time_tracker->update($formFields);
        return response()->json(['error' => false, 'message' => 'Timer has been stopped successfully.', 'id' => $request->input('record_id'), 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' stopped time tracker ' . format_date($formFields['end_date_time'],  'H:i:s'), 'type' => 'time_tracker', 'operation' => 'stopped']);
    }

    /**
     * List and filter time tracking records.
     * 
     * This method retrieves time tracking records with related user information and applies various filters:
     * - Search by message content
     * - Filter by user ID
     * - Filter by start date range
     * - Filter by end date range
     * - Sort by specified column and order
     * 
     * Access control is implemented - non-admin users can only view their own records.
     * Duration is calculated and formatted to show days if spanning multiple days.
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *                                      - rows: Array of time tracking records with formatted data
     *                                      - total: Total count of records matching filters
     * 
     * Request parameters:
     * @param string|null $search Search term for message content
     * @param string $sort Column to sort by (default: 'id')
     * @param string $order Sort direction (default: 'DESC') 
     * @param int|null $user_id Filter by specific user
     * @param string|null $start_date_from Start date range begin
     * @param string|null $start_date_to Start date range end
     * @param string|null $end_date_from End date range begin
     * @param string|null $end_date_to End date range end
     * @param int $limit Number of records per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";

        $timesheet = TimeTracker::select(
            'time_trackers.*',
            'users.photo as user_photo',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name')
        )
            ->leftJoin('users', 'time_trackers.user_id', '=', 'users.id');

        $timesheet  = $timesheet->where('workspace_id', $this->workspace->id);

        if (!isAdminOrHasAllDataAccess()) {
            $timesheet  = $timesheet->where('user_id', $this->user->id);
        }
        if ($start_date_from && $start_date_to) {
            $start_date_from = $start_date_from . ' 00:00:00';
            $start_date_to = $start_date_to . ' 23:59:59';
            $timesheet = $timesheet->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $end_date_from = $end_date_from . ' 00:00:00';
            $end_date_to = $end_date_to . ' 23:59:59';
            $timesheet  = $timesheet->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
        }
        if ($user_id) {
            $timesheet  = $timesheet->where('user_id', $user_id);
        }
        if ($search) {
            $timesheet = $timesheet->where(function ($query) use ($search) {
                $query->where('message', 'like', '%' . $search . '%');
            });
        }

        $total = $timesheet->count();

        $timesheet = $timesheet->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($timesheet) {
                $formattedDuration = '-';
                if ($timesheet->end_date_time) {
                    $startDateTime = Carbon::parse($timesheet->start_date_time);
                    $endDateTime = Carbon::parse($timesheet->end_date_time);

                    // Calculate the difference between start and end date times
                    $duration = $endDateTime->diff($startDateTime);

                    // Check if the duration spans multiple days
                    if ($duration->days > 0) {
                        // Format with days if the duration spans multiple days
                        $formattedDuration = $duration->format('%D days %H:%I:%S');
                    } else {
                        // Format as usual without days if the duration is within the same day
                        $formattedDuration = $duration->format('%H:%I:%S');
                    }
                }

                return [
                    'id' => $timesheet->id,
                    'user_name' => $timesheet->user_name,
                    'photo' => "<div class='avatar avatar-md pull-up' title='" . $timesheet->user_name . "'>
                    <a href='/users/profile/" . $timesheet->user_id . "' target='_blank'>
                    <img src='" . ($timesheet->user_photo ? asset('storage/' . $timesheet->user_photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>
                    </a>
                    </div>",
                    'start_date_time' => format_date($timesheet->start_date_time,  'H:i:s'),
                    'end_date_time' => $timesheet->end_date_time ? format_date($timesheet->end_date_time,  'H:i:s') : '-',
                    'duration' => $formattedDuration,
                    'message' => $timesheet->message,
                    'created_at' => format_date($timesheet->created_at,  'H:i:s'),
                    'updated_at' => format_date($timesheet->updated_at, 'H:i:s'),
                ];
            });


        return response()->json([
            "rows" => $timesheet->items(),
            "total" => $total,
        ]);
    }


    public function destroy($id)
    {

        DeletionService::delete(TimeTracker::class, $id, 'Record');
        return response()->json(['error' => false, 'message' => 'Record deleted successfully.', 'id' => $id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' deleted time tracker record', 'type' => 'time_tracker']);
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:time_trackers,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deletedIds[] = $id;
            DeletionService::delete(TimeTracker::class, $id, 'Record');
        }

        return response()->json(['error' => false, 'message' => 'Record(s) deleted successfully.', 'id' => $deletedIds, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' deleted time tracker record', 'type' => 'time_tracker']);
    }
}

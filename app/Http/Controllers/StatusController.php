<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\Session;

class StatusController extends Controller
{
    /**
     * Display a listing of the statuses.
     *
     * @return \Illuminate\View\View Returns the status list view
     */
    public function index()
    {
        return view('status.list');
    }

    /**
     * Display the form for creating a new status.
     *
     * @return \Illuminate\View\View The view for creating a new status
     */
    public function create()
    {
        return view('status.create');
    }

    /**
     * Store a newly created status in the database.
     *
     * This method validates the incoming request data, generates a unique slug,
     * and creates a new status record in the database.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing status data
     * @return \Illuminate\Http\JsonResponse       JSON response indicating success or failure
     *                                            with message and status ID on success
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'color' => ['required']
        ]);
        $slug = generateUniqueSlug($request->title, Status::class);
        $formFields['slug'] = $slug;

        if ($status = Status::create($formFields)) {
            return response()->json(['error' => false, 'message' => 'Status created successfully.', 'id' => $status->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Status couldn\'t created.']);
        }
    }

    /**
     * Retrieve and format a paginated list of statuses
     * 
     * This method handles fetching statuses with optional search, sorting and pagination.
     * The results are formatted to include status details with styled color output.
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *      - rows: Array of status records with formatted fields
     *      - total: Total count of records matching criteria
     * 
     * Request parameters:
     * @param string|null $search Optional search term for filtering by title or ID
     * @param string $sort Field to sort by (defaults to "id")
     * @param string $order Sort direction ("ASC" or "DESC", defaults to "DESC")
     * @param int $limit Number of records per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = Status::orderBy($sort, $order);

        if ($search) {
            $status = $status->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $status->count();
        $status = $status
            ->paginate(request("limit"))
            ->through(
                fn ($status) => [
                    'id' => $status->id,
                    'title' => $status->title,
                    'color' => '<span class="badge bg-' . $status->color . '">' . $status->title . '</span>',
                    'created_at' => format_date($status->created_at,  'H:i:s'),
                    'updated_at' => format_date($status->updated_at, 'H:i:s'),
                ]
            );


        return response()->json([
            "rows" => $status->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve a specific status by ID.
     * 
     * @param int $id The ID of the status to retrieve
     * @return \Illuminate\Http\JsonResponse Returns JSON response containing the status object
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If status is not found
     */
    public function get($id)
    {
        $status = Status::findOrFail($id);
        return response()->json(['status' => $status]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update an existing status in the database.
     *
     * @param \Illuminate\Http\Request $request Contains the request data including:
     *                                         - id: The ID of the status to update
     *                                         - title: The new title for the status
     *                                         - color: The new color for the status
     * 
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *                                      - error: Boolean indicating if operation failed
     *                                      - message: Success/failure message
     *                                      - id: ID of updated status (on success only)
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When status with given ID is not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'title' => ['required'],
            'color' => ['required']
        ]);
        $slug = generateUniqueSlug($request->title, Status::class, $request->id);
        $formFields['slug'] = $slug;
        $status = Status::findOrFail($request->id);

        if ($status->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Status updated successfully.', 'id' => $status->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Status couldn\'t updated.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $status = Status::findOrFail($id);
        $status->projects()->update(['status_id' => 0]);
        $status->tasks()->update(['status_id' => 0]);
        $response = DeletionService::delete(Status::class, $id, 'Status');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:statuses,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $status = Status::findOrFail($id);
            $status->projects()->update(['status_id' => 0]);
            $status->tasks()->update(['status_id' => 0]);
            $deletedIds[] = $id;
            $deletedTitles[] = $status->title;
            DeletionService::delete(Status::class, $id, 'Status');
        }

        return response()->json(['error' => false, 'message' => 'Status(es) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

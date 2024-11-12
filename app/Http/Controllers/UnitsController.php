<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Unit;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;

class UnitsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for UnitsController.
     * 
     * Initializes middleware that:
     * - Retrieves the current workspace from session
     * - Gets the authenticated user
     * 
     * Sets class properties:
     * @property Workspace $workspace Current workspace instance
     * @property User $user Authenticated user instance
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
     * Display a list of units in the workspace.
     *
     * This method retrieves the count of all units associated with the current workspace
     * and returns a view with the units count data.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request
     * @return \Illuminate\View\View Returns a view with the units count
     */
    public function index(Request $request)
    {
        $units = $this->workspace->units();
        $units = $units->count();
        return view('units.list', ['units' => $units]);
    }

    /**
     * Store a newly created unit in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * Validates and stores a new unit with the following fields:
     * - title (required, unique)
     * - description (optional)
     * - workspace_id (automatically set from current workspace)
     * 
     * Returns JSON response with:
     * - On success: error=false, success message, and created unit ID
     * - On failure: error=true and error message
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:units,title',
            'description' => 'nullable',
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        if ($res = Unit::create($formFields)) {
            Session::flash('message', 'Unit created successfully.');
            return response()->json(['error' => false, 'message' => 'Unit created successfully.', 'id' => $res->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Unit couldn\'t created.']);
        }
    }

    /**
     * Retrieves and returns a paginated list of units with optional search and sorting.
     * 
     * This method handles the following functionalities:
     * - Search filtering based on title, description, or ID
     * - Sorting by any column (defaults to ID in descending order)
     * - Pagination with custom limit
     * - Data transformation for consistent response format
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of unit records with formatted timestamps
     *         - total: Total count of units (before pagination)
     *
     * Query Parameters:
     * @param string|null $search Optional search term to filter units
     * @param string $sort Column name to sort by (default: 'id')
     * @param string $order Sort direction ('ASC' or 'DESC', default: 'DESC')
     * @param int $limit Number of items per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $units = $this->workspace->units();
        if ($search) {
            $units = $units->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $units->count();
        $units = $units->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($unit) => [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'description' => $unit->description,
                    'created_at' => format_date($unit->created_at,  'H:i:s'),
                    'updated_at' => format_date($unit->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $units->items(),
            "total" => $total,
        ]);
    }



    /**
     * Retrieve a specific unit by its ID.
     *
     * @param int $id The ID of the unit to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the unit data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When unit is not found
     */
    public function get($id)
    {
        $unit = Unit::findOrFail($id);
        return response()->json(['unit' => $unit]);
    }

    /**
     * Updates an existing unit with the provided data.
     *
     * @param  \Illuminate\Http\Request  $request Contains the request data including:
     *         - title: Required, unique unit title
     *         - description: Optional unit description
     *         - id: ID of the unit to update
     * 
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: Boolean indicating if operation failed
     *         - message: Success/error message
     *         - id: ID of updated unit (on success)
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When unit not found
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:units,title,' . $request->id,
            'description' => 'nullable',
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        $unit = Unit::findOrFail($request->id);

        if ($unit->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Unit updated successfully.', 'id' => $unit->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Unit couldn\'t updated.']);
        }
    }

    public function destroy($id)
    {
        $unit = Unit::findOrFail($id);
        DB::table('estimates_invoice_item')
            ->where('unit_id', $unit->id)
            ->update(['unit_id' => null]);
        DB::table('items')
            ->where('unit_id', $unit->id)
            ->update(['unit_id' => null]);
        $response = DeletionService::delete(Unit::class, $id, 'Unit');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:units,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $unit = Unit::findOrFail($id);
            DB::table('estimates_invoice_item')
                ->where('unit_id', $unit->id)
                ->update(['unit_id' => null]);
            DB::table('items')
                ->where('unit_id', $unit->id)
                ->update(['unit_id' => null]);
            $deletedIds[] = $id;
            $deletedTitles[] = $unit->title;
            DeletionService::delete(Unit::class, $id, 'Unit');
        }

        return response()->json(['error' => false, 'message' => 'Unit(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

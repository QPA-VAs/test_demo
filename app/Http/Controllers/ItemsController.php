<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Item;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;

/**
 * Controller class for managing item-related operations.
 * 
 * This controller handles CRUD operations and other functionalities
 * related to items in the Taskify application.
 * 
 * @package App\Http\Controllers
 */
class ItemsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for ItemsController.
     * Applies middleware to handle workspace and user authentication.
     * 
     * This constructor sets up middleware that:
     * - Retrieves the current workspace from session
     * - Gets the authenticated user
     * - Makes these values available throughout the controller
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
     * Display a listing of items.
     * 
     * This method retrieves the count of items and units from the current workspace
     * and returns them to the items list view.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request
     * @return \Illuminate\View\View Returns view with items count and units data
     */
    public function index(Request $request)
    {
        $items = $this->workspace->items();
        $items = $items->count();
        $units = $this->workspace->units;
        return view('items.list', ['items' => $items, 'units' => $units]);
    }

    /**
     * Store a newly created item in storage.
     *
     * This method validates the incoming request data and creates a new item
     * in the database with the given workspace ID.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing item data
     * 
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - On success: error => false, message => success message, id => new item ID
     *         - On failure: error => true, message => error message
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:items,title',
            'price' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'unit_id' => 'nullable',
            'description' => 'nullable',
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        if ($res = Item::create($formFields)) {
            Session::flash('message', 'Item created successfully.');
            return response()->json(['error' => false, 'message' => 'Item created successfully.', 'id' => $res->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Item couldn\'t created.']);
        }
    }

    /**
     * Retrieves a paginated list of items with optional filtering and sorting.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of items with formatted data
     *         - total: Total number of items matching the criteria
     *
     * Query parameters:
     * @param string|null $search Search term to filter items by title, description, price, unit_id or id
     * @param string $sort Field to sort by (defaults to "id")
     * @param string $order Sort direction ("ASC" or "DESC", defaults to "DESC")
     * @param int|null $unit_id Filter items by specific unit_id
     * @param int $limit Number of items per page
     *
     * Each item in response contains:
     * - id: Item ID
     * - unit_id: Unit ID
     * - unit: Unit title
     * - title: Item title
     * - price: Formatted price
     * - description: Item description
     * - created_at: Formatted creation timestamp
     * - updated_at: Formatted update timestamp
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $unit_id = (request('unit_id')) ? request('unit_id') : "";
        $where = ['items.workspace_id' => $this->workspace->id];
        if ($unit_id != '') {
            $where['unit_id'] = $unit_id;
        }
        $items = Item::select(
            'items.*',
            'units.title as unit'
        )
            ->leftJoin('units', 'items.unit_id', '=', 'units.id');
        if ($search) {
            $items = $items->where(function ($query) use ($search) {
                $query->where('items.title', 'like', '%' . $search . '%')
                    ->orWhere('items.description', 'like', '%' . $search . '%')
                    ->orWhere('price', 'like', '%' . $search . '%')
                    ->orWhere('unit_id', 'like', '%' . $search . '%')                    
                    ->orWhere('items.id', 'like', '%' . $search . '%');
            });
        }
        $items->where($where);
        $total = $items->count();
        $items = $items->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($item) => [
                    'id' => $item->id,
                    'unit_id' => $item->unit_id,
                    'unit' => $item->unit,
                    'title' => $item->title,
                    'price' => format_currency($item->price),
                    'description' => $item->description,
                    'created_at' => format_date($item->created_at,  'H:i:s'),
                    'updated_at' => format_date($item->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $items->items(),
            "total" => $total,
        ]);
    }



    /**
     * Retrieve a specific item by its ID.
     *
     * @param int $id The ID of the item to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the item
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When item is not found
     */
    public function get($id)
    {
        $item = Item::findOrFail($id);
        return response()->json(['item' => $item]);
    }

    /**
     * Update the specified item in the database.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing item data
     * @return \Illuminate\Http\JsonResponse
     * 
     * The method validates and updates an existing item with the following fields:
     * - title (required, must be unique)
     * - price (required, must be a valid decimal number)
     * - unit_id (optional)
     * - description (optional)
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When item is not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:items,title,' . $request->id,
            'price' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'unit_id' => 'nullable',
            'description' => 'nullable',
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        $item = Item::findOrFail($request->id);

        if ($item->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Item updated successfully.', 'id' => $item->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Item couldn\'t updated.']);
        }
    }

    /**
     * Delete a specified item from storage.
     *
     * @param  int  $id
     * The ID of the item to delete
     * 
     * @return \Illuminate\Http\Response
     * Returns the response from DeletionService containing status and message
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Item::class, $id, 'Item');
        return $response;
    }
    /**
     * Delete multiple items from the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * 
     * Expects a request with:
     * - ids: array of item IDs to be deleted
     * 
     * Returns JSON response with:
     * - error: boolean indicating operation success
     * - message: success message
     * - id: array of deleted item IDs
     * - titles: array of deleted item titles
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:items,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $unit = Item::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $unit->title;
            DeletionService::delete(Item::class, $id, 'Item');
        }

        return response()->json(['error' => false, 'message' => 'Item(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

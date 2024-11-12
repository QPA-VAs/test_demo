<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Workspace;
use App\Models\ExpenseType;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

/**
 * Controller for managing expenses and expense types in the workspace.
 * 
 * This controller handles CRUD operations for expenses and expense types, including:
 * - Listing expenses and expense types with filtering and pagination
 * - Creating new expenses and expense types
 * - Updating existing expenses and expense types 
 * - Deleting single or multiple expenses and expense types
 * - Duplicating expenses
 *
 * @property Workspace $workspace Current workspace instance
 * @property User $user Currently authenticated user
 *
 * @method index() Display the expenses listing page
 * @method expense_types() Display the expense types listing page
 * @method store() Create a new expense record
 * @method store_expense_type() Create a new expense type record
 * @method list() Get paginated list of expenses with filters
 * @method expense_types_list() Get paginated list of expense types with filters
 * @method get() Get a specific expense record
 * @method get_expense_type() Get a specific expense type record
 * @method update() Update an existing expense record
 * @method update_expense_type() Update an existing expense type record
 * @method destroy() Delete a specific expense record
 * @method delete_expense_type() Delete a specific expense type record
 * @method destroy_multiple() Delete multiple expense records
 * @method delete_multiple_expense_type() Delete multiple expense type records
 * @method duplicate() Create a duplicate of an existing expense record
 *
 * @package App\Http\Controllers
 */
class ExpensesController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Create a new ExpensesController instance.
     * 
     * This constructor applies middleware to initialize workspace and user data
     * for the entire controller. It fetches the current workspace from session
     * and gets the authenticated user information.
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
     * Display a listing of expenses.
     *
     * This method retrieves and counts all expenses associated with the current workspace,
     * along with expense types and users, and returns them to the expenses list view.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request
     * @return \Illuminate\View\View Returns a view with expenses count, expense types and users data
     */
    public function index(Request $request)
    {
        $expenses = $this->workspace->expenses();
        $expenses = $expenses->count();
        $expense_types = $this->workspace->expense_types;
        $users = $this->workspace->users;
        return view('expenses.list', ['expenses' => $expenses, 'expense_types' => $expense_types, 'users' => $users]);
    }
    /**
     * Display expense types and their count for the current workspace.
     *
     * @param \Illuminate\Http\Request $request The HTTP request object
     * @return \Illuminate\View\View Returns view with expense types count
     */
    public function expense_types(Request $request)
    {
        $expense_types = $this->workspace->expense_types();
        $expense_types = $expense_types->count();
        return view('expenses.expense_types', ['expense_types' => $expense_types]);
    }

    /**
     * Store a new expense in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     * 
     * Validates and stores new expense with the following required fields:
     * - title (unique)
     * - expense_type_id
     * - user_id 
     * - amount (numeric with optional decimals)
     * - expense_date
     * - note (optional)
     * 
     * Automatically sets:
     * - workspace_id from current workspace
     * - created_by with prefix 'c_' for clients or 'u_' for users
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:expenses,title', // Validate the title
            'expense_type_id' => 'required',
            'user_id' => 'required',
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'expense_date' => 'required',
            'note' => 'nullable'
        ]);
        $expense_date = $request->input('expense_date');
        $formFields['expense_date'] = format_date($expense_date, null, "Y-m-d");
        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;

        if ($exp = Expense::create($formFields)) {
            return response()->json(['error' => false, 'message' => 'Expense created successfully.', 'id' => $exp->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Expense couldn\'t created.']);
        }
    }

    /**
     * Store a newly created expense type in the database.
     * 
     * This method validates and stores a new expense type entry with the provided title and description.
     * The expense type is associated with the current workspace.
     *
     * @param  \Illuminate\Http\Request  $request Contains the form data for expense type creation
     * @return \Illuminate\Http\JsonResponse Returns a JSON response indicating success or failure
     *                                      with appropriate message and created expense type details on success
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store_expense_type(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:expense_types,title', // Validate the type
            'description' => 'nullable'
        ]);
        $formFields['workspace_id'] = $this->workspace->id;

        if ($et = ExpenseType::create($formFields)) {
            Session::flash('message', 'Expense type created successfully.');
            return response()->json(['error' => false, 'message' => 'Expense type created successfully.', 'id' => $et->id, 'title' => $et->type, 'type' => 'expense_type']);
        } else {
            return response()->json(['error' => true, 'message' => 'Expense type couldn\'t created.']);
        }
    }

    /**
     * Lists expenses with filtering, sorting and pagination capabilities.
     * 
     * This method retrieves expenses from the database based on various search criteria and filters:
     * - Search by title, amount, note or ID
     * - Filter by expense type
     * - Filter by user
     * - Filter by date range
     * - Sort by any field in ascending or descending order
     * 
     * The results are joined with users and expense types tables to include additional information.
     * Access control is implemented based on user roles:
     * - Admins and users with all data access can see all expenses
     * - Regular users can only see expenses they created or are assigned to
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of expense records with formatted data
     *         - total: Total count of matching records
     * 
     * Expected query parameters:
     * @param string|null $search Search term for filtering expenses
     * @param string $sort Field name to sort by (defaults to "id")
     * @param string $order Sort direction - "ASC" or "DESC" (defaults to "DESC")
     * @param int|null $type_id Filter by expense type ID
     * @param int|null $user_id Filter by user ID
     * @param string|null $date_from Start date for filtering
     * @param string|null $date_to End date for filtering
     * @param int $limit Number of records per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $type_id = (request('type_id')) ? request('type_id') : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $exp_date_from = (request('date_from')) ? request('date_from') : "";
        $exp_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['expenses.workspace_id' => $this->workspace->id];

        $expenses = Expense::select(
            'expenses.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'expense_types.title as expense_type'
        )
            ->leftJoin('users', 'expenses.user_id', '=', 'users.id')
            ->leftJoin('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id');


        if (!isAdminOrHasAllDataAccess()) {
            $expenses = $expenses->where(function ($query) {
                $query->where('expenses.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('expenses.user_id', $this->user->id);
            });
        }
        if ($type_id) {
            $where['expense_type_id'] = $type_id;
        }
        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        if ($exp_date_from && $exp_date_to) {
            $expenses = $expenses->whereBetween('expenses.expense_date', [$exp_date_from, $exp_date_to]);
        }
        if ($search) {
            $expenses = $expenses->where(function ($query) use ($search) {
                $query->where('expenses.title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('expenses.note', 'like', '%' . $search . '%')
                    ->orWhere('expenses.id', 'like', '%' . $search . '%');
            });
        }

        $expenses->where($where);
        $total = $expenses->count();

        $expenses = $expenses->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($expense) {
                if (strpos($expense->created_by, 'u_') === 0) {
                    // The ID corresponds to a user
                    $creator = User::find(substr($expense->created_by, 2)); // Remove the 'u_' prefix
                } elseif (strpos($expense->created_by, 'c_') === 0) {
                    // The ID corresponds to a client
                    $creator = Client::find(substr($expense->created_by, 2)); // Remove the 'c_' prefix                    
                }
                if ($creator !== null) {
                    $creator = $creator->first_name . ' ' . $creator->last_name;
                } else {
                    $creator = '-';
                }
                return [
                    'id' => $expense->id,
                    'user_id' => $expense->user_id,
                    'user' => $expense->user_name,
                    'title' => $expense->title,
                    'expense_type_id' => $expense->expense_type_id,
                    'expense_type' => $expense->expense_type,
                    'amount' => format_currency($expense->amount),
                    'expense_date' => format_date($expense->expense_date),
                    'note' => $expense->note,
                    'created_by' => $creator,
                    'created_at' => format_date($expense->created_at,  'H:i:s'),
                    'updated_at' => format_date($expense->updated_at, 'H:i:s'),
                ];
            });


        return response()->json([
            "rows" => $expenses->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve and filter a paginated list of expense types.
     * 
     * This method handles the retrieval of expense types with optional search, sorting and pagination.
     * The results are formatted and returned as JSON response.
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *                                      - rows: Array of expense types with formatted data
     *                                      - total: Total count of expense types before pagination
     *
     * Request parameters:
     * @param string|null $search Optional search term to filter expense types
     * @param string $sort Field to sort by (defaults to "id")
     * @param string $order Sort direction ("ASC" or "DESC", defaults to "DESC")
     * @param int $limit Number of items per page
     *
     * Each expense type in response contains:
     * - id
     * - title
     * - description
     * - created_at (formatted time)
     * - updated_at (formatted time)
     */
    public function expense_types_list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $expense_types = $this->workspace->expense_types();
        if ($search) {
            $expense_types = $expense_types->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $expense_types->count();
        $expense_types = $expense_types->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($expense_types) => [
                    'id' => $expense_types->id,
                    'title' => $expense_types->title,
                    'description' => $expense_types->description,
                    'created_at' => format_date($expense_types->created_at,  'H:i:s'),
                    'updated_at' => format_date($expense_types->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $expense_types->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve a specific expense record by ID.
     *
     * @param int $id The ID of the expense to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the expense data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When expense is not found
     */
    public function get($id)
    {
        $exp = Expense::findOrFail($id);
        return response()->json(['exp' => $exp]);
    }

    /**
     * Retrieves a specific expense type by ID
     *
     * @param int $id The ID of the expense type to retrieve
     * @return \Illuminate\Http\JsonResponse Returns JSON response containing the expense type data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When expense type is not found
     */
    public function get_expense_type($id)
    {
        $et = ExpenseType::findOrFail($id);
        return response()->json(['et' => $et]);
    }

    /**
     * Update the specified expense in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     *
     * @apiParam {integer} id The ID of the expense
     * @apiParam {string} title The title of the expense (must be unique)
     * @apiParam {integer} expense_type_id The ID of the expense type
     * @apiParam {integer} user_id The ID of the user
     * @apiParam {numeric} amount The expense amount (must be a valid number)
     * @apiParam {string} expense_date The date of the expense
     * @apiParam {string|null} note Optional note for the expense
     *
     * @apiSuccess {boolean} error False if update was successful
     * @apiSuccess {string} message Success message
     * @apiSuccess {integer} id The ID of the updated expense
     *
     * @apiError {boolean} error True if update failed
     * @apiError {string} message Error message
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'id' => 'required',
            'title' => 'required|unique:expenses,title,' . $request->id,
            'expense_type_id' => 'required',
            'user_id' => 'required',
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'expense_date' => 'required',
            'note' => 'nullable'
        ]);
        $expense_date = $request->input('expense_date');
        $formFields['expense_date'] = format_date($expense_date, null, "Y-m-d");

        $exp = Expense::findOrFail($request->id);

        if ($exp->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Expense updated successfully.', 'id' => $exp->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Expense couldn\'t updated.']);
        }
    }

    /**
     * Updates an existing expense type in the database.
     *
     * @param  \Illuminate\Http\Request  $request Contains the request data
     *      - id: The ID of the expense type to update
     *      - title: The new title for the expense type (must be unique)
     *      - description: Optional description for the expense type
     * 
     * @return \Illuminate\Http\JsonResponse
     *      - On success: JSON with error=false, success message, ID and type
     *      - On failure: JSON with error=true and failure message
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When expense type with given ID is not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function update_expense_type(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'title' => 'required|unique:expense_types,title,' . $request->id,
            'description' => 'nullable',
        ]);
        $et = ExpenseType::findOrFail($request->id);

        if ($et->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Expense type updated successfully.', 'id' => $et->id, 'type' => 'expense_type']);
        } else {
            return response()->json(['error' => true, 'message' => 'Expense type couldn\'t updated.']);
        }
    }

    /**
     * Delete an expense record.
     *
     * @param int $id The ID of the expense to delete
     * @return \Illuminate\Http\JsonResponse Response containing:
     *         - error: boolean indicating if there was an error
     *         - message: success message
     *         - id: ID of the deleted expense
     *         - title: title of the deleted expense
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When expense not found
     */
    public function destroy($id)
    {
        $exp = Expense::findOrFail($id);
        DeletionService::delete(Expense::class, $id, 'Expense');
        return response()->json(['error' => false, 'message' => 'Expense deleted successfully.', 'id' => $id, 'title' => $exp->title]);
    }

    /**
     * Deletes an expense type and updates associated expenses
     * 
     * @param int $id The ID of the expense type to delete
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error: false on success
     *         - message: Success message
     *         - id: ID of deleted expense type
     *         - title: Title of deleted expense type
     *         - type: Type identifier ('expense_type')
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When expense type not found
     */
    public function delete_expense_type($id)
    {
        $et = ExpenseType::findOrFail($id);
        $et->expenses()->update(['expense_type_id' => 0]);
        DeletionService::delete(ExpenseType::class, $id, 'Expense type');
        return response()->json(['error' => false, 'message' => 'Expense type deleted successfully.', 'id' => $id, 'title' => $et->title, 'type' => 'expense_type']);
    }

    /**
     * Delete multiple expenses based on provided IDs.
     *
     * This method handles the bulk deletion of expenses. It validates the input array
     * of IDs, ensures each ID exists in the expenses table, and performs the deletion
     * through the DeletionService.
     *
     * @param \Illuminate\Http\Request $request The request containing array of expense IDs to delete
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error: boolean indicating if operation failed
     *         - message: success/error message
     *         - id: array of deleted expense IDs
     *         - titles: array of deleted expense titles
     *         - type: string indicating the type of deleted resources
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When an expense ID doesn't exist
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:expenses,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $exp = Expense::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $exp->title;
            DeletionService::delete(Expense::class, $id, 'Expense');
        }

        return response()->json(['error' => false, 'message' => 'Expense(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'expense']);
    }

    /**
     * Delete multiple expense types and update their associated expenses.
     *
     * This method handles the bulk deletion of expense types. For each deleted expense type,
     * its associated expenses are updated to have an expense_type_id of 0.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing the IDs to delete
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - error: boolean indicating if operation failed
     *         - message: success/error message
     *         - id: array of deleted expense type IDs
     *         - titles: array of deleted expense type titles
     *         - type: string identifier 'expense_type'
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When an expense type is not found
     */
    public function delete_multiple_expense_type(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:expense_types,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $et = ExpenseType::findOrFail($id);
            if ($et) {
                $deletedIds[] = $id;
                $deletedTitles[] = $et->title;
                $et->expenses()->update(['expense_type_id' => 0]);
                DeletionService::delete(ExpenseType::class, $id, 'Expense type');
            }
        }

        return response()->json(['error' => false, 'message' => 'Expense type(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'type' => 'expense_type']);
    }
    /**
     * Duplicates an existing expense record.
     *
     * Uses the general duplicateRecord function to create a copy of the specified expense.
     * Handles both AJAX and regular requests with appropriate responses.
     *
     * @param int $id The ID of the expense to duplicate
     * @return \Illuminate\Http\JsonResponse Returns JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: success/failure message
     *         - id: original expense ID
     * 
     * @throws \Exception If duplication process fails
     */
    public function duplicate($id)
    {
        // Use the general duplicateRecord function
        $duplicated = duplicateRecord(Expense::class, $id);
        if (!$duplicated) {
            return response()->json(['error' => true, 'message' => 'Expense duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Expense duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Expense duplicated successfully.', 'id' => $id]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Allowance;
use App\Models\Workspace;

use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

/* The `AllowancesController` class in PHP manages CRUD operations for allowances within a workspace,
including creation, listing, updating, and deletion of allowances. */
class AllowancesController extends Controller
{
    /* The lines `protected ;` and `protected ;` are declaring two protected properties
    within the `AllowancesController` class in PHP. */
    protected $workspace;
    protected $user;
    /**
     * The constructor function initializes session data for the workspace and authenticated user in a
     * PHP class.
     * 
     * @return The code snippet is returning the `()` which is a closure that represents
     * the next middleware in the pipeline. This allows the request to continue to the next middleware
     * or the controller action.
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
     * The index function retrieves the count of allowances from the workspace and passes it to the
     * view for display.
     * 
     * @param Request request The `Request ` parameter in the `index` function is an instance
     * of the `Illuminate\Http\Request` class. It represents the HTTP request that is being made to the
     * application and contains information such as input data, headers, and more.
     * 
     * @return The `index` function is returning a view called 'allowances.list' with an array
     * containing the count of allowances.
     */
    public function index(Request $request)
    {
        $allowances = $this->workspace->allowances();
        $allowances = $allowances->count();
        return view('allowances.list', ['allowances' => $allowances]);
    }

    /**
     * The function `store` validates and stores a new allowance entry in the database, returning a
     * success message or an error message accordingly.
     * 
     * @param Request request The `store` function in the code snippet is a method that handles the
     * storing of a new allowance based on the data provided in the request. Here's a breakdown of what
     * the function does:
     * 
     * @return The `store` function is returning a JSON response. If the Allowance is successfully
     * created, it returns a JSON response with an 'error' key set to false, a 'message' key with the
     * success message, and the ID of the created Allowance. If the Allowance creation fails, it
     * returns a JSON response with an 'error' key set to true and a message indicating that the
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:allowances,title', // Validate the title
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
        ]);
        $formFields['workspace_id'] = $this->workspace->id;

        if ($allowance = Allowance::create($formFields)) {
            Session::flash('message', 'Allowance created successfully.');
            return response()->json(['error' => false, 'message' => 'Allowance created successfully.', 'id' => $allowance->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Allowance couldn\'t created.']);
        }
    }

    /**
     * The function retrieves a list of allowances based on search criteria, sorts and paginates the
     * results, and returns them in JSON format.
     * 
     * @return The `list()` function returns a JSON response containing an array with two keys:
     * 1. "rows": This key contains the items (allowances) from the paginated result after applying
     * search, sorting, and formatting. Each item in the array includes the 'id', 'title', 'amount',
     * 'created_at', and 'updated_at' fields of the allowance.
     * 2. "total": This
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $allowances = $this->workspace->allowances();
        if ($search) {
            $allowances = $allowances->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $allowances->count();
        $allowances = $allowances->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($allowance) => [
                    'id' => $allowance->id,
                    'title' => $allowance->title,
                    'amount' => format_currency($allowance->amount),
                    'created_at' => format_date($allowance->created_at,  'H:i:s'),
                    'updated_at' => format_date($allowance->updated_at, 'H:i:s')
                ]
            );

        return response()->json([
            "rows" => $allowances->items(),
            "total" => $total,
        ]);
    }

    /**
     * The get function retrieves an allowance record by its ID and returns it as a JSON response.
     * 
     * @param id The `get` function is used to retrieve a specific `Allowance` record based on the
     * provided `id`. The function first tries to find the `Allowance` record with the given `id`. If
     * the record is found, it is returned as a JSON response with the key `allowance
     * 
     * @return The `get` function is returning a JSON response with the `allowance` data fetched from
     * the database using the `findOrFail` method.
     */
    public function get($id)
    {
        $allowance = Allowance::findOrFail($id);
        return response()->json(['allowance' => $allowance]);
    }

    /**
     * The function `update` in PHP validates and updates an allowance record based on the request
     * data.
     * 
     * @param Request request The `update` function in your code snippet is responsible for updating an
     * allowance based on the data provided in the request. Let's break down the code:
     * 
     * @return The `update` function is returning a JSON response. If the update operation on the
     * `Allowance` model is successful, it returns a JSON response with an error status of false, a
     * success message indicating that the allowance was updated successfully, and the ID of the
     * updated allowance. If the update operation fails, it returns a JSON response with an error
     * status of true and a message indicating that the allowance
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required',
            'title' => 'required|unique:allowances,title,' . $request->id,
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
        ]);
        $allowance = Allowance::findOrFail($request->id);

        if ($allowance->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Allowance updated successfully.', 'id' => $allowance->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Allowance couldn\'t updated.']);
        }
    }

    /**
     * The function destroys an Allowance record by detaching related payslips and using a
     * DeletionService to delete the record.
     * 
     * @param id The `id` parameter in the `destroy` function is used to identify the specific
     * `Allowance` record that needs to be deleted. This function first retrieves the `Allowance`
     * record with the given `id`, then detaches any related `payslips`, and finally uses a `
     * 
     * @return The `destroy` function is returning the response from the `DeletionService::delete`
     * method.
     */
    public function destroy($id)
    {
        $allowance = Allowance::findOrFail($id);
        $allowance->payslips()->detach();
        $response = DeletionService::delete(Allowance::class, $id, 'Allowance');
        return $response;
    }

    /**
     * The function `destroy_multiple` in PHP validates and deletes multiple allowances based on the
     * provided IDs.
     * 
     * @param Request request The `destroy_multiple` function is designed to handle the deletion of
     * multiple allowances based on the IDs provided in the request. Here's a breakdown of how it
     * works:
     * 
     * @return The function `destroy_multiple` is returning a JSON response with the following
     * structure:
     * - 'error': false, indicating that there are no errors
     * - 'message': 'Allowance(s) deleted successfully.', a success message
     * - 'id': an array containing the IDs of the allowances that were deleted
     * - 'titles': an array containing the titles of the allowances that were deleted
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:allowances,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $allowance = Allowance::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $allowance->title;
            $allowance->payslips()->detach();
            DeletionService::delete(Allowance::class, $id, 'Allowance');
        }

        return response()->json(['error' => false, 'message' => 'Allowance(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

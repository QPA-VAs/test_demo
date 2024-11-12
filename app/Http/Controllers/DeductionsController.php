<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Deduction;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;

/* The `DeductionsController` class in PHP handles CRUD operations for deductions within a workspace,
including creation, listing, updating, and deletion of deductions. */
class DeductionsController extends Controller
{
    /* The lines `protected ;` and `protected ;` are declaring two protected properties
    within the `DeductionsController` class in PHP. */
    protected $workspace;
    protected $user;
    
    /**
     * The constructor function sets up middleware to fetch the workspace and user information for the
     * entire class.
     * 
     * @return The `next()` is being returned in the middleware closure. This allows the
     * request to continue to the next middleware or controller action in the pipeline.
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
     * The index function retrieves the count of deductions from the workspace and passes it to the
     * deductions list view.
     * 
     * @param Request request The `Request ` parameter in the `index` function is an instance
     * of the `Illuminate\Http\Request` class in Laravel. It represents the HTTP request that is being
     * made to the application and contains information such as input data, headers, cookies, and more.
     * 
     * @return The `index` function is returning a view called 'deductions.list' with an array
     * containing the count of deductions as the data passed to the view.
     */
    public function index(Request $request)
    {
        $deductions = $this->workspace->deductions();
        $deductions = $deductions->count();
        return view('deductions.list', ['deductions' => $deductions]);
    }

    /**
     * The function `store` validates and stores deduction data based on type (amount or percentage) in
     * a PHP application.
     * 
     * @param Request request The `store` function in the code snippet is responsible for storing a new
     * deduction record based on the data provided in the request. Here's a breakdown of the process:
     * 
     * @return The `store` function is returning a JSON response. If the deduction is successfully
     * created, it returns a JSON response with the following structure:
     * ```json
     * {
     *     "error": false,
     *     "message": "Deduction created successfully.",
     *     "id": <deduction_id>
     * }
     * ```
     * If the deduction creation fails, it returns a JSON response with the following structure:
     * ```json
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:deductions,title',
            'type' => [
                'required',
                Rule::in(['amount', 'percentage']),
            ],
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        if ($request->type === 'amount') {
            $validatedAmount = $request->validate([
                'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            ]);
            $formFields['amount'] = $validatedAmount['amount'];
        } elseif ($request->type === 'percentage') {
            $validatedPercentage = $request->validate([
                'percentage' => 'required|numeric',
            ]);
            $formFields['percentage'] = $validatedPercentage['percentage'];
        }

        if ($deduction = Deduction::create($formFields)) {
            Session::flash('message', 'Deduction created successfully.');
            return response()->json(['error' => false, 'message' => 'Deduction created successfully.', 'id' => $deduction->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Deduction couldn\'t created.']);
        }
    }

    /**
     * The function retrieves and lists deductions based on search criteria, sorting, and pagination in
     * a PHP application.
     * 
     * @return The `list()` function returns a JSON response containing an array with two keys:
     * 1. "rows": This key contains the items (deductions) paginated based on the search criteria,
     * sorting, and ordering specified in the request. Each item in the array includes the id, title,
     * type (formatted as uppercase), percentage, amount (formatted as currency), created_at (formatted
     * date and time),
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $deductions = $this->workspace->deductions();
        if ($search) {
            $deductions = $deductions->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $deductions->count();
        $deductions = $deductions->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($deduction) => [
                    'id' => $deduction->id,
                    'title' => $deduction->title,
                    'type' => ucfirst($deduction->type),
                    'percentage' => $deduction->percentage,
                    'amount' => format_currency($deduction->amount),
                    'created_at' => format_date($deduction->created_at,  'H:i:s'),
                    'updated_at' => format_date($deduction->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $deductions->items(),
            "total" => $total,
        ]);
    }



    /**
     * The get function retrieves a Deduction object by its ID and returns it as a JSON response.
     * 
     * @param id The `get` function you provided is a PHP function that retrieves a Deduction model by
     * its ID and returns a JSON response containing the deduction data.
     * 
     * @return The `get` function is returning a JSON response containing the deduction object with the
     * specified ID.
     */
    public function get($id)
    {
        $deduction = Deduction::findOrFail($id);
        return response()->json(['deduction' => $deduction]);
    }

    /**
     * The function `update` in PHP validates and updates deduction data based on the request type
     * (amount or percentage).
     * 
     * @param Request request The `update` function in the provided code snippet is responsible for
     * updating a deduction record based on the request data. Let's break down the key steps in this
     * function:
     * 
     * @return The `update` function is returning a JSON response. If the deduction is successfully
     * updated, it returns a JSON response with an error status of false, a success message, and the ID
     * of the updated deduction. If the update operation fails, it returns a JSON response with an
     * error status of true and an error message indicating that the deduction couldn't be updated.
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:deductions,title,' . $request->id,
            'type' => [
                'required',
                Rule::in(['amount', 'percentage']),
            ],
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        if ($request->type === 'amount') {
            $validatedAmount = $request->validate([
                'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            ]);
            $formFields['amount'] = $validatedAmount['amount'];
            $formFields['percentage'] = null;
        } elseif ($request->type === 'percentage') {
            $validatedPercentage = $request->validate([
                'percentage' => 'required|numeric',
            ]);
            $formFields['percentage'] = $validatedPercentage['percentage'];
            $formFields['amount'] = null;
        }

        $deduction = Deduction::findOrFail($request->id);

        if ($deduction->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Deduction updated successfully.', 'id' => $deduction->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Deduction couldn\'t updated.']);
        }
    }

    /**
     * This PHP function deletes a Deduction record by its ID along with its related payslips and
     * returns the deletion response.
     * 
     * @param id The `destroy` function in the code snippet is used to delete a `Deduction` record from
     * the database along with its related `payslips`. The function first retrieves the `Deduction`
     * record by its ID using `findOrFail`, then detaches any related `paysl
     * 
     * @return The `destroy` function is returning the response from the `DeletionService::delete`
     * method.
     */
    public function destroy($id)
    {
        $deduction = Deduction::findOrFail($id);
        $deduction->payslips()->detach();
        $response = DeletionService::delete(Deduction::class, $id, 'Deduction');
        return $response;
    }
    /**
     * The function `destroy_multiple` in PHP validates and deletes multiple deductions based on
     * provided IDs.
     * 
     * @param Request request The `destroy_multiple` function is designed to handle the deletion of
     * multiple deductions based on the IDs provided in the request. Let's break down the process:
     * 
     * @return The function `destroy_multiple` is returning a JSON response with the following
     * structure:
     * ```json
     * {
     *     "error": false,
     *     "message": "Deduction(s) deleted successfully.",
     *     "id": [array of deleted deduction IDs],
     *     "titles": [array of deleted deduction titles]
     * }
     * ```
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:deductions,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deduction = Deduction::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $deduction->title;
            $deduction->payslips()->detach();
            DeletionService::delete(Deduction::class, $id, 'Deduction');
        }

        return response()->json(['error' => false, 'message' => 'Deduction(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

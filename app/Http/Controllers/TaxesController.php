<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Tax;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;

class TaxesController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for TaxesController.
     * 
     * Initializes middleware to:
     * - Set workspace property from session workspace_id
     * - Set authenticated user property
     * 
     * The middleware runs before any controller action to ensure
     * workspace and user context is available throughout the controller.
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
     * Display a listing of taxes.
     *
     * This method retrieves the count of all taxes associated with the current workspace
     * and returns it to the taxes list view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $taxes = $this->workspace->taxes();
        $taxes = $taxes->count();
        return view('taxes.list', ['taxes' => $taxes]);
    }

    /**
     * Store a new tax record in the database.
     *
     * Validates and stores tax information including title and type (amount/percentage).
     * The tax is associated with the current workspace.
     *
     * @param  \Illuminate\Http\Request  $request Request containing tax data
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * Request parameters:
     * - title: string (required, unique) The name of the tax
     * - type: string (required) Either 'amount' or 'percentage'
     * - amount: numeric (required if type is 'amount') Fixed tax amount
     * - percentage: numeric (required if type is 'percentage') Tax percentage value
     *
     * Response:
     * - On success: JSON with error=false, success message, and new tax ID
     * - On failure: JSON with error=true and error message
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:taxes,title',
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

        if ($res = Tax::create($formFields)) {
            Session::flash('message', 'Tax created successfully.');
            return response()->json(['error' => false, 'message' => 'Tax created successfully.', 'id' => $res->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Tax couldn\'t created.']);
        }
    }

    /**
     * Retrieve a paginated and filtered list of taxes for the current workspace.
     * 
     * This method handles:
     * - Searching through taxes based on title, amount, percentage, type, and ID
     * - Sorting results by any column (defaults to ID)
     * - Order direction (defaults to DESC)
     * - Pagination with custom limit
     * - Formatting of currency and dates
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of formatted tax records
     *         - total: Total count of records before pagination
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $taxes = $this->workspace->taxes();
        if ($search) {
            $taxes = $taxes->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('amount', 'like', '%' . $search . '%')
                    ->orWhere('percentage', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $taxes->count();
        $taxes = $taxes->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($tax) => [
                    'id' => $tax->id,
                    'title' => $tax->title,
                    'type' => ucfirst($tax->type),
                    'percentage' => $tax->percentage,
                    'amount' => format_currency($tax->amount),
                    'created_at' => format_date($tax->created_at,  'H:i:s'),
                    'updated_at' => format_date($tax->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $taxes->items(),
            "total" => $total,
        ]);
    }



    /**
     * Retrieve a specific tax record by its ID.
     *
     * @param int $id The ID of the tax record to retrieve
     * @return \Illuminate\Http\JsonResponse Returns JSON response containing the tax record
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When tax record is not found
     */
    public function get($id)
    {
        $tax = Tax::findOrFail($id);
        return response()->json(['tax' => $tax]);
    }

    /**
     * Update an existing tax record in the database.
     *
     * @param  \Illuminate\Http\Request  $request The HTTP request containing tax data
     * @return \Illuminate\Http\JsonResponse Json response indicating success/failure
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When tax is not found
     *
     * The method validates and updates:
     * - title (required, must be unique)
     * - workspace_id (automatically set from current workspace)
     *
     * Returns JSON with:
     * - error: boolean indicating success/failure
     * - message: status message
     * - id: updated tax ID (on success)
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:taxes,title,' . $request->id,
            // 'type' => [
            //     'required',
            //     Rule::in(['amount', 'percentage']),
            // ],
        ]);

        $formFields['workspace_id'] = $this->workspace->id;

        // if ($request->type === 'amount') {
        //     $validatedAmount = $request->validate([
        //         'amount' => ['nullable', 'regex:/^\d+(\.\d+)?$/'],
        //     ]);
        //     $formFields['amount'] = $validatedAmount['amount'];
        //     $formFields['percentage'] = null;
        // } elseif ($request->type === 'percentage') {
        //     $validatedPercentage = $request->validate([
        //         'percentage' => 'required|numeric',
        //     ]);
        //     $formFields['percentage'] = $validatedPercentage['percentage'];
        //     $formFields['amount'] = null;
        // }

        $tax = Tax::findOrFail($request->id);

        if ($tax->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Tax updated successfully.', 'id' => $tax->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Tax couldn\'t updated.']);
        }
    }

    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        DB::table('estimates_invoice_item')
            ->where('tax_id', $tax->id)
            ->update(['tax_id' => null]);
        $response = DeletionService::delete(Tax::class, $id, 'Tax');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:taxes,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $tax = Tax::findOrFail($id);
            DB::table('estimates_invoice_item')
            ->where('tax_id', $tax->id)
            ->update(['tax_id' => null]);
            $deletedIds[] = $id;
            $deletedTitles[] = $tax->title;
            DeletionService::delete(Tax::class, $id, 'Tax');
        }

        return response()->json(['error' => false, 'message' => 'Tax(es) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

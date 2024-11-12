<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;

class PaymentMethodsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for PaymentMethodsController
     * 
     * Sets up middleware to fetch and initialize workspace and user data for the controller.
     * The middleware runs before any controller action and:
     * - Fetches the current workspace from session
     * - Gets the authenticated user
     * 
     * These values are then available throughout the controller via:
     * - $this->workspace
     * - $this->user
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
     * Display a listing of payment methods.
     *
     * Retrieves the count of payment methods associated with the current workspace
     * and returns the view with the payment methods data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $payment_methods = $this->workspace->payment_methods();
        $payment_methods = $payment_methods->count();
        return view('payment_methods.list', ['payment_methods' => $payment_methods]);
    }
    /**
     * Store a newly created payment method in storage.
     * 
     * This method validates the request data, ensuring the title is unique,
     * and creates a new payment method associated with the current workspace.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'title' => 'required|unique:payment_methods,title', // Validate the title
        ]);
        $formFields['workspace_id'] = $this->workspace->id;

        if ($pm = PaymentMethod::create($formFields)) {
            return response()->json(['error' => false, 'message' => 'Payment method created successfully.', 'id' => $pm->id, 'type' => 'payment_method']);
        } else {
            return response()->json(['error' => true, 'message' => 'Payment method couldn\'t created.']);
        }
    }

    /**
     * Retrieve a paginated list of payment methods for the workspace.
     * 
     * This method handles:
     * - Search filtering by title or id
     * - Sorting by specified column (defaults to id)
     * - Order direction (defaults to DESC)
     * - Pagination with configurable limit
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *      - rows: Array of payment methods with id, title and timestamps
     *      - total: Total count of payment methods (before pagination)
     * 
     * Query Parameters:
     * @param string|null $search Optional search term for filtering
     * @param string|null $sort Column to sort by (default: 'id')
     * @param string|null $order Sort direction 'ASC' or 'DESC' (default: 'DESC')
     * @param int|null $limit Number of records per page
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $payment_methods = $this->workspace->payment_methods();
        if ($search) {
            $payment_methods = $payment_methods->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $payment_methods->count();
        $payment_methods = $payment_methods->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($payment_method) => [
                    'id' => $payment_method->id,
                    'title' => $payment_method->title,
                    'created_at' => format_date($payment_method->created_at,  'H:i:s'),
                    'updated_at' => format_date($payment_method->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $payment_methods->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve a specific payment method by ID.
     * 
     * @param int $id The ID of the payment method to retrieve
     * @return \Illuminate\Http\JsonResponse Returns JSON response containing the payment method
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When payment method is not found
     */
    public function get($id)
    {
        $pm = PaymentMethod::findOrFail($id);
        return response()->json(['pm' => $pm]);
    }

    /**
     * Update the specified payment method in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'title' => 'required|unique:payment_methods,title,' . $request->id,
        ]);
        $pm = PaymentMethod::findOrFail($request->id);

        if ($pm->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Payment method updated successfully.', 'id' => $pm->id, 'type' => 'payment_method']);
        } else {
            return response()->json(['error' => true, 'message' => 'Payment method couldn\'t updated.']);
        }
    }

    /**
     * Remove the specified payment method from storage.
     *
     * This method performs the following steps:
     * 1. Finds the payment method or throws 404 if not found
     * 2. Updates related payslips to have no payment method (id = 0)
     * 3. Updates related payments to have no payment method (id = 0)
     * 4. Deletes the payment method using DeletionService
     *
     * @param int $id The ID of the payment method to delete
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: false on success
     *         - message: success message
     *         - id: deleted payment method ID
     *         - title: deleted payment method title
     *         - type: 'payment_method'
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When payment method is not found
     */
    public function destroy($id)
    {
        $pm = PaymentMethod::findOrFail($id);
        $pm->payslips()->update(['payment_method_id' => 0]);
        $pm->payments()->update(['payment_method_id' => 0]);
        DeletionService::delete(PaymentMethod::class, $id, 'Payment method');
        return response()->json(['error' => false, 'message' => 'Payment method deleted successfully.', 'id' => $id, 'title' => $pm->title, 'type' => 'payment_method']);
    }

    /**
     * Delete multiple payment methods.
     *
     * This method handles the deletion of multiple payment methods simultaneously.
     * It validates the incoming IDs, ensures they exist in the database,
     * updates related payslips and payments to remove references,
     * and performs the actual deletion through the DeletionService.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing payment method IDs
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: success message
     *         - id: array of deleted payment method IDs
     *         - titles: array of deleted payment method titles
     *         - type: string identifier 'payment_method'
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When a payment method is not found
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:payment_methods,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedPms = [];
        $deletedPmTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $pm = PaymentMethod::findOrFail($id);
            $deletedPms[] = $id;
            $deletedPmTitles[] = $pm->title;
            $pm->payslips()->update(['payment_method_id' => 0]);
            $pm->payments()->update(['payment_method_id' => 0]);
            DeletionService::delete(PaymentMethod::class, $id, 'Payment method');
        }

        return response()->json(['error' => false, 'message' => 'Payment method(s) deleted successfully.', 'id' => $deletedPms, 'titles' => $deletedPmTitles, 'type' => 'payment_method']);
    }
}

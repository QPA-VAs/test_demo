<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Payment;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\EstimatesInvoice;

class PaymentsController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for PaymentsController
     * 
     * Initializes middleware that:
     * - Gets the current workspace from session
     * - Gets authenticated user
     * - Sets these as class properties for use across controller methods
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
     * Display a listing of payments.
     *
     * This method retrieves various payment-related data for the current workspace:
     * - Total count of payments
     * - Available payment methods
     * - All invoices
     * - Workspace users
     * 
     * @param \Illuminate\Http\Request $request The incoming HTTP request
     * @return \Illuminate\View\View Returns the payments list view with payment data
     */
    public function index(Request $request)
    {
        $payments = $this->workspace->payments();
        $payments = $payments->count();
        $payment_methods = $this->workspace->payment_methods;
        $invoices = $this->workspace->estimates_invoices('invoice')->get();
        $users = $this->workspace->users;
        return view('payments.list', ['payments' => $payments, 'payment_methods' => $payment_methods, 'invoices' => $invoices, 'users' => $users]);
    }
    /**
     * Display a view with the count of expense types in the workspace.
     * 
     * This method retrieves the expense types associated with the current workspace,
     * counts them, and passes the count to the expense_types view.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request
     * @return \Illuminate\View\View Returns a view with the count of expense types
     */
    public function expense_types(Request $request)
    {
        $expense_types = $this->workspace->expense_types();
        $expense_types = $expense_types->count();
        return view('expenses.expense_types', ['expense_types' => $expense_types]);
    }
    /**
     * Store a new payment record in the database.
     * 
     * This method handles the creation of a new payment record with validation of input data.
     * It checks if the total paid amount doesn't exceed the total invoice amount when an invoice is specified.
     * The payment date is formatted according to the Y-m-d format.
     * The workspace_id is automatically set based on the current workspace.
     * The created_by field is set based on whether the user is a client or regular user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When invoice_id is invalid
     */
    public function store(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'user_id' => 'nullable',
            'invoice_id' => 'nullable',
            'payment_method_id' => 'nullable',
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'payment_date' => 'required',
            'note' => 'nullable'
        ]);
        $payment_date = $request->input('payment_date');
        $formFields['payment_date'] = format_date($payment_date, null, "Y-m-d");
        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;
        if (!empty($formFields['invoice_id'])) {
            // Check if the total paid amount exceeds the total amount from the estimates_invoices table
            $totalPaidAmount = Payment::where('invoice_id', $formFields['invoice_id'])->sum('amount');
            $totalInvoiceAmount = EstimatesInvoice::findOrFail($formFields['invoice_id'])->total;
            if ($totalPaidAmount + $formFields['amount'] > $totalInvoiceAmount) {
                return response()->json(['error' => true, 'message' => 'Total paid amount exceeds the total invoice amount.']);
            }
        }


        if ($payment = Payment::create($formFields)) {
            return response()->json(['error' => false, 'message' => 'Payment created successfully.', 'id' => $payment->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Payment couldn\'t created.']);
        }
    }

    /**
     * List and filter payments with pagination
     * 
     * Retrieves a paginated list of payments with optional filtering and sorting capabilities.
     * Joins payment data with related user, invoice and payment method information.
     * Applies workspace and user access restrictions based on authentication.
     * 
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of payment records with formatted data
     *         - total: Total count of filtered records
     * 
     * Request parameters:
     * @param string|null $search Search term for filtering by ID, amount or note
     * @param string $sort Column name to sort by (default: "id")
     * @param string $order Sort direction - "ASC" or "DESC" (default: "DESC")
     * @param int|null $user_id Filter by user ID
     * @param int|null $invoice_id Filter by invoice ID
     * @param int|null $pm_id Filter by payment method ID
     * @param string|null $date_from Start date for payment date range filter
     * @param string|null $date_to End date for payment date range filter
     * @param int $limit Number of records per page
     * 
     * Response format per payment:
     * - id: Payment ID
     * - user_id: User ID
     * - user: User full name
     * - invoice_id: Invoice ID
     * - invoice: Formatted invoice link or dash
     * - payment_method_id: Payment method ID
     * - payment_method: Payment method title
     * - amount: Formatted currency amount
     * - payment_date: Formatted payment date
     * - note: Payment note
     * - created_by: Creator's full name
     * - created_at: Formatted creation time
     * - updated_at: Formatted update time
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $invoice_id = (request('invoice_id')) ? request('invoice_id') : "";
        $pm_id = (request('pm_id')) ? request('pm_id') : "";
        $pm_date_from = (request('date_from')) ? request('date_from') : "";
        $pm_date_to = (request('date_to')) ? request('date_to') : "";
        $where = ['payments.workspace_id' => $this->workspace->id];

        $payments = Payment::select(
            'payments.*',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            'estimates_invoices.id as invoice',
            'payment_methods.title as payment_method'
        )
            ->leftJoin('users', 'payments.user_id', '=', 'users.id')
            ->leftJoin('estimates_invoices', 'payments.invoice_id', '=', 'estimates_invoices.id')
            ->leftJoin('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id');


        if (!isAdminOrHasAllDataAccess()) {
            $payments = $payments->where(function ($query) {
                $query->where('payments.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('payments.user_id', $this->user->id);
            });
        }
        if ($invoice_id) {
            $where['invoice_id'] = $invoice_id;
        }
        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        if ($pm_id) {
            $where['payment_method_id'] = $pm_id;
        }
        if ($pm_date_from && $pm_date_to) {
            $payments = $payments->whereBetween('payments.payment_date', [$pm_date_from, $pm_date_to]);
        }
        if ($search) {
            $payments = $payments->where(function ($query) use ($search) {
                $query->where('payments.id', 'like', '%' . $search . '%')
                    ->orWhere('payments.amount', 'like', '%' . $search . '%')
                    ->orWhere('payments.note', 'like', '%' . $search . '%');
            });
        }

        $payments->where($where);

        $total = $payments->count();

        $payments = $payments->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($payment) {
                if (strpos($payment->created_by, 'u_') === 0) {
                    // The ID corresponds to a user
                    $creator = User::find(substr($payment->created_by, 2)); // Remove the 'u_' prefix
                } elseif (strpos($payment->created_by, 'c_') === 0) {
                    // The ID corresponds to a client
                    $creator = Client::find(substr($payment->created_by, 2)); // Remove the 'c_' prefix                    
                }
                if ($creator !== null) {
                    $creator = $creator->first_name . ' ' . $creator->last_name;
                } else {
                    $creator = '-';
                }
                return [
                    'id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'user' => $payment->user_name,
                    'invoice_id' => $payment->invoice_id,
                    'invoice' => $payment->invoice ? '<a href="/estimates-invoices/view/' . $payment->invoice . '" target="_blank">' . get_label('invoice_id_prefix', 'INVC-') . $payment->invoice_id . '</a>' : '-',
                    'payment_method_id' => $payment->payment_method_id,
                    'payment_method' => $payment->payment_method,
                    'amount' => format_currency($payment->amount),
                    'payment_date' => format_date($payment->payment_date),
                    'note' => $payment->note,
                    'created_by' => $creator,
                    'created_at' => format_date($payment->created_at,  'H:i:s'),
                    'updated_at' => format_date($payment->updated_at, 'H:i:s'),
                ];
            });


        return response()->json([
            "rows" => $payments->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieve a specific payment by ID
     * 
     * @param int $id The ID of the payment to retrieve
     * @return \Illuminate\Http\JsonResponse A JSON response containing the payment data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When payment is not found
     */
    public function get($id)
    {
        $payment = Payment::findOrFail($id);
        return response()->json(['payment' => $payment]);
    }

    /**
     * Update the specified payment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * This method handles updating an existing payment record with the following steps:
     * 1. Validates incoming request data including payment ID, user ID, invoice ID, payment method,
     *    amount, payment date and note
     * 2. Formats the payment date to Y-m-d format
     * 3. If invoice_id is provided, checks if total paid amount (excluding current payment) 
     *    plus new amount exceeds total invoice amount
     * 4. Updates the payment record and returns success/error response
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When payment not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function update(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'id' => 'required',
            'user_id' => 'nullable',
            'invoice_id' => 'nullable',
            'payment_method_id' => 'nullable',
            'amount' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'payment_date' => 'required',
            'note' => 'nullable'
        ]);
        $payment_date = $request->input('payment_date');
        $formFields['payment_date'] = format_date($payment_date, null, "Y-m-d");
        if (!empty($formFields['invoice_id'])) {
            // Check if the total paid amount exceeds the total amount from the estimates_invoices table
            $totalPaidAmount = Payment::where('invoice_id', $formFields['invoice_id'])
                ->where('id', '!=', $formFields['id']) // Exclude the current payment being updated
                ->sum('amount');
            $totalInvoiceAmount = EstimatesInvoice::findOrFail($formFields['invoice_id'])->total;

            if ($totalPaidAmount + $formFields['amount'] > $totalInvoiceAmount) {
                return response()->json(['error' => true, 'message' => 'Total paid amount exceeds the total invoice amount.']);
            }
        }

        $payment = Payment::findOrFail($request->id);
        if ($payment->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Payment updated successfully.', 'id' => $payment->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Payment couldn\'t updated.']);
        }
    }

    /**
     * Remove the specified payment from storage.
     *
     * @param  int  $id The ID of the payment to delete
     * @return \Illuminate\Http\JsonResponse A JSON response indicating the success/failure of deletion
     */
    public function destroy($id)
    {
        DeletionService::delete(Payment::class, $id, 'Payment');
        return response()->json(['error' => false, 'message' => 'Payment deleted successfully.', 'id' => $id]);
    }

    /**
     * Delete multiple payments from the system.
     *
     * @param \Illuminate\Http\Request $request Request containing array of payment IDs to delete
     * @return \Illuminate\Http\JsonResponse Json response with deletion status and details
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * The method expects a request with:
     * - ids: Array of payment IDs to be deleted
     *
     * Returns JSON response with:
     * - error: Boolean indicating if operation failed
     * - message: Success/failure message
     * - id: Array of deleted payment IDs
     * - titles: Array of deleted payment titles
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:payments,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $label = get_label('payment_id', 'Payment ID');
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deletedIds[] = $id;
            $deletedTitles[] = $label . ' ' . $id;
            DeletionService::delete(Payment::class, $id, 'Payment');
        }

        return response()->json(['error' => false, 'message' => 'Payment(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

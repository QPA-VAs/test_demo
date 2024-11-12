<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Workspace;
use App\Models\ContractType;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

/* The `ContractsController` class in PHP manages contract-related operations including creation,
updating, listing, signing, and deletion with access control based on user roles. */
class ContractsController extends Controller
{
    /* The above code is defining two protected properties in a PHP class: `` and ``.
    These properties are not initialized with any values in the code snippet provided. */
    protected $workspace;
    protected $user;
    /**
     * The constructor fetches session data and assigns it to class properties, while the index method
     * retrieves and displays contract-related data based on user access level.
     * 
     * @return The `index` method is returning a view named 'contracts.list' with an array of data
     * containing the following variables: 'contracts', 'users', 'clients', 'projects', and
     * 'contract_types'. These variables are being passed to the view for rendering.
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
     * The index function retrieves data based on user access level and returns it to the contracts
     * list view in a PHP Laravel application.
     * 
     * @param Request request The `index` function in the code snippet is a controller method that
     * retrieves data based on the user's access level and then passes that data to a view for display.
     * Here's a breakdown of the parameters used in the function:
     * 
     * @return The `index` function is returning a view named 'contracts.list' with an array of data
     * including the count of contracts, users, clients, projects, and contract types. The data being
     * passed to the view includes:
     * - 'contracts' with the count of contracts
     * - 'users' with the workspace users
     * - 'clients' with the workspace clients
     * - 'projects' with either all workspace
     */
    public function index(Request $request)
    {
        $contracts = isAdminOrHasAllDataAccess() ? $this->workspace->contracts() : $this->user->contracts();
        $contracts = $contracts->count();
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
        $contract_types = $this->workspace->contract_types;
        return view('contracts.list', ['contracts' => $contracts, 'users' => $users, 'clients' => $clients, 'projects' => $projects, 'contract_types' => $contract_types]);
    }

    /**
     * The function `store` creates a new contract with specified form fields and returns a JSON
     * response indicating success or failure.
     * 
     * @param Request request The `store` function in the code snippet is responsible for storing a new
     * contract based on the data provided in the request. Let's break down the code logic step by
     * step:
     * 
     * @return a JSON response. If the contract is successfully created, it returns a JSON response
     * with 'error' set to false, 'message' indicating success, and the 'id' of the created contract.
     * If the contract creation fails, it returns a JSON response with 'error' set to true and a
     * message indicating that the contract couldn't be created.
     */
    public function store(Request $request)
    {
        if (isClient()) {
            $request->merge(['client_id' => $this->user->id]);
        }
        $formFields = $request->validate([
            'title' => ['required'],
            'value' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required'],
            'client_id' => ['required'],
            'project_id' => ['required'],
            'contract_type_id' => ['required'],
            'description' => ['required']
        ]);

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        $formFields['end_date'] = format_date($end_date, null, "Y-m-d");
        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['created_by'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;
        if ($contract = Contract::create($formFields)) {
            Session::flash('message', 'Contract created successfully.');
            return response()->json(['error' => false, 'message' => 'Contract created successfully.', 'id' => $contract->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Contract couldn\'t created.']);
        }
    }

    /**
     * The function `update` in this PHP code snippet validates and updates contract information based
     * on the provided request data.
     * 
     * @param Request request The `update` function in your code snippet is responsible for updating a
     * contract based on the provided request data. Here is a breakdown of what the function does:
     * 
     * @return The `update` function is returning a JSON response. If the contract is successfully
     * updated, it returns a JSON response with an error status of false, a success message indicating
     * that the contract was updated successfully, and the ID of the updated contract. If the update
     * operation fails, it returns a JSON response with an error status of true and a message
     * indicating that the contract couldn't be updated.
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required|exists:contracts,id',
            'title' => ['required'],
            'value' => ['required', 'regex:/^\d+(\.\d+)?$/'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required'],
            'client_id' => ['required'],
            'project_id' => ['required'],
            'contract_type_id' => ['required'],
            'description' => ['required']
        ]);

        $contract = Contract::findOrFail($formFields['id']);

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $formFields['start_date'] = format_date($start_date, null, "Y-m-d");
        $formFields['end_date'] = format_date($end_date, null, "Y-m-d");
        if ($contract->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Contract updated successfully.', 'id' => $formFields['id']]);
        } else {
            return response()->json(['error' => true, 'message' => 'Contract couldn\'t updated.']);
        }
    }

    /**
     * The `list` function in PHP retrieves and filters contract data based on various parameters and
     * returns a JSON response with the results.
     * 
     * @return The `list()` function returns a JSON response with two keys:
     * 1. "rows": Contains the paginated list of contracts with various details such as ID, title,
     * value, start date, end date, duration, client, project, contract type, description, sign
     * statuses, creator, created at, and updated at.
     * 2. "total": Represents the total count of contracts that match the specified
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = (request('status')) ? request('status') : "";
        $type_id = (request('type_id')) ? request('type_id') : "";
        $project_id = (request('project_id')) ? request('project_id') : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $where = ['contracts.workspace_id' => $this->workspace->id];

        $contracts = Contract::select(
            'contracts.*',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name'),
            'contract_types.type as contract_type',
            'projects.title as project_title'
        )
            ->leftJoin('users', 'contracts.created_by', '=', 'users.id')
            ->leftJoin('clients', 'contracts.client_id', '=', 'clients.id')
            ->leftJoin('contract_types', 'contracts.contract_type_id', '=', 'contract_types.id')
            ->leftJoin('projects', 'contracts.project_id', '=', 'projects.id');


        if (!isAdminOrHasAllDataAccess()) {
            $contracts = $contracts->where(function ($query) {
                $query->where('contracts.created_by', isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id)
                    ->orWhere('contracts.client_id', $this->user->id);
            });
        }

        if ($project_id) {
            $where['project_id'] = $project_id;
        }
        if ($type_id) {
            $where['contract_type_id'] = $type_id;
        }
        if ($status) {
            if ($status == 'partially_signed') {
                $contracts = $contracts->where(function ($query) {
                    $query->where(function ($subquery) {
                        $subquery->whereNotNull('promisor_sign')
                            ->whereNull('promisee_sign');
                    })
                        ->orWhere(function ($subquery) {
                            $subquery->whereNull('promisor_sign')
                                ->whereNotNull('promisee_sign');
                        });
                });
            }
            if ($status == 'signed') {
                $contracts = $contracts->whereNotNull('promisor_sign')->WhereNotNull('promisee_sign');
            }
            if ($status == 'not_signed') {
                $contracts = $contracts->whereNull('promisor_sign')->whereNull('promisee_sign');
            }
        }
        if ($start_date_from && $start_date_to) {
            $contracts = $contracts->whereBetween('contracts.start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $contracts  = $contracts->whereBetween('contracts.end_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $contracts = $contracts->where(function ($query) use ($search) {
                $query->where('contracts.title', 'like', '%' . $search . '%')
                    ->orWhere('value', 'like', '%' . $search . '%')
                    ->orWhere('contracts.description', 'like', '%' . $search . '%')
                    ->orWhere('contracts.id', 'like', '%' . $search . '%');
            });
        }

        $contracts->where($where);
        $total = $contracts->count();

        $contracts = $contracts->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($contract) {
                // Format "from_date" and "to_date" with labels
                $formattedDates = format_date($contract->start_date) . ' ' . get_label('to', 'To') . ' ' . format_date($contract->end_date);

                $promisorSign = $contract->promisor_sign;
                $promiseeSign = $contract->promisee_sign;

                $statusBadge = '';

                $promisor_sign_status = !is_null($promisorSign) ? '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>' : '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';
                $promisee_sign_status = !is_null($promiseeSign) ? '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>' : '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';

                if (!is_null($promisorSign) && !is_null($promiseeSign)) {
                    $statusBadge = '<span class="badge bg-success">' . get_label('signed', 'Signed') . '</span>';
                } elseif (!is_null($promisorSign) || !is_null($promiseeSign)) {
                    $statusBadge = '<span class="badge bg-warning">' . get_label('partially_signed', 'Partially signed') . '</span>';
                } else {
                    $statusBadge = '<span class="badge bg-danger">' . get_label('not_signed', 'Not signed') . '</span>';
                }
                if (strpos($contract->created_by, 'u_') === 0) {
                    // The ID corresponds to a user
                    $creator = User::find(substr($contract->created_by, 2)); // Remove the 'u_' prefix
                } elseif (strpos($contract->created_by, 'c_') === 0) {
                    // The ID corresponds to a client
                    $creator = Client::find(substr($contract->created_by, 2)); // Remove the 'c_' prefix                    
                }
                if ($creator !== null) {
                    $creator = $creator->first_name . ' ' . $creator->last_name;
                } else {
                    $creator = '-';
                }
                return [
                    'id' => $contract->id,
                    'title' => '<a href="/contracts/sign/' . $contract->id . '" target="_blank">' . $contract->title . '</a>',
                    'value' => format_currency($contract->value),
                    'start_date' => format_date($contract->start_date),
                    'end_date' => format_date($contract->end_date),
                    'duration' => $formattedDates,
                    'client' => $contract->client_name,
                    'project' => $contract->project_title,
                    'contract_type' => $contract->contract_type,
                    'description' => $contract->description,
                    'promisor_sign' => $promisor_sign_status,
                    'promisee_sign' => $promisee_sign_status,
                    'status' => $statusBadge,
                    'created_by' => $creator,
                    'created_at' => format_date($contract->created_at,  'H:i:s'),
                    'updated_at' => format_date($contract->updated_at, 'H:i:s'),
                ];
            });


        return response()->json([
            "rows" => $contracts->items(),
            "total" => $total,
        ]);
    }

    /**
     * The function retrieves a Contract model by its ID and returns a JSON response with the contract
     * data.
     * 
     * @param id The `get` function in the code snippet is a method that retrieves a `Contract` model
     * by its ID and returns a JSON response containing the contract data if found. The `findOrFail`
     * method is used to retrieve the contract by its ID, and if the contract is not found, it will
     * throw
     * 
     * @return An error status of false and the contract data associated with the given ID are being
     * returned in JSON format.
     */
    public function get($id)
    {
        $contract = Contract::findOrFail($id);
        return response()->json(['error' => false, 'contract' => $contract]);
    }

    /**
     * The `duplicate` function duplicates a Contract record using a general `duplicateRecord` function
     * and returns a JSON response indicating success or failure.
     * 
     * @param id The `duplicate` function you provided seems to be a method in a PHP class that
     * duplicates a record of a Contract model based on the given ``. It calls a `duplicateRecord`
     * function passing the Contract class and the `` as parameters.
     * 
     * @return The `duplicate` function is returning a JSON response. If the duplication of the
     * contract is successful, it will return a JSON response with `error` set to `false`, the original
     * `id`, and a success message. If the duplication fails, it will return a JSON response with
     * `error` set to `true` and an error message indicating that the contract duplication failed.
     */
    public function duplicate($id)
    {
        // Use the general duplicateRecord function
        $duplicate = duplicateRecord(Contract::class, $id);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Contract duplication failed.']);
        }
        return response()->json(['error' => false, 'id' => $id, 'message' => 'Contract duplicated successfully.']);
    }

    /**
     * This PHP function retrieves contract details and the creator's name based on the ID provided,
     * then returns a view for signing the contract.
     * 
     * @param Request request The `Request ` parameter in the `sign` function represents the
     * HTTP request that is being made to the server. It contains all the data that is sent along with
     * the request, such as form data, query parameters, and more.
     * @param id The `id` parameter in the `sign` function is used to retrieve a specific contract from
     * the database based on its ID. The function then fetches additional related information such as
     * client details, contract type, project details, and the creator of the contract.
     * 
     * @return a view called 'contracts.sign' with the data from the  variable passed to the
     * view using the compact() function.
     */
    public function sign(Request $request, $id)
    {
        $contract = Contract::select(
            'contracts.*',
            'clients.id as client_id',
            'contracts.created_by as created_by_id',
            DB::raw('CONCAT(clients.first_name, " ", clients.last_name) AS client_name'),
            'contract_types.type as contract_type',
            'projects.title as project_title',
            'projects.id as project_id'
        )->where('contracts.id', '=', $id)
            ->leftJoin('users', 'contracts.created_by', '=', 'users.id')
            ->leftJoin('clients', 'contracts.client_id', '=', 'clients.id')
            ->leftJoin('contract_types', 'contracts.contract_type_id', '=', 'contract_types.id')
            ->leftJoin('projects', 'contracts.project_id', '=', 'projects.id')->first();

        if (strpos($contract->created_by, 'u_') === 0) {
            // The ID corresponds to a user
            $creator = User::find(substr($contract->created_by, 2)); // Remove the 'u_' prefix
        } elseif (strpos($contract->created_by, 'c_') === 0) {
            // The ID corresponds to a client
            $creator = Client::find(substr($contract->created_by, 2)); // Remove the 'c_' prefix                    
        }
        if ($creator !== null) {
            $contract->creator = $creator->first_name . ' ' . $creator->last_name;
        } else {
            $contract->creator = ' -';
        }
        return view('contracts.sign', compact('contract'));
    }

    /**
     * The function `create_sign` processes a signature image, saves it as a PNG file, and updates the
     * corresponding contract record based on user roles.
     * 
     * @param Request request The `create_sign` function is responsible for creating a signature for a
     * contract based on the provided request data. Here's a breakdown of the process:
     * 
     * @return The function `create_sign` is returning a JSON response. If the signature is created
     * successfully, it returns a JSON response with the following structure:
     * ```json
     * {
     *     "error": false,
     *     "id": <contract_id>,
     *     "activity_message": "<user_name> signed contract <contract_title>"
     * }
     * ```
     * If the signature couldn't be created, it returns a JSON response with the
     */
    public function create_sign(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required',
            'signatureImage' => 'required'
        ]);
        $contract = Contract::findOrFail($formFields['id']);
        $base64Data = $request->input('signatureImage');
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Data));
        // $imageData = base64_decode($base64Data);
        $filename = 'signature_' . uniqid() . '.png';
        Storage::put('public/signatures/' . $filename, $imageData);
        if (($this->user->id == $contract->created_by || isAdminOrHasAllDataAccess()) && !isClient()) {
            $contract->promisor_sign = $filename;
        } elseif (($this->user->id == $contract->client_id) && isClient()) {
            $contract->promisee_sign = $filename;
        }
        if ($contract->save()) {
            Session::flash('message', 'Signature created successfully.');
            return response()->json(['error' => false, 'id' => $formFields['id'], 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' signed contract ' . $contract->title]);
        } else {
            return response()->json(['error' => true, 'message' => 'Signature couldn\'t created.']);
        }
    }

    /**
     * The function `delete_sign` deletes a signature from a contract based on user permissions and
     * updates the database accordingly.
     * 
     * @param id The `delete_sign` function you provided is responsible for deleting a signature from a
     * contract based on certain conditions. The function takes an `` parameter which represents the
     * ID of the contract whose signature needs to be deleted.
     * 
     * @return The function `delete_sign` returns a JSON response with an error status and additional
     * data based on certain conditions. If the conditions for deleting a signature are met (user is
     * creator or admin, not a client), it deletes the signature file, updates the database, flashes a
     * success message, and returns a JSON response with error set to false and some activity message.
     * If the user is a client and the
     */
    public function delete_sign($id)
    {
        $contract = Contract::findOrFail($id);
        if (($this->user->id == $contract->created_by || isAdminOrHasAllDataAccess()) && !isClient()) {
            Storage::delete('public/signatures/' . $contract->promisor_sign);
            Contract::where('id', $id)->update(['promisor_sign' => null]);
            Session::flash('message', 'Signature deleted successfully.');
            return response()->json(['error' => false, 'id' => $id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' unsigned contract ' . $contract->title]);
        } elseif ($this->user->id == $contract->client_id && isClient()) {
            Storage::delete('public/signatures/' . $contract->promisee_sign);
            Contract::where('id', $id)->update(['promisee_sign' => null]);
            Session::flash('message', 'Signature deleted successfully.');
            return response()->json(['error' => false, 'id' => $id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' unsigned contract ' . $contract->title]);
        } else {
            Session::flash('error', 'Un authorized access.');
            return response()->json(['error' => true]);
        }
    }

    /**
     * This PHP function deletes a contract record and associated signatures from storage.
     * 
     * @param id The `destroy` function in the code snippet is responsible for deleting a `Contract`
     * record from the database along with associated files stored in the `public/signatures`
     * directory. The function first retrieves the `Contract` instance with the given ``, then calls
     * the `delete` method of the `
     * 
     * @return The `destroy` function is returning the response from the `DeletionService::delete`
     * method.
     */
    public function destroy($id)
    {
        $contract = Contract::findOrFail($id);
        if ($response = DeletionService::delete(Contract::class, $id, 'Contract')) {
            Storage::delete('public/signatures/' . $contract->promisor_sign);
            Storage::delete('public/signatures/' . $contract->promisee_sign);
        }
        return $response;
    }

    /**
     * The function `destroy_multiple` in PHP validates and deletes multiple contracts based on the
     * provided IDs, also deleting associated signature files.
     * 
     * @param Request request The `destroy_multiple` function is designed to handle the deletion of
     * multiple contracts based on the provided IDs in the request. Here's a breakdown of the function:
     * 
     * @return The function `destroy_multiple` is returning a JSON response with the following
     * structure:
     * - 'error': false (indicating no error occurred during the deletion process)
     * - 'message': 'Contract(s) deleted successfully.'
     * - 'id': An array containing the IDs of the contracts that were successfully deleted
     * - 'titles': An array containing the titles of the contracts that were successfully deleted
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:contracts,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedContracts = [];
        $deletedContractTitles = [];
        // Perform deletion using validated IDs

        foreach ($ids as $id) {
            $contract = Contract::findOrFail($id);
            if ($contract) {
                $deletedContracts[] = $id;
                $deletedContractTitles[] = $contract->title;
                if (DeletionService::delete(Contract::class, $id, 'Contract')) {
                    Storage::delete('public/signatures/' . $contract->promisor_sign);
                    Storage::delete('public/signatures/' . $contract->promisee_sign);
                }
            }
        }
        return response()->json(['error' => false, 'message' => 'Contract(s) deleted successfully.', 'id' => $deletedContracts, 'titles' => $deletedContractTitles]);
    }

    /**
     * The function retrieves the count of contract types from the workspace and passes it to the
     * contract_types view.
     * 
     * @param Request request The `Request ` parameter in the `contract_types` function is an
     * instance of the `Illuminate\Http\Request` class in Laravel. It represents the HTTP request that
     * is being made to the application and contains information such as input data, headers, and more.
     * 
     * @return The `contract_types` method is returning a view named 'contracts.contract_types' with
     * the data of the count of contract types stored in the variable ``.
     */
    public function contract_types(Request $request)
    {
        $contract_types = $this->workspace->contract_types();
        $contract_types = $contract_types->count();
        return view('contracts.contract_types', ['contract_types' => $contract_types]);
    }

    /**
     * The function `store_contract_type` validates and stores a new contract type in a PHP
     * application.
     * 
     * @param Request request The `store_contract_type` function is used to store a new contract type
     * based on the data provided in the request. Here's a breakdown of the function:
     * 
     * @return The function `store_contract_type` is returning a JSON response. If the ContractType is
     * successfully created, it returns a JSON response with the following data:
     * - 'error' set to false
     * - 'message' indicating that the Contract type was created successfully
     * - 'id' containing the id of the created ContractType
     * - 'title' containing the type of the created ContractType
     * - '
     */
    public function store_contract_type(Request $request)
    {
        // Validate the request data
        $formFields = $request->validate([
            'type' => 'required|unique:contract_types,type', // Validate the type
        ]);
        $formFields['workspace_id'] = $this->workspace->id;

        if ($ct = ContractType::create($formFields)) {
            Session::flash('message', 'Contract type created successfully.');
            return response()->json(['error' => false, 'message' => 'Contract type created successfully.', 'id' => $ct->id, 'title' => $ct->type, 'type' => 'contract_type']);
        } else {
            return response()->json(['error' => true, 'message' => 'Contract type couldn\'t created.']);
        }
    }

    /**
     * The function `contract_types_list` retrieves and paginates a list of contract types based on
     * search criteria and returns the results in JSON format.
     * 
     * @return The `contract_types_list` function returns a JSON response containing an array with two
     * keys:
     * 1. "rows": This key contains the paginated list of contract types with their id, type,
     * created_at, and updated_at fields formatted as specified.
     * 2. "total": This key contains the total count of contract types that match the search criteria
     * (if any).
     */
    public function contract_types_list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $contract_types = $this->workspace->contract_types();
        if ($search) {
            $contract_types = $contract_types->where(function ($query) use ($search) {
                $query->where('type', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $contract_types->count();
        $contract_types = $contract_types->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(
                fn ($contract_type) => [
                    'id' => $contract_type->id,
                    'type' => $contract_type->type,
                    'created_at' => format_date($contract_type->created_at,  'H:i:s'),
                    'updated_at' => format_date($contract_type->updated_at, 'H:i:s'),
                ]
            );

        return response()->json([
            "rows" => $contract_types->items(),
            "total" => $total,
        ]);
    }

    /**
     * The function `get_contract_type` retrieves a contract type by its ID and returns it as a JSON
     * response.
     * 
     * @param id The `get_contract_type` function takes an `` parameter, which is used to retrieve a
     * ContractType model from the database using the `findOrFail` method. The function then returns a
     * JSON response containing the retrieved ContractType object with the key 'ct'.
     * 
     * @return The `get_contract_type` function is returning a JSON response with the ContractType
     * object that was found using the `findOrFail` method with the provided ``. The response
     * includes the ContractType object under the key 'ct'.
     */
    public function get_contract_type($id)
    {
        $ct = ContractType::findOrFail($id);
        return response()->json(['ct' => $ct]);
    }

    /**
     * The function `update_contract_type` updates a contract type in PHP using Laravel's Eloquent ORM.
     * 
     * @param Request request The `update_contract_type` function is used to update a contract type
     * based on the provided request data. Here is a breakdown of the function:
     * 
     * @return The function `update_contract_type` returns a JSON response. If the contract type is
     * successfully updated, it returns a JSON response with the following structure:
     * ```json
     * {
     *     "error": false,
     *     "message": "Contract type updated successfully.",
     *     "id": <contract_type_id>,
     *     "title": "<updated_contract_type_title>",
     *     "type": "contract_type"
     * }
     * ```
     */
    public function update_contract_type(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'type' => 'required|unique:contract_types,type,' . $request->id,
        ]);
        $ct = ContractType::findOrFail($request->id);
        if ($ct->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Contract type updated successfully.', 'id' => $ct->id, 'title' => $formFields['type'], 'type' => 'contract_type']);
        } else {
            return response()->json(['error' => true, 'message' => 'Contract type couldn\'t updated.']);
        }
    }

    /**
     * The function `delete_contract_type` deletes a contract type by setting its associated contracts
     * to have a contract type ID of 0 and then deletes the contract type itself.
     * 
     * @param id The `delete_contract_type` function takes an `` parameter, which is used to
     * identify the specific contract type that needs to be deleted. This function first retrieves the
     * contract type using the `findOrFail` method from the `ContractType` model based on the provided
     * ``.
     * 
     * @return The function `delete_contract_type` is returning a JSON response with the following keys
     * and values:
     * - 'error': false
     * - 'message': 'Contract type deleted successfully.'
     * - 'id': the ID of the deleted contract type
     * - 'title': the type of the deleted contract type
     * - 'type': 'contract_type'
     */
    public function delete_contract_type($id)
    {
        $ct = ContractType::findOrFail($id);
        $ct->contracts()->update(['contract_type_id' => 0]);
        DeletionService::delete(ContractType::class, $id, 'Contract type');
        return response()->json(['error' => false, 'message' => 'Contract type deleted successfully.', 'id' => $id, 'title' => $ct->type, 'type' => 'contract_type']);
    }

    /**
     * The function `delete_multiple_contract_type` deletes multiple contract types based on validated
     * IDs and updates associated contracts accordingly.
     * 
     * @param Request request The provided code snippet is a PHP function that handles the deletion of
     * multiple contract types based on the IDs passed in the request. Here's a breakdown of the
     * function:
     * 
     * @return The function `delete_multiple_contract_type` returns a JSON response with the following
     * structure:
     * - 'error': false (indicating no error occurred during the deletion process)
     * - 'message': 'Contract type(s) deleted successfully.'
     * - 'id': An array containing the IDs of the deleted contract types
     * - 'titles': An array containing the titles of the deleted contract types
     * - 'type': '
     */
    public function delete_multiple_contract_type(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:contract_types,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedContractTypes = [];
        $deletedContractTypeTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $ct = ContractType::findOrFail($id);
            if ($ct) {
                $deletedContractTypes[] = $id;
                $deletedContractTypeTitles[] = $ct->type;
                $ct->contracts()->update(['contract_type_id' => 0]);
                DeletionService::delete(ContractType::class, $id, 'Contract type');
            }
        }

        return response()->json(['error' => false, 'message' => 'Contract type(s) deleted successfully.', 'id' => $deletedContractTypes, 'titles' => $deletedContractTypeTitles, 'type' => 'contract_type']);
    }
}

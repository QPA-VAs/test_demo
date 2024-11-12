<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * The index function retrieves clients based on the user's role and displays them in the
     * appropriate view.
     * 
     * @return The `index` function is returning a view based on the user's role. If the authenticated
     * user has a role named 'member', it will return the 'emp_clients' view with the clients data.
     * Otherwise, it will return the 'eclients' view with the clients data.
     */
    public function index()
    {
        $workspace = Workspace::find(session()->get('workspace_id'));
        $clients = $workspace->clients ?? [];
        if (Auth::user()->roles->pluck('name')->contains('member')) {

            return view('clients.emp_clients', ['clients' => $clients]);
        }

        return view('clients.eclients', ['clients' => $clients]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    /**
     * The create function returns a view for creating a new client.
     * 
     * @return A view named 'clients.create_client' is being returned.
     */
    public function create()
    {
        return view('clients.create_client');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    /**
     * The function `store` in PHP handles the creation of a new client with validation, file upload
     * handling, date formatting, role assignment, and error handling.
     * 
     * @param Request request The `store` function you provided is a controller method that handles the
     * storing of a new client record based on the data received in the HTTP request. Let's break down
     * the key parts of this function:
     * 
     * @return The function `store` is returning a JSON response. If the client creation is successful,
     * it returns an array with 'error' set to false and the 'id' of the created client. If there is an
     * error during client creation, it returns an array with 'error' set to true and a corresponding
     * error message.
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'business_name' => 'required',
            'company' => 'nullable',
            'phone' => 'required',
            'password' => 'nullable',
            'address' => 'required',
            'zip' => 'required',
            'email' => ['required', 'email'],
            'preferred_correspondence_email' => 'required',
            'preferred_contact_method' => 'nullable',
            'business_address' => 'required',
            'address_line_1' => 'nullable',
            'address_line_2' => 'nullable',
            'state_province_region' => 'nullable',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'postal_zip_code' => 'required',
            'website' => 'nullable',
            'prefer_company' => 'required',
            'dob' => 'required',
            'doj' => 'required',
            'applications_used' => 'nullable',
            'maximum_budget' => 'nullable',
            'agree_terms' => 'nullable',
            'agree_update_terms' => 'nullable',
            'signature' => 'nullable'

        ]);


        if ($request->hasFile('profile')) {
            $formFields['photo'] = $request->file('profile')->store('photos', 'public');
        } else {
            $formFields['photo'] = 'photos/no-image.jpg';
        }
        $dob = $request->input('dob');
        $doj = $request->input('doj');
        $agree_terms = $request->input('agree_terms');
        $agree_terms == "yes" ? $agree_terms = true : $agree_terms = false;
        $agree_terms_update = $request->input('agree_update_terms');
        $agree_terms_update == "yes" ? $agree_terms_update = true : $agree_terms_update = false;
        $formFields['password'] = '123456';
        $formFields['dob'] = format_date($dob, null, "Y-m-d");
        $formFields['doj'] = format_date($doj, null, "Y-m-d");
        $formFields['agree_terms'] = $agree_terms;
        $formFields['agree_update_terms'] = $agree_terms_update;


        $role_id = Role::where('name', 'client')->first()->id;
        $workspace = Workspace::find(session()->get('workspace_id'));

        $status = isAdminOrHasAllDataAccess() && $request->has('status') && $request->input('status') == 1 ? 1 : 0;
        if ($status == 1) {
            $formFields['email_verified_at'] = now()->tz(config('app.timezone'));
        }
        $formFields['status'] = $status;

        $client = Client::create($formFields);

        try {
            if ($status == 0) {
                event(new Registered($client));
            }
            $workspace->clients()->attach($client->id);
            $client->assignRole($role_id);
            Session::flash('message', 'Client created successfully.');
            return response()->json(['error' => false, 'id' => $client->id]);
        } catch (TransportExceptionInterface $e) {

            $client = Client::findOrFail($client->id);
            $client->delete();
            return response()->json(['error' => true, 'message' => 'Client couldn\'t be created, please check email settings.']);
        } catch (Throwable $e) {
            // Catch any other throwable, including non-Exception errors

            $client = Client::findOrFail($client->id);
            $client->delete();
            return response()->json(['error' => true, 'message' => 'Client couldn\'t be created, please check email settings.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * The function "show" retrieves data related to a client and their associated projects, tasks,
     * users, and clients within a workspace in PHP.
     * 
     * @param id The `id` parameter in the `show` function is used to retrieve a specific client record
     * based on the provided ID. This function fetches information about the client, such as their
     * projects, tasks count, associated users, and clients within the workspace. The retrieved data is
     * then passed to the `
     * 
     * @return The `show` function is returning a view called 'clients.client_profile' with an array of
     * data including the client, projects associated with the client, number of tasks for the client,
     * users in the workspace, clients in the workspace, and the authenticated user.
     */
    public function show($id)
    {
        $workspace = Workspace::find(session()->get('workspace_id'));
        $client = Client::findOrFail($id);
        $projects = $client->projects;
        $tasks = $client->tasks()->count();
        $users = $workspace->users;
        $clients = $workspace->clients;
        return view('clients.client_profile', ['client' => $client, 'projects' => $projects, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients, 'auth_user' => getAuthenticatedUser()]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * The edit function retrieves a specific client by ID and passes it to a view for editing.
     * 
     * @param id The `edit` function is used to retrieve a specific client record based on the provided
     * `` parameter. The function first tries to find the client with the given `` using the
     * `findOrFail` method of the `Client` model. If the client is found, it then returns a view
     * 
     * @return The `edit` function is returning a view called `update_client` with the `client` data
     * fetched using the `findOrFail` method based on the provided ``.
     */
    public function edit($id)
    {
        $client = Client::findOrFail($id);
        return view('clients.update_client')->with('client', $client);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * The function `update` in this PHP code snippet updates client details based on the provided
     * request data and handles file uploads and date formatting.
     * 
     * @param Request request The `update` function in the code snippet is used to update client
     * details based on the provided request data. Here's a breakdown of the process:
     * @param id The `id` parameter in the `update` function represents the unique identifier of the
     * client record that needs to be updated. This identifier is used to fetch the specific client
     * from the database using `Client::findOrFail()` and then update its details based on the
     * validated form fields provided in the request
     * 
     * @return The `update` function is returning a JSON response with an object containing two keys:
     * 1. 'error': A boolean value set to false, indicating that there are no errors.
     * 2. 'id': The ID of the updated client record, which is retrieved from `->id`.
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'company' => 'nullable',
            'phone' => 'required',
            'email' => [
                'required'
            ],
            'address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'zip' => 'required',
            'dob' => 'required',
            'doj' => 'required',
        ]);
        $client = Client::findOrFail($id);
        if ($request->hasFile('upload')) {
            if ($client->photo != 'photos/no-image.jpg' && $client->photo !== null)
                Storage::disk('public')->delete($client->photo);
            $formFields['photo'] = $request->file('upload')->store('photos', 'public');
        }
        $dob = $request->input('dob');
        $doj = $request->input('doj');
        $formFields['dob'] = format_date($dob, null, "Y-m-d");
        $formFields['doj'] = format_date($doj, null, "Y-m-d");

        $status = isAdminOrHasAllDataAccess() && $request->has('status') && $request->input('status') == 1 ? 1 : $client->status;
        $formFields['status'] = $status;

        $client->update($formFields);

        Session::flash('message', 'Client details updated successfully.');
        return response()->json(['error' => false, 'id' => $client->id]);
    }

    /**
     * The get function retrieves a client by their ID and returns it as a JSON response.
     * 
     * @param id The `get` function is a method that retrieves a `Client` model instance based on the
     * provided `id` parameter. The `findOrFail` method is used to find a client by its primary key,
     * and if the client is not found, it will throw a `ModelNotFoundException`.
     * 
     * @return The `get` function is returning a JSON response with the client data fetched using the
     * `findOrFail` method from the `Client` model. The response includes the client data under the key
     * 'client'.
     */
    public function get($id)
    {
        $client = Client::findOrFail($id);
        return response()->json(['client' => $client]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /**
     * The `destroy` function deletes a client record along with associated todos using a deletion
     * service in PHP.
     * 
     * @param id The `id` parameter in the `destroy` function is used to identify the specific client
     * that needs to be deleted. It is typically the unique identifier of the client record in the
     * database, such as the primary key.
     * 
     * @return The `destroy` function is returning the response from the `DeletionService::delete`
     * method.
     */
    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        $response = DeletionService::delete(Client::class, $id, 'Client');
        $client->todos()->delete();
        return $response;
    }


    /**
     * The function `destroy_multiple` validates and deletes multiple client records based on the
     * provided IDs.
     * 
     * @param Request request The `destroy_multiple` function in the provided code snippet is
     * responsible for deleting multiple client records based on the IDs provided in the request. Here
     * is a breakdown of the function:
     * 
     * @return The function `destroy_multiple` is returning a JSON response with the following
     * structure:
     * - 'error': false, indicating that there are no errors
     * - 'message': 'Clients(s) deleted successfully.', a success message
     * - 'id': an array containing the IDs of the clients that were deleted
     * - 'titles': an array containing the full names of the clients that were deleted
     */
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:clients,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedClients = [];
        $deletedClientNames = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $client = Client::findOrFail($id);
            if ($client) {
                $deletedClients[] = $id;
                $deletedClientNames[] = $client->first_name . ' ' . $client->last_name;
                DeletionService::delete(Client::class, $id, 'Client');
                $client->todos()->delete();
            }
        }
        return response()->json(['error' => false, 'message' => 'Clients(s) deleted successfully.', 'id' => $deletedClients, 'titles' => $deletedClientNames]);
    }



    /**
     * The function retrieves and paginates a list of clients based on search criteria and returns the
     * data in JSON format.
     * 
     * @return The `list()` function returns a JSON response containing an array with two keys:
     * 1. "rows": This key contains the paginated list of clients with their details such as id, first
     * name, last name, company, email, phone, profile image, number of projects, status, creation
     * date, update date, and number of tasks.
     * 2. "total": This key contains the total count
     */
    public function list()
    {
        $workspace = Workspace::find(session()->get('workspace_id'));
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $clients = $workspace->clients();
        $clients = $clients->when($search, function ($query) use ($search) {
            return $query->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%')
                ->orWhere('company', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%');
        });
        $totalclients = $clients->count();

        $clients = $clients->orderBy($sort, $order)
            ->paginate(request("limit"))

            // ->withQueryString()
            ->through(fn($client) => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'profile' => "<div class='avatar avatar-md pull-up' title='" . $client->first_name . " " . $client->last_name . "'>
                                <a href='/clients/profile/" . $client->id . "'>
                                <img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>
                                </a>
                                </div>",
                'projects' => count(isAdminOrHasAllDataAccess('client', $client->id) ? $workspace->projects : $client->projects),
                'status' => $client->status,
                'created_at' => format_date($client->created_at, 'H:i:s'),
                'updated_at' => format_date($client->updated_at, 'H:i:s'),
                'tasks' => $client->tasks()->count()
            ]);

        return response()->json([
            "rows" => $clients->items(),
            "total" => $totalclients,
        ]);
    }

    /**
     * The function `verify_email` fulfills an email verification request and redirects the user to the
     * home page with a success message.
     * 
     * @param EmailVerificationRequest request The `verify_email` function takes an
     * `EmailVerificationRequest` object as a parameter. This object likely contains information
     * related to the email verification process, such as the email address to be verified and any
     * additional data needed for verification.
     * 
     * @return A redirect to the '/home' route with a success message 'Email verified successfully.' is
     * being returned.
     */
    public function verify_email(EmailVerificationRequest $request)
    {
        $request->fulfill();
        return redirect('/home')->with('message', 'Email verified successfully.');
    }
}

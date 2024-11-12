<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\TaskUser;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use GuzzleHttp\Promise\TaskQueue;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Contracts\Role as ContractsRole;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $workspace = Workspace::find(session()->get('workspace_id'));
        $users = $workspace->users;
        return view('users.users', ['users' => $users]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::where('guard_name', 'web')->get();
        return view('users.create_user', ['roles' => $roles]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, User $user)
    {
        $formFields = $request->validate([
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => 'required|confirmed|min:6',
            'password_confirmation' => 'required',
            'address' => 'required',
            'phone' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'zip' => 'required',
            'dob' => 'required',
            'doj' => 'required',
            'role' => 'required'
        ]);
        $workspace = Workspace::find(session()->get('workspace_id'));
        $dob = $request->input('dob');
        $doj = $request->input('doj');
        $formFields['dob'] = format_date($dob, null, "Y-m-d");
        $formFields['doj'] = format_date($doj, null, "Y-m-d");
        $formFields['password'] = bcrypt($formFields['password']);

        if ($request->hasFile('photo')) {
            $formFields['photo'] = $request->file('photo')->store('photos', 'public');
        } else {
            $formFields['photo'] = 'photos/no-image.jpg';
        }

        $status = isAdminOrHasAllDataAccess() && $request->has('status') && $request->input('status') == 1 ? 1 : 0;
        if ($status == 1) {
            $formFields['email_verified_at'] = now()->tz(config('app.timezone'));
        }
        $formFields['status'] = $status;
        $user = User::create($formFields);
        try {
            if ($status == 0) {
                event(new Registered($user));
            }
            $workspace->users()->attach($user->id);
            $user->assignRole($request->input('role'));
            Session::flash('message', 'User created successfully.');
            return response()->json(['error' => false, 'id' => $user->id]);
        } catch (TransportExceptionInterface $e) {

            $user = User::findOrFail($user->id);
            $user->delete();
            return response()->json(['error' => true, 'message' => 'User couldn\'t be created, please check email settings.']);
        } catch (Throwable $e) {
            // Catch any other throwable, including non-Exception errors

            $user = User::findOrFail($user->id);
            $user->delete();
            return response()->json(['error' => true, 'message' => 'User couldn\'t be created, please check email settings.']);
        }
    }

    /**
     * Show email verification notice for unverified users.
     * 
     * This method checks if the authenticated user has verified their email address.
     * If the email is not verified, it displays the verification notice view.
     * If the email is already verified, redirects to the home page.
     * 
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function email_verification()
    {
        $user = getAuthenticatedUser();
        if (!$user->hasVerifiedEmail()) {
            return view('auth.verification-notice');
        } else {
            return redirect('/home');
        }
    }

    /**
     * Resends the email verification link to the authenticated user.
     * 
     * This method checks if email is configured in the system before attempting to send
     * the verification link. If email is configured, sends a new verification notification
     * to the authenticated user's email address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resend_verification_link(Request $request)
    {
        if (isEmailConfigured()) {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('message', 'Verification link sent.');
        } else {
            return back()->with('error', 'Verification link couldn\'t sent.');
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */


    /**
     * Show the form for editing a user.
     *
     * @param  int  $id  The ID of the user to edit
     * @return \Illuminate\View\View Returns the edit user view with user and roles data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user is not found
     */

    public function edit_user($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::where('guard_name', 'web')->get();
        return view('users.edit_user', ['user' => $user, 'roles' => $roles]);
    }

    /**
     * Update user profile details and role
     *
     * @param \Illuminate\Http\Request $request The HTTP request object containing form data
     * @param int $id The ID of the user to update
     * @return \Illuminate\Http\JsonResponse JSON response with error status and user ID
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user not found
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * Validates and updates user profile information including:
     * - Personal details (name, contact info, address)
     * - Dates (DOB, date of joining)
     * - Profile photo (optional)
     * - User status (if admin)
     * - User role
     *
     * Profile photo is stored in public storage.
     * Old photo is deleted when new one is uploaded.
     * Sets flash message on successful update.
     */
    public function update_user(Request $request, $id)
    {

        $formFields = $request->validate([
            'first_name' => ['required'],
            'last_name' => ['required'],
            'phone' => 'required',
            'address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'zip' => 'required',
            'dob' => 'required',
            'doj' => 'required'
        ]);
        $user = User::findOrFail($id);
        $dob = $request->input('dob');
        $doj = $request->input('doj');
        $formFields['dob'] = format_date($dob, null, "Y-m-d");
        $formFields['doj'] = format_date($doj, null, "Y-m-d");
        if ($request->hasFile('upload')) {
            if ($user->photo != 'photos/no-image.jpg' && $user->photo !== null)
                Storage::disk('public')->delete($user->photo);

            $formFields['photo'] = $request->file('upload')->store('photos', 'public');
        }

        $status = isAdminOrHasAllDataAccess() && $request->has('status') && $request->input('status') == 1 ? 1 : $user->status;
        $formFields['status'] = $status;

        $user->update($formFields);
        $user->syncRoles($request->input('role'));

        Session::flash('message', 'Profile details updated successfully.');
        return response()->json(['error' => false, 'id' => $user->id]);
    }

    /**
     * Update user's profile photo.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request
     * @param  int  $id  The ID of the user to update
     * @return \Illuminate\Http\RedirectResponse  Redirects back with success or error message
     *
     * This method handles the upload and storage of a user's profile photo.
     * If an existing photo exists (not the default 'no-image.jpg'), it will be deleted.
     * The new photo is stored in the 'public/photos' directory.
     */
    public function update_photo(Request $request, $id)
    {
        if ($request->hasFile('upload')) {
            $old = User::findOrFail($id);
            if ($old->photo != 'photos/no-image.jpg' && $old->photo !== null)
                Storage::disk('public')->delete($old->photo);
            $formFields['photo'] = $request->file('upload')->store('photos', 'public');
            User::findOrFail($id)->update($formFields);
            return back()->with('message', 'Profile picture updated successfully.');
        } else {
            return back()->with('error', 'No profile picture selected.');
        }
    }

    /**
     * Delete a user and their associated todos.
     *
     * @param int $id The ID of the user to delete
     * @return \Illuminate\Http\JsonResponse Returns the deletion response
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user is not found
     */
    public function delete_user($id)
    {
        $user = User::findOrFail($id);
        $response = DeletionService::delete(User::class, $id, 'User');
        $user->todos()->delete();
        return $response;
    }

    /**
     * Delete multiple users from the system.
     *
     * This method handles bulk deletion of users. It validates the incoming request
     * for user IDs, checks their existence, and performs the deletion operation
     * including associated todos.
     *
     * @param \Illuminate\Http\Request $request The request containing array of user IDs
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @return \Illuminate\Http\JsonResponse JSON response with:
     *         - error: boolean indicating if operation failed
     *         - message: status message
     *         - id: array of deleted user IDs
     *         - titles: array of deleted user names
     */
    public function delete_multiple_user(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:users,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedUsers = [];
        $deletedUserNames = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $user = User::findOrFail($id);
            if ($user) {
                $deletedUsers[] = $id;
                $deletedUserNames[] = $user->first_name . ' ' . $user->last_name;
                DeletionService::delete(User::class, $id, 'User');
                $user->todos()->delete();
            }
        }
        return response()->json(['error' => false, 'message' => 'User(s) deleted successfully.', 'id' => $deletedUsers, 'titles' => $deletedUserNames]);
    }

    /**
     * Logs out the currently authenticated user.
     *
     * This method handles logout for both web and client guards.
     * It performs the following actions:
     * - Logs out the user from their respective guard (web or client)
     * - Invalidates the current session
     * - Regenerates the CSRF token
     * - Redirects to home page with success message
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        if (Auth::guard('web')->check()) {
            auth('web')->logout();
        } else {
            auth('client')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('message', 'Logged out successfully.');
    }

    /**
     * Display the login page view.
     * 
     * This method returns the login form view located in the auth directory.
     * 
     * @return \Illuminate\View\View
     */
    public function login()
    {
        return view('auth.login');
    }

    /**
     * Authenticate user login attempt
     * 
     * Validates and authenticates user/client login credentials. Checks account status
     * and sets up session data on successful login.
     *
     * @param \Illuminate\Http\Request $request The HTTP request object containing login credentials
     * @return \Illuminate\Http\JsonResponse JSON response indicating authentication result
     *     - On success: {"error": false}
     *     - On failure: {"error": true, "message": "error message"}
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * Possible error messages:
     * - "Account not found!" - When email doesn't exist
     * - "Your account is currently inactive..." - When account is not active
     * - "Invalid credentials!" - When password doesn't match
     */
    public function authenticate(Request $request)
    {
        $formFields = $request->validate([
            'email' => ['required', 'email'],
            'password' => 'required'
        ]);
        if (!User::where('email', $formFields['email'])->first() && !Client::where('email', $formFields['email'])->first()) {
            return response()->json(['error' => true, 'message' => 'Account not found!']);
        }
        $logged_in = false;
        if (auth('web')->attempt($formFields)) {
            $user = auth('web')->user();
            if ($user->hasRole('admin') || $user->status == 1) {
                $logged_in = true;
            } else {
                return response()->json(['error' => true, 'message' => get_label('status_not_active', 'Your account is currently inactive. Please contact admin for assistance.')]);
            }
        }
        if (auth('client')->attempt($formFields)) {
            $user = auth('client')->user();
            if ($user->status == 1) {
                $logged_in = true;
            } else {
                return response()->json(['error' => true, 'message' => get_label('status_not_active', 'Your account is currently inactive. Please contact admin for assistance.')]);
            }
        }

        if ($logged_in) {
            $workspace_id = isset($user->workspaces[0]['id']) && !empty($user->workspaces[0]['id']) ? $user->workspaces[0]['id'] : 0;
            $my_locale = $locale = isset($user->lang) && !empty($user->lang) ? $user->lang : 'en';
            $data = ['user_id' => $user->id, 'workspace_id' => $workspace_id, 'my_locale' => $my_locale, 'locale' => $locale];
            session()->put($data);
            $request->session()->regenerate();

            Session::flash('message', 'Logged in successfully.');
            return response()->json(['error' => false]);
        } else {
            return response()->json(['error' => true, 'message' => 'Invalid credentials!']);
        }
    }

    /**
     * Display the user profile page with related data.
     *
     * This method retrieves user information along with associated workspace data,
     * including projects, tasks count, users and clients. The data shown varies
     * based on user permissions (admin/all data access).
     *
     * @param int $id The ID of the user to display
     * @return \Illuminate\View\View Returns the user profile view with:
     *                               - user: The requested user model
     *                               - projects: Collection of projects (filtered by permissions)
     *                               - tasks: Count of tasks (filtered by permissions)
     *                               - users: Collection of workspace users
     *                               - clients: Collection of workspace clients
     *                               - auth_user: Currently authenticated user
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user is not found
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        $workspace = Workspace::find(session()->get('workspace_id'));
        $projects = isAdminOrHasAllDataAccess() ? $workspace->projects : $user->projects;
        $tasks = isAdminOrHasAllDataAccess() ? $workspace->tasks->count() : $user->tasks->count();
        $users = $workspace->users;
        $clients = $workspace->clients;

        return view('users.user_profile', ['user' => $user, 'projects' => $projects, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients, 'auth_user' => getAuthenticatedUser()]);
    }
    
        /**
         * Formats a time duration from seconds into HH:MM:SS format.
         *
         * @param int $seconds The number of seconds to format
         * @return string The formatted time string in HH:MM:SS format
         */
        public function format_time($seconds)
    {
        return sprintf("%02d%s%02d%s%02d", floor($seconds / 3600), ':', ($seconds / 60) % 60, ':', $seconds % 60);
    }

    /**
     * Retrieves and formats a list of users for the current workspace.
     * 
     * This method handles the following functionalities:
     * - Retrieves users based on the current workspace
     * - Implements search functionality across user fields (first_name, last_name, phone, email)
     * - Supports sorting and ordering of results
     * - Paginates results based on requested limit
     * - Formats user data including:
     *   - Basic user information
     *   - Role with styled badge
     *   - Avatar/photo display
     *   - Task and project counts
     *   - Status and timestamps
     *   - Time spent on tasks
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of formatted user data
     *         - total: Total count of users matching criteria
     */
    public function list()
    {
        $workspace = Workspace::find(session()->get('workspace_id'));
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $users = $workspace->users();
        $users = $users->when($search, function ($query) use ($search) {
            return $query->where('first_name', 'like', '%' . $search . '%')
                ->orWhere('last_name', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%');
        });

        $totalusers = $users->count();
        $users = $users->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(fn ($user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => "<span class='badge bg-label-" . (isset(config('taskhub.role_labels')[$user->getRoleNames()->first()]) ? config('taskhub.role_labels')[$user->getRoleNames()->first()] : config('taskhub.role_labels')['default']) . " me-1'>" . $user->getRoleNames()->first() . "</span>",
                'email' => $user->email,
                'phone' => $user->phone,
                'photo' => "<div class='avatar avatar-md pull-up' title='" . $user->first_name . " " . $user->last_name . "'>
                    <a href='/users/profile/" . $user->id . "'>
                    <img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>
                    </a>
                    </div>",
                'tasks' => isAdminOrHasAllDataAccess('user',$user->id) ? count($workspace->tasks) : count($user->tasks),
                'projects' => isAdminOrHasAllDataAccess('user',$user->id) ? count($workspace->projects) : count($user->projects),
                'status' => $user->status,
                'created_at' => format_date($user->created_at, 'H:i:s'),
                'updated_at' => format_date($user->updated_at, 'H:i:s'),
                'timeSpent' => $this->format_time($user->tasks()->sum(DB::raw("TIME_TO_SEC(time_spent)"))),
            ]);

        return response()->json([
            "rows" => $users->items(),
            "total" => $totalusers,
        ]);
    }
}

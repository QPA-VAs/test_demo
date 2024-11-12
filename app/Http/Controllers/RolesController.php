<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Artisan;

class RolesController extends Controller
{
    /**
     * Display all roles in the permission settings page
     *
     * Retrieves all roles from the database and passes them to the permission settings view
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $roles = Role::all();
        return view('settings.permission_settings', ['roles' => $roles]);
    }

    /**
     * Display the create role form with available permissions.
     *
     * Retrieves all permissions from database filtered by category (projects, tasks, users, clients)
     * and sorted alphabetically by name. These permissions are then passed to the view
     * for displaying the role creation form.
     *
     * @return \Illuminate\View\View Returns view with categorized permissions data
     */
    public function create()
    {

        $projects = Permission::where('name', 'like', '%projects%')->get()->sortBy('name');
        $tasks = Permission::where('name', 'like', '%tasks%')->get()->sortBy('name');
        $users = Permission::where('name', 'like', '%users%')->get()->sortBy('name');
        $clients = Permission::where('name', 'like', '%clients%')->get()->sortBy('name');
        return view('roles.create_role', ['projects' => $projects, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients]);
    }

    /**
     * Store a newly created role in storage.
     *
     * This method creates a new role with the provided name and assigns selected permissions.
     * It also clears the application cache after creation.
     *
     * @param  \Illuminate\Http\Request  $request Request containing role data
     *      - name: string (required) The name of the role
     *      - permissions: array The permissions to assign to the role
     * 
     * @return \Illuminate\Http\JsonResponse JSON response indicating success/failure
     *      - error: boolean False if successful
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'name' => ['required']
        ]);

        $formFields['guard_name'] = 'web';

        $role = Role::create($formFields);
        $filteredPermissions = array_filter($request->input('permissions'), function ($permission) {
            return $permission != 0;
        });
        $role->permissions()->sync($filteredPermissions);
        Artisan::call('cache:clear');

        Session::flash('message', 'Role created successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Show the form for editing the specified role.
     *
     * @param int $id The ID of the role
     * @return \Illuminate\View\View Returns the edit role view with role data, permissions, guard name and authenticated user
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When role is not found
     */
    public function edit($id)
    {

        $role = Role::findOrFail($id);
        $role_permissions = $role->permissions;
        $guard = $role->guard_name == 'client' ? 'client' : 'web';
        return view('roles.edit_role', ['role' => $role, 'role_permissions' => $role_permissions, 'guard' => $guard, 'user' => getAuthenticatedUser()]);
    }

    /**
     * Update the specified role in the database.
     *
     * This method updates a role's name and its associated permissions.
     * After updating, it clears the application cache and sets a success message.
     *
     * @param  \Illuminate\Http\Request  $request  The HTTP request containing role data
     * @param  int  $id  The ID of the role to update
     * @return \Illuminate\Http\JsonResponse  JSON response indicating success
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException  When role is not found
     */
    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'name' => ['required']
        ]);
        $role = Role::findOrFail($id);
        $role->name = $formFields['name'];
        $role->save();
        $filteredPermissions = array_filter($request->input('permissions'), function ($permission) {
            return $permission != 0;
        });
        $role->permissions()->sync($filteredPermissions);

        Artisan::call('cache:clear');

        Session::flash('message', 'Role updated successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function destroy($id)
    {
        $response = DeletionService::delete(Role::class, $id, 'Role');
        return $response;
    }

    /**
     * Creates a new permission in the system.
     * 
     * This method specifically creates a permission for editing projects
     * with 'client' as the guard name. The permission is stored in the
     * permissions table using Spatie's Permission package.
     *
     * @return void
     */
    public function create_permission()
    {
        // $createProjectsPermission = Permission::findOrCreate('create_tasks', 'client');
        Permission::create(['name' => 'edit_projects', 'guard_name' => 'client']);
    }
}

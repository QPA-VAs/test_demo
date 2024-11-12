<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Profile;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the user profile page with role information.
     * 
     * Retrieves all available roles from the database and displays the account view
     * with the authenticated user's information and role options.
     * 
     * @return \Illuminate\View\View Returns the account view with user and roles data
     */
    public function show()
    {
        $roles = Role::all();
        return view('users.account', ['user' => getAuthenticatedUser(), 'roles' => $roles]);
    }

    /**
     * Update the user or client profile information.
     *
     * This method handles the update of profile details including personal information,
     * contact details, and password if provided. It supports both User and Client models.
     *
     * @param  \Illuminate\Http\Request  $request The HTTP request containing profile data
     * @param  int  $id The ID of the user or client to update
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user/client not found
     * 
     * Validation Rules:
     * - first_name: required
     * - last_name: required
     * - phone: required
     * - role: required
     * - address: required
     * - city: required
     * - state: required
     * - country: required
     * - zip: required
     * - password: optional, minimum 6 characters
     * - password_confirmation: required if password is set, must match password
     * - email: required and unique (for admin users only)
     */
    public function update(Request $request, $id)
    {

        $rules = [
            'first_name' => ['required'],
            'last_name' => ['required'],
            'phone' => 'required',
            'role' => 'required',
            'address' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'zip' => 'required',
            'password' => 'nullable|min:6',
            'password_confirmation' => 'nullable|required_with:password|same:password',
        ];

        if (isAdminOrHasAllDataAccess()) {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ];
        }

        $formFields = $request->validate($rules);
        if (isset($formFields['password']) && !empty($formFields['password'])) {
            $formFields['password'] = bcrypt($formFields['password']);
        } else {
            unset($formFields['password']);
        }


        $user = isUser() ? User::findOrFail($id) : Client::findOrFail($id);
        $user->update($formFields);
        $user->syncRoles($request->input('role'));

        Session::flash('message', 'Profile details updated successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Updates the user's profile photo
     * 
     * @param \Illuminate\Http\Request $request The HTTP request instance
     * @param int $id The ID of the user or client
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success or failure
     * 
     * This method handles profile photo uploads for both users and clients:
     * - Validates if a file was uploaded
     * - Deletes the old photo if it exists (except default photo)
     * - Stores the new photo in public storage
     * - Updates the user/client record with new photo path
     * - Returns JSON response with success/error status
     */
    public function update_photo(Request $request, $id)
    {
        if ($request->hasFile('upload')) {
            $user = isUser() ? User::findOrFail($id) : Client::findOrFail($id);
            if ($user->photo != 'photos/no-image.jpg' && $user->photo !== null)
                Storage::disk('public')->delete($user->photo);
            $formFields['photo'] = $request->file('upload')->store('photos', 'public');
            $user->update($formFields);

            Session::flash('message', 'Profile picture updated successfully.');
            return response()->json(['error' => false]);
        } else {
            return response()->json(['error' => true, 'message' => 'No profile picture selected!']);
        }
    }

    /**
     * Delete the authenticated user account and related data.
     *
     * This method deletes the user account (either User or Client) along with their associated todos.
     * Uses DeletionService to handle the deletion process and logging.
     *
     * @param  int  $id The ID of the user/client to be deleted
     * @return \Illuminate\Http\RedirectResponse Redirects to homepage after successful deletion
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If user/client not found
     */
    public function destroy($id)
    {
        $user = isUser() ? User::findOrFail($id) : Client::findOrFail($id);
        isUser() ? DeletionService::delete(User::class, $id, 'Account') : DeletionService::delete(Client::class, $id, 'Account');
        $user->todos()->delete();
        return redirect('/');
    }
}

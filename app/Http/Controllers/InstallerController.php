<?php

namespace App\Http\Controllers;

use PDO;
use PDOException;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

/**
 * InstallerController handles the installation process of the application.
 * 
 * This controller manages database configuration, user setup, and initial workspace creation.
 * It includes functionality for:
 * - Checking and creating storage symbolic links
 * - Clearing application caches
 * - Configuring database connection
 * - Installing the application with initial data
 * - Creating the first admin user
 * - Setting up the default workspace
 * 
 * @package App\Http\Controllers
 */
class InstallerController extends Controller
{
    /**
     * Handles the installation process for the application.
     * 
     * This method checks if the installation view exists and attempts to:
     * 1. Create a symbolic link between public and storage directories if symlink function is available
     * 2. Clear application cache and return to installation view
     * 
     * If the installation view doesn't exist, redirects to home page.
     *
     * @return \Illuminate\Http\Response Returns either the install view or redirects to home
     * @throws \Exception May throw exceptions during symlink creation (caught internally)
     */
    public function index()
    {
        $installViewPath = resource_path('views/install.blade.php');
        if (File::exists($installViewPath)) {
            // Check if the symlink function is available
            if (function_exists('symlink')) {
                try {
                    // Attempt to create the symbolic link between the public directory and the storage directory
                    Artisan::call('storage:link');
                } catch (\Exception $e) {
                    // Log or handle the exception (if needed)
                }
            }
            // Regardless of whether the symlink function is available or not, proceed to clear cache and return to the install view
            return $this->clearAndReturnToInstallView();
        } else {
            return redirect('/');
        }
    }

    /**
     * Clears application caches and returns to the installation view.
     * 
     * This method performs the following cache clearing operations:
     * - Clears the application cache
     * - Clears the configuration cache
     * - Clears the route cache
     * - Clears the view cache
     * 
     * @return \Illuminate\View\View Returns the installation view
     */
    private function clearAndReturnToInstallView()
    {
        // Clear cache
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Return install view
        return view('install');
    }

    /**
     * Configure and test database connection with provided credentials
     * 
     * This method validates database connection credentials, tests the connection,
     * and updates the .env file with the new database configuration if successful.
     *
     * @param  \Illuminate\Http\Request  $request Request containing database credentials
     * @return \Illuminate\Http\JsonResponse Json response with connection status
     * 
     * @throws \PDOException When database connection fails
     * 
     * Required request parameters:
     * - db_name: Database name
     * - db_host_name: Database host
     * - db_user_name: Database username
     * Optional request parameters:
     * - db_password: Database password
     */
    public function config_db(Request $request)
    {
        $formFields = $request->validate([
            'db_name' => ['required'],
            'db_host_name' => ['required'],
            'db_user_name' => ['required'],
            'db_password' => 'nullable',
        ]);

        try {
            // Replace these values with your actual database configuration
            $pdo = new PDO("mysql:host={$formFields['db_host_name']};dbname={$formFields['db_name']}", $formFields['db_user_name'], $formFields['db_password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $envFilePath = base_path('.env');
            $envContent = file_get_contents($envFilePath);

            $envContent = preg_replace('/^DB_HOST=.*$/m', "DB_HOST={$formFields['db_host_name']}", $envContent);
            $envContent = preg_replace('/^DB_DATABASE=.*$/m', "DB_DATABASE={$formFields['db_name']}", $envContent);
            $envContent = preg_replace('/^DB_USERNAME=.*$/m', "DB_USERNAME={$formFields['db_user_name']}", $envContent);
            $envContent = preg_replace('/^DB_PASSWORD=.*$/m', "DB_PASSWORD={$formFields['db_password']}", $envContent);
            file_put_contents($envFilePath, $envContent);
            return response()->json(['error' => false, 'message' => 'Connected to the database successfully.']);
        } catch (PDOException $e) {
            return response()->json(['error' => true, 'message' => "Connection failed: " . $e->getMessage()]);
        }
    }

    /**
     * Handles the installation process of the application.
     *
     * This method performs the following steps:
     * 1. Sets maximum execution time to 900 seconds
     * 2. Validates user input fields (first name, last name, email, password)
     * 3. Creates a new user with administrative privileges
     * 4. Creates a default workspace for the user
     * 5. Imports database schema from SQL dump file
     * 6. Cleans up installation files
     * 7. Clears various Laravel caches
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing user details
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success or failure
     *   - On success: {'error': false, 'message': 'Congratulations! Installation completed successfully.'}
     *   - On failure: {'error': true, 'message': 'Oops! Installation failed. Please try again.'}
     *   - If SQL file missing: {'error': true, 'message': 'Oops! Installation couldn\'t process.'}
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Exception When database operations fail
     */
    public function install(Request $request)
    {
        ini_set('max_execution_time', 900);

        // Validate user-related fields
        $userFields = $request->validate([
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
            'password' => 'required|min:6|confirmed'
        ]);

        // Hash the password
        $userFields['password'] = Hash::make($userFields['password']);
        $userFields['status'] = 1;
        Artisan::call('config:cache');
        Artisan::call('config:clear');
        DB::purge('mysql');

        // Import the SQL dump file
        $installViewPath = resource_path('views/install.blade.php');
        $sqlDumpPath = base_path('taskify.sql');
        if (file_exists($sqlDumpPath)) {
            $sql = file_get_contents($sqlDumpPath);
            DB::unprepared($sql);


            $user = User::create($userFields);
            if ($user) {
                $user->assignRole(1);

                $workspaceFields = [
                    'user_id' => $user->id,
                    'title' => 'Default Workspace'
                ];

                $new_workspace = Workspace::create($workspaceFields);

                $workspace_id = $new_workspace->id;
                $workspace = Workspace::find($workspace_id);
                $workspace->users()->attach([$user->id]);

                File::delete($installViewPath);
                unlink($sqlDumpPath);

                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');

                return response()->json(['error' => false, 'message' => 'Congratulations! Installation completed successfully.']);
            } else {
                return response()->json(['error' => true, 'message' => 'Oops! Installation failed. Please try again.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'Oops! Installation couldn\'t process.']);
        }
    }
}

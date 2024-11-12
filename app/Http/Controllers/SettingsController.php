<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Display the general settings page.
     *
     * Retrieves a list of available timezones and passes it to the view.
     * The timezones are obtained using the get_timezone_array() helper function.
     *
     * @return \Illuminate\View\View The view containing the general settings form with timezone options
     */
    public function index()
    {
        $timezones = get_timezone_array();
        return view('settings.general_settings', compact('timezones'));
    }

    /**
     * Displays the Pusher settings view.
     * 
     * @return \Illuminate\View\View Returns the pusher settings page view
     */
    public function pusher()
    {
        return view('settings.pusher_settings');
    }

    /**
     * Display the email settings view.
     *
     * @return \Illuminate\View\View
     */
    public function email()
    {
        return view('settings.email_settings');
    }

    /**
     * Display the media storage settings page.
     * 
     * This method renders the view for managing media storage configurations.
     * 
     * @return \Illuminate\View\View Returns the media storage settings view
     */
    public function media_storage()
    {
        return view('settings.media_storage_settings');
    }

    /**
     * Store general settings in the database.
     * 
     * This method handles the storage and update of general application settings including:
     * - Company information (title)
     * - Localization settings (timezone, currency, date format)
     * - Logo management (full logo, half logo, favicon)
     * 
     * The method performs the following operations:
     * - Validates required input fields
     * - Manages file uploads for logos and favicon
     * - Stores settings in JSON format
     * - Updates session with new date format
     * 
     * @param \Illuminate\Http\Request $request The HTTP request containing form data and files
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success or failure
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store_general_settings(Request $request)
    {
        $request->validate([
            'company_title' => ['required'],
            'timezone' => ['required'],
            'currency_full_form' => ['required'],
            'currency_symbol' => ['required'],
            'currency_code' => ['required'],
            'date_format' => ['required']
        ]);
        $settings = [];
        $fetched_data = Setting::where('variable', 'general_settings')->first();
        if ($fetched_data != null) {
            $settings = json_decode($fetched_data->value, true);
        }
        $form_val = $request->except('_token', '_method', 'redirect_url');
        $old_logo = isset($settings['full_logo']) && !empty($settings['full_logo']) ? $settings['full_logo'] : '';
        if ($request->hasFile('full_logo')) {
            Storage::disk('public')->delete($old_logo);
            $form_val['full_logo'] = $request->file('full_logo')->store('logos', 'public');
        } else {
            $form_val['full_logo'] = $old_logo;
        }

        $old_half_logo = isset($settings['half_logo']) && !empty($settings['half_logo']) ? $settings['half_logo'] : '';
        if ($request->hasFile('half_logo')) {
            Storage::disk('public')->delete($old_half_logo);
            $form_val['half_logo'] = $request->file('half_logo')->store('logos', 'public');
        } else {
            $form_val['half_logo'] = $old_half_logo;
        }

        $old_favicon = isset($settings['favicon']) && !empty($settings['favicon']) ? $settings['favicon'] : '';
        if ($request->hasFile('favicon')) {
            Storage::disk('public')->delete($old_favicon);
            $form_val['favicon'] = $request->file('favicon')->store('logos', 'public');
        } else {
            $form_val['favicon'] = $old_favicon;
        }
        $data = [
            'variable' => 'general_settings',
            'value' => json_encode($form_val),
        ];

        if ($fetched_data == null) {
            Setting::create($data);
        } else {
            Setting::where('variable', 'general_settings')->update($data);
        }
        session()->put('date_format', $request->input('date_format'));

        Session::flash('message', 'Settings saved successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Store Pusher settings in the database.
     *
     * This method handles the storage of Pusher configuration settings. It validates
     * the required fields for Pusher integration, processes the form data, and saves
     * it in the settings table as a JSON encoded string.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing Pusher settings
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store_pusher_settings(Request $request)
    {
        $request->validate([
            'pusher_app_id' => ['required'],
            'pusher_app_key' => ['required'],
            'pusher_app_secret' => ['required'],
            'pusher_app_cluster' => ['required']
        ]);
        $fetched_data = Setting::where('variable', 'pusher_settings')->first();
        $form_val = $request->except('_token', '_method', 'redirect_url');
        $data = [
            'variable' => 'pusher_settings',
            'value' => json_encode($form_val),
        ];

        if ($fetched_data == null) {
            Setting::create($data);
        } else {
            Setting::where('variable', 'pusher_settings')->update($data);
        }

        Session::flash('message', 'Settings saved successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Store email configuration settings in the database.
     *
     * This method handles the storage and update of SMTP email settings including:
     * - Email address
     * - Password
     * - SMTP host
     * - SMTP port
     * - Email content type
     * - SMTP encryption type
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing email settings
     * @return \Illuminate\Http\JsonResponse JSON response indicating success/failure
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function store_email_settings(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'smtp_host' => ['required'],
            'smtp_port' => ['required'],
            'email_content_type' => ['required'],
            'smtp_encryption' => ['required']
        ]);
        $fetched_data = Setting::where('variable', 'email_settings')->first();
        $form_val = $request->except('_token', '_method', 'redirect_url');
        $data = [
            'variable' => 'email_settings',
            'value' => json_encode($form_val),
        ];

        if ($fetched_data == null) {
            Setting::create($data);
        } else {
            Setting::where('variable', 'email_settings')->update($data);
        }
        Session::flash('message', 'Settings saved successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Store media storage settings in the database.
     * 
     * This method handles the storage of media storage configuration settings,
     * supporting both local and S3 storage options. It validates the input data
     * based on the selected storage type and saves the settings in JSON format.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     *
     * Validation Rules:
     * - media_storage_type: Required, must be either 'local' or 's3'
     * - s3_key: Required if storage type is 's3'
     * - s3_secret: Required if storage type is 's3'
     * - s3_region: Required if storage type is 's3'
     * - s3_bucket: Required if storage type is 's3'
     */
    public function store_media_storage_settings(Request $request)
    {
        $request->validate([
            'media_storage_type' => 'required|in:local,s3',
            's3_key' => $request->input('media_storage_type') === 's3' ? 'required' : 'nullable',
            's3_secret' => $request->input('media_storage_type') === 's3' ? 'required' : 'nullable',
            's3_region' => $request->input('media_storage_type') === 's3' ? 'required' : 'nullable',
            's3_bucket' => $request->input('media_storage_type') === 's3' ? 'required' : 'nullable',
        ]);
        $fetched_data = Setting::where('variable', 'media_storage_settings')->first();
        $form_val = $request->except('_token', '_method', 'redirect_url');
        $data = [
            'variable' => 'media_storage_settings',
            'value' => json_encode($form_val),
        ];

        if ($fetched_data == null) {
            Setting::create($data);
        } else {
            Setting::where('variable', 'media_storage_settings')->update($data);
        }
        Session::flash('message', 'Settings saved successfully.');
        return response()->json(['error' => false]);
    }
}

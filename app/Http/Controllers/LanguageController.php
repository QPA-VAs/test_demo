<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    protected $user;
    /**
     * Create a new LanguageController instance.
     * Sets up middleware to authenticate and fetch the current user.
     * 
     * The middleware runs before any controller action and:
     * - Gets the authenticated user
     * - Makes user data available throughout the controller via $this->user
     */
    public function __construct()
    {

        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }
    /**
     * Display the language settings page.
     * 
     * Retrieves the user's default language setting and passes it to the view
     * for displaying the language preferences interface.
     * 
     * @return \Illuminate\View\View Returns the languages view with the user's default language
     */
    public function index()
    {
        $default_language = $this->user->lang;
        return view('settings.languages', compact('default_language'));
    }

    /**
     * Show the form for creating a new language.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {

        return view('languages.create_language');
    }

    /**
     * Store a newly created language in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     * 
     * The request must contain:
     * @property string $name The name of the language
     * @property string $code The unique code identifier for the language
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'name' => ['required'],
            'code' => ['required', 'unique:languages,code']

        ]);

        if (language::create($formFields)) {
            Session::flash('message', 'Language created successfully.');
            return response()->json(['error' => false]);
        } else {
        }
    }

    /**
     * Saves language labels to a file.
     *
     * This method processes the incoming request data to create or update language label files.
     * It strips HTML tags from the values and generates a PHP array of language labels,
     * which is then saved to a file in the appropriate language directory.
     *
     * @param Request $request The HTTP request containing the language labels data
     * @param Language $lang The language model instance (currently unused)
     * @return JsonResponse Returns JSON response indicating success
     *
     * @throws \Exception If there's an error creating directory or writing file
     *
     * The method:
     * 1. Processes form data excluding token and method
     * 2. Strips HTML tags from label values
     * 3. Creates language directory if it doesn't exist
     * 4. Writes labels to a PHP file in the format: 'key' => 'value'
     * 5. Sets a success flash message
     */
    public function save_labels(Request $request, Language $lang)
    {
        $data = $request->except(["_token", "_method"]);

        $langstr = '';

        foreach ($data as $key => $value) {
            $label_data =  strip_tags($value);
            $label_key = $key;
            $langstr .= "'" . $label_key . "' => '$label_data'," . "\n";
        }
        $langstr_final = "<?php return [" . "\n\n\n" . $langstr . "];";

        $root = base_path("/resources/lang");
        $dir = $root . '/' . $request->langcode;

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = $dir . '\labels.php';

        file_put_contents($filename, $langstr_final);

        Session::flash('message', 'Language labels saved successfully.');
        return response()->json(['error' => false]);
    }

    /**
     * Changes the application's current language.
     *
     * This method updates the session locale based on the provided language code
     * and redirects the user back to the languages settings page.
     *
     * @param string $code The language code to switch to (e.g., 'en', 'es', 'fr')
     * @return \Illuminate\Http\RedirectResponse Redirects to the languages settings page
     */
    public function change($code)
    {
        // app()->setLocale($code);
        session()->put('locale', $code);
        return redirect('/settings/languages');
    }

    /**
     * Switch the application's language.
     *
     * This method changes the current locale by storing it in the session.
     * After switching the language, it redirects back to the previous page
     * with a success message.
     *
     * @param string $locale The language code to switch to (e.g., 'en', 'es', 'fr')
     * @return \Illuminate\Http\RedirectResponse Redirects back to the previous page
     */
    public function switch($locale)
    {
        session(['my_locale' => $locale]);

        return redirect()->back()->with('message', 'Language switched successfully.');
    }

    /**
     * Sets the default language for the authenticated user.
     *
     * This method updates the user's primary language preference and sets
     * the corresponding session variables for locale.
     *
     * @param \Illuminate\Http\Request $request The request object containing the language code
     * @return \Illuminate\Http\JsonResponse Returns JSON response indicating success or failure
     *                                      - On success: ['error' => false]
     *                                      - On failure: ['error' => true, 'message' => error_message]
     * @throws \Illuminate\Validation\ValidationException When validation fails
     */
    public function set_default(Request $request)
    {
        $formFields = $request->validate([
            'lang' => ['required']

        ]);
        $locale = $request->lang;
        if (Language::where('code', '=', $locale)->exists()) {
            $this->user->lang = $locale;
            if ($this->user->save()) {
                session(['my_locale' => $locale, 'locale' => $locale]);
                Session::flash('message', 'Primary language set successfully.');
                return response()->json(['error' => false]);
            } else {
                return response()->json(['error' => true, 'message' => 'Primary language couldn\'t set.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'Invalid language.']);
        }
    }
}

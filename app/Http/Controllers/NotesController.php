<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Services\DeletionService;

class NotesController extends Controller
{
    protected $workspace;
    protected $user;
    /**
     * Constructor for NotesController
     * 
     * Initializes middleware that:
     * - Sets the current workspace based on workspace_id stored in session
     * - Sets authenticated user for access throughout controller
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
     * Display a listing of user's notes.
     * 
     * This method retrieves all notes associated with the authenticated user
     * and returns them to the notes list view.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $notes = $this->user->notes();
        return view('notes.list', ['notes' => $notes]);
    }

    /**
     * Store a newly created note in the database.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * Request body should contain:
     * @property string $title Required. The title of the note
     * @property string $color Required. The color code for the note
     * @property string|null $description Optional. The description of the note
     * 
     * Success Response:
     * {
     *   "error": false,
     *   "id": integer
     * }
     * 
     * Error Response:
     * {
     *   "error": true,
     *   "message": string
     * }
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'color' => ['required'],
            'description' => ['nullable']
        ]);
        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['creator_id'] = isClient() ? 'c_' . $this->user->id : 'u_' . $this->user->id;

        if ($note = Note::create($formFields)) {
            Session::flash('message', 'Note created successfully.');
            return response()->json(['error' => false, 'id' => $note->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Note couldn\'t created.']);
        }
    }

    /**
     * Update an existing note in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When note with given id is not found
     * 
     * Request body should contain:
     * @property integer $id The ID of the note to update
     * @property string  $title The title of the note
     * @property string  $color The color of the note
     * @property string|null  $description The description of the note (optional)
     * 
     * Returns JSON response:
     * - On success: { "error": false, "id": note_id }
     * - On failure: { "error": true, "message": error_message }
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'title' => ['required'],
            'color' => ['required'],
            'description' => ['nullable']
        ]);
        $note = Note::findOrFail($request->id);

        if ($note->update($formFields)) {
            Session::flash('message', 'Note updated successfully.');
            return response()->json(['error' => false, 'id' => $note->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Note couldn\'t updated.']);
        }
    }

    /**
     * Retrieve a specific note by ID
     * 
     * @param int $id The ID of the note to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the note
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When note is not found
     */
    public function get($id)
    {
        $note = Note::findOrFail($id);
        return response()->json(['note' => $note]);
    }



    /**
     * Delete a specific note from the database
     *
     * @param int $id The ID of the note to delete
     * @return \Illuminate\Http\JsonResponse Returns JSON response with success/error message
     * @throws \Exception If note deletion fails
     */
    public function destroy($id)
    {
        $response = DeletionService::delete(Note::class, $id, 'Note');
        return $response;
    }
}

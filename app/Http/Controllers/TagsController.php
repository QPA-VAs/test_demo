<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class TagsController extends Controller
{
    /**
     * Display a listing of all tags.
     *
     * @return \Illuminate\View\View Returns the tags list view
     */
    public function index()
    {
        return view('tags.list');
    }

    /**
     * Store a newly created tag in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * 
     * This method validates the incoming request for required 'title' and 'color' fields,
     * generates a unique slug based on the title, creates a new Tag record,
     * and returns a JSON response with the created tag's ID.
     * Also sets a flash message for successful tag creation.
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'color' => ['required']
        ]);
        $slug = generateUniqueSlug($request->title, Tag::class);
        $formFields['slug'] = $slug;
        $tag = Tag::create($formFields);

        Session::flash('message', 'Tag created successfully.');
        return response()->json(['error' => false, 'message' => 'Tag created successfully.', 'id' => $tag->id]);
    }

    /**
     * Retrieve and format a paginated list of tags.
     *
     * This method handles the following functionalities:
     * - Sorting tags by specified column and order
     * - Searching tags by title or ID
     * - Paginating results
     * - Formatting tag data for display
     *
     * @return \Illuminate\Http\JsonResponse JSON response containing:
     *         - rows: Array of formatted tag data including:
     *           - id: Tag ID
     *           - title: Tag title
     *           - color: HTML formatted color badge
     *           - created_at: Formatted creation timestamp
     *           - updated_at: Formatted update timestamp
     *         - total: Total number of tags matching the criteria
     */
    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $tags = Tag::orderBy($sort, $order); // or 'desc'

        if ($search) {
            $tags = $tags->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        $total = $tags->count();
        $tags = $tags
            ->paginate(request("limit"))
            ->through(
                fn ($tag) => [
                    'id' => $tag->id,
                    'title' => $tag->title,
                    'color' => '<span class="badge bg-' . $tag->color . '">' . $tag->title . '</span>',
                    'created_at' => format_date($tag->created_at,  'H:i:s'),
                    'updated_at' => format_date($tag->updated_at, 'H:i:s'),
                ]
            );


        return response()->json([
            "rows" => $tags->items(),
            "total" => $total,
        ]);
    }

    /**
     * Retrieves a specific tag by its ID
     *
     * @param int $id The ID of the tag to retrieve
     * @return \Illuminate\Http\JsonResponse JSON response containing the tag data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When tag is not found
     */
    public function get($id)
    {
        $tag = Tag::findOrFail($id);
        return response()->json(['tag' => $tag]);
    }

    /**
     * Update the specified tag in storage.
     *
     * @param \Illuminate\Http\Request $request The request object containing tag data
     * @return \Illuminate\Http\JsonResponse Returns JSON response with status and message
     *
     * @throws \Illuminate\Validation\ValidationException When validation fails
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When tag is not found
     *
     * Request body parameters:
     * @param int    $id    The ID of the tag to update
     * @param string $title The new title for the tag
     * @param string $color The new color for the tag
     *
     * Response format:
     * {
     *     "error": boolean,
     *     "message": string,
     *     "id": int|null
     * }
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => ['required'],
            'title' => ['required'],
            'color' => ['required']
        ]);
        $slug = generateUniqueSlug($request->title, Tag::class, $request->id);
        $formFields['slug'] = $slug;

        $tag = Tag::findOrFail($request->id);

        if ($tag->update($formFields)) {
            return response()->json(['error' => false, 'message' => 'Tag updated successfully.', 'id' => $tag->id]);
        } else {
            return response()->json(['error' => true, 'message' => 'Tag couldn\'t updated.']);
        }
    }

    /**
     * Retrieves all tag titles as suggestions.
     *
     * This method fetches all tag titles from the Tag model and returns them as a JSON response.
     * It's typically used for providing tag suggestions in autocomplete or similar features.
     *
     * @return \Illuminate\Http\JsonResponse A JSON response containing an array of tag titles
     */
    public function get_suggestions()
    {
        $tags = Tag::pluck('title');
        return response()->json($tags);
    }

    /**
     * Retrieve tag IDs based on provided tag names
     * 
     * @param Request $request The HTTP request containing tag names
     * @return JsonResponse Returns JSON response with an array of tag IDs
     *
     * @throws None
     *
     * Expected request format:
     * {
     *     "tag_names": ["tag1", "tag2", ...]  
     * }
     *
     * Response format:
     * {
     *     "tag_ids": [1, 2, ...]
     * }
     */
    public function get_ids(Request $request)
    {
        $tagNames = $request->input('tag_names');
        $tagIds = Tag::whereIn('title', $tagNames)->pluck('id')->toArray();
        return response()->json(['tag_ids' => $tagIds]);
    }

    public function destroy($id)
    {
        $response = DeletionService::delete(Tag::class, $id, 'Tag');
        return $response;
    }
    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:tags,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $tag = Tag::findOrFail($id);
            $deletedIds[] = $id;
            $deletedTitles[] = $tag->title;
            DeletionService::delete(Tag::class, $id, 'Tag');
        }

        return response()->json(['error' => false, 'message' => 'Tag(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles]);
    }
}

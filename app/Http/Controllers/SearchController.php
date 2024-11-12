<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\Request;
use ProtoneMedia\LaravelCrossEloquentSearch\Search;

class SearchController extends Controller
{
    /**
     * Performs a global search across multiple models based on user input.
     *
     * This method searches through Projects, Tasks, Users, Clients, Meetings and Workspaces
     * using the provided query string. The search is performed on the 'title' field for most models
     * and 'first_name' for User and Client models.
     *
     * @param \Illuminate\Http\Request $request The HTTP request object containing the search query
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Returns either:
     *                                                                 - search view with paginated results and query
     *                                                                 - redirect to home with error message if query is empty
     */
    public function search(Request $request)
    {
        $query = $request->input('query');
        if ($query) {
            if (!$query) {
                $results = collect([]);
                return view('search', ['results' => $results, 'query' => $query]);
            } else {
                $results = Search::addMany([
                    [Project::class, 'title'],
                    [Task::class, 'title'],
                    [User::class, 'first_name'],
                    [Client::class, 'first_name'],
                    [Meeting::class, 'title'],
                    [Workspace::class, 'title']
                ])
                    ->paginate(10)
                    ->beginWithWildcard()
                    ->search($query);
                return view('search', ['results' => $results, 'query' => $query]);
            }
        }else{
            return redirect('/home')->with('error','Please enter search keyword.');
        }
    }
}

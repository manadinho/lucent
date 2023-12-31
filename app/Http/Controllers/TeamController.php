<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Class TeamController
 * @package App\Http\Controllers\TeamController
 * 
 * @author Muhammad Imran Israr (mimranisrar6@gmail.com)
 */
class TeamController extends Controller
{
    private $user;

    /**
     * Display the user's teams on the index page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View 
    {  
        $this->user = auth()->user();

        $user = User::where('id', $this->user->id)->with('teams', function($q){
            return $q->withCount('users', 'projects');
        })->first();

        return view('team.index', ['teams' => $user->teams]);
    }

    /**
     * Create a new team and associate the logged-in user as the owner.
     *
     * This function validates the input data, creates a new team, assigns the logged-in user as the owner,
     * and then redirects to the teams index page with a success message.
     *
     * @return \Illuminate\Http\RedirectResponse Redirects to the teams index page with a success message.
     */
    public function create()
    {
        $this->user = auth()->user();

        request()->validate([
            'name' => 'required'
        ]);

        // create new team
        $team = Team::updateOrCreate(['id' => request()->id], [
            'name' => $this->getUniqueName(request()->name),
            'user_id' => $this->user->id

        ]);

        if(!request()->id) {
            // add loggedin user as owner of the team
            $team->createTeamUser([
                $this->user->id => ['role' => 'owner']
            ]);
        }

        $message = request()->id ? 'Team updated' : 'Team created';

        return back()->with(sendToast($message));
    }

    /**
     * Generate a unique name by appending a number to the input name if it already exists in the database.
     *
     * @param string $name The original name to check for uniqueness.
     * @return string The unique name.
     */
    private function getUniqueName(string $name): string
    {
        // TO CHECK IF UPDATE REQUEST AND USER IS NOT UPDATIONG NAME
        if (request()->id) {
            if(Team::find(request()->id)->name ===  $name) {
                return $name;
            }
        }

        $originalName = $name;

        $counter = 1;

        while (Team::where(['name' => $name, 'user_id' => $this->user->id])->exists()) {
            $name = $originalName . '-' . $counter;

            $counter++;
        }

        return $name;
    }

    /**
     * Delete a team.
     *
     * This function deletes the provided team after checking the current user's role in the team.
     *
     * @param Team $team The team to be deleted.
     * @return \Illuminate\Http\RedirectResponse Redirects to the teams index page with a success message.
     */
    public function delete(Team $team) 
    {
        $this->user = auth()->user();

        if ($team->isUserOwner($this->user->id)) {
            $team->delete();

            return redirect()->route('teams.index')->with(['toast' => true, 'status' => 'success', 'message' => 'Team deleted successfully.']);
        }

        return redirect()->route('teams.index')->with(['toast' => true, 'status' => 'error', 'message' => 'Sorry, You cannot delete this team.']);
    }

    /**
     * Display the members of a team.
     *
     * @param  Team  $team The team for which to display members.
     * @return View        The view displaying the team members.
     */
    public function members(Team $team): View
    {
        $team->load('users');

        $this->user = auth()->user();

        return view('team.members',[
            'members' => $team->users,
            'team' => $team,
            'isTeamOwner' => $team->isUserOwner($this->user->id)
        ]);
    }

    /**
     * Display the projects associated with a team.
     *
     * @param Team $team The team instance for which to display projects.
     * @return \Illuminate\Contracts\View\View The view displaying the team's projects.
     */
    public function projects(Team $team): View 
    {
        $team->load(['projects' => function($q){
            return $q->withCount('exceptions');
        }]);

        $this->user = auth()->user();
        
       return view('team.projects',[
        'projects' => $team->projects,
        'team' => $team,
        'isTeamOwner' => $team->isUserOwner($this->user->id)
       ]);
    }

}

<?php

namespace App\Http\Controllers\Firebase;

use App\Exceptions\RoleChangeException;
use App\Services\FirebaseRoleService;
use App\Services\RoleChangeService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Kreait\Firebase\Contract\Auth;
use Throwable;

class FirebaseAdminController extends Controller
{
    public function __construct(
        protected Auth $auth,
        protected FirebaseRoleService $roles,
        protected RoleChangeService $changes,
    ) {
    }

    /**
     * Display a list of users to manage (admins see the list; only owners see controls).
     */
    public function manageUsers()
    {
        try {
            // listUsers is lazy: fetch every page inside the failure boundary.
            $users = iterator_to_array($this->auth->listUsers(1000));
        } catch (Throwable $e) {
            return back()->with('error', 'Error fetching users.');
        }

        $viewerUid = session()->get('verified_user_id');
        $viewerIsOwner = $this->roles->isOwner($viewerUid);

        return view('admin.manage-users', compact('users', 'viewerUid', 'viewerIsOwner'));
    }

    /** Owner only (route middleware + service re-check). Merges claims; never overwrites. */
    public function makeAdmin(Request $request, $uid)
    {
        return $this->change(fn ($actor) => $this->changes->grantAdmin($actor, (string) $uid), 'Admin privileges granted.');
    }

    /** Owner only. Unsets only `admin`; owners are refused by the service. */
    public function removeAdmin(Request $request, $uid)
    {
        return $this->change(fn ($actor) => $this->changes->revokeAdmin($actor, (string) $uid), 'Admin privileges removed.');
    }

    /** Kept for callers; delegates to the authoritative service (owner implies admin). */
    public function isCurrentUserAdmin()
    {
        return $this->roles->isAdmin(session()->get('verified_user_id'));
    }

    private function change(callable $op, string $okMessage)
    {
        // Actor comes only from the authenticated web session, never from input.
        $actor = session()->get('verified_user_id');

        try {
            $op($actor);
        } catch (RoleChangeException $e) {
            if ($e->httpStatus === 403) {
                abort(403, $e->getMessage());
            }

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $okMessage);
    }
}

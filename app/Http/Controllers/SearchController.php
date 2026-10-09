<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ChildProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Header search, deliberately limited to what the viewer may already open:
 * children are scoped exactly like ChildProfileController::index() (admin =
 * whole organization, professional = only assigned cases), activities to the
 * viewer's own organization. Never searches clinical notes or responses.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', ChildProfile::class);

        $term = trim($request->string('q')->toString());
        $like = '%'.mb_strtolower($term).'%';
        $user = $request->user();

        $children = collect();
        $activities = collect();

        if ($term !== '') {
            $query = $user->isAdmin() ? $user->organization->childProfiles() : $user->assignedChildProfiles();
            $children = $query
                ->where(fn ($q) => $q->whereRaw('lower(first_name) like ?', [$like])->orWhereRaw('lower(preferred_name) like ?', [$like]))
                ->orderBy('first_name')
                ->limit(20)
                ->get(['child_profiles.id', 'first_name', 'preferred_name', 'visual_experience']);

            $activities = Activity::query()
                ->where('organization_id', $user->organization_id)
                ->whereRaw('lower(title) like ?', [$like])
                ->orderBy('title')
                ->limit(20)
                ->get(['id', 'title', 'status']);
        }

        return Inertia::render('Search/Index', [
            'q' => $term,
            'children' => $children,
            'activities' => $activities,
        ]);
    }
}

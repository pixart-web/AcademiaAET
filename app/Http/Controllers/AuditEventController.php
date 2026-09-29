<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditEventController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditEvent::class);

        $events = AuditEvent::query()
            ->where('organization_id', $request->user()->organization_id)
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "%{$action}%"))
            ->with('user:id,name')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'events' => $events,
            'filters' => ['action' => $request->string('action')->toString()],
        ]);
    }
}

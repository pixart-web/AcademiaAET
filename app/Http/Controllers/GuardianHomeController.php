<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuardianHomeController extends Controller
{
    /**
     * The guardian's own portal is out of scope for this stage of the project
     * (see spec Module A); this keeps a guardian login functional without
     * exposing anything beyond an explicit "not yet available" placeholder.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Guardian/Home');
    }
}

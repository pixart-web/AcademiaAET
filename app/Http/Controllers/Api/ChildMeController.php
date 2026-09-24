<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var ChildProfile $child */
        $child = $request->user();

        return response()->json([
            'id' => $child->id,
            'first_name' => $child->first_name,
            'preferred_name' => $child->preferred_name,
            'visual_experience' => $child->visual_experience,
        ]);
    }
}

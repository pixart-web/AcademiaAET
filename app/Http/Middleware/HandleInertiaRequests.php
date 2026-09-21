<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $staffUser = Auth::guard('web')->user();
        $child = Auth::guard('child')->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $staffUser,
                'child' => $child ? [
                    'id' => $child->id,
                    'first_name' => $child->first_name,
                    'preferred_name' => $child->preferred_name,
                    'visual_experience' => $child->visual_experience,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'newDeviceCode' => fn () => $request->session()->get('newDeviceCode'),
                'newDevicePin' => fn () => $request->session()->get('newDevicePin'),
            ],
        ];
    }
}

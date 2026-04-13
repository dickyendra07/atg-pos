<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ModeController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        return view('mode-select', [
            'user' => $user,
            'roleCode' => $user->role?->code,
        ]);
    }
}
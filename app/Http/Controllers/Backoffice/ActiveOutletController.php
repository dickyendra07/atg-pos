<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Services\BackofficeOutletContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActiveOutletController extends Controller
{
    public function __invoke(Request $request, BackofficeOutletContext $context)
    {
        $validated = $request->validate([
            'outlet_id' => ['nullable', 'integer'],
        ]);

        $outletId = (int) ($validated['outlet_id'] ?? 0);

        if ($outletId === 0) {
            session()->forget(BackofficeOutletContext::SESSION_KEY);
        } elseif (! $context->canAccess($request->user(), $outletId)) {
            throw ValidationException::withMessages([
                'outlet_id' => 'Outlet tidak aktif atau tidak tersedia untuk akun ini.',
            ]);
        } else {
            session([BackofficeOutletContext::SESSION_KEY => $outletId]);
        }

        return back()->with('success', $outletId ? 'Active Outlet Backoffice berhasil diubah.' : 'Backoffice menampilkan seluruh outlet yang dapat diakses.');
    }
}

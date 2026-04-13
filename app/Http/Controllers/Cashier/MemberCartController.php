<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberCartController extends Controller
{
    protected function authorizeCashierAccess()
    {
        $user = Auth::user()->load(['role']);

        $allowedRoles = [
            'owner',
            'admin_outlet',
            'kasir',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke Cashier.');
        }
    }

    public function attach(Request $request)
    {
        $this->authorizeCashierAccess();

        $request->validate([
            'phone' => 'required|string',
        ]);

        $phone = trim($request->input('phone'));

        $member = Member::where('phone', $phone)
            ->where('is_active', true)
            ->first();

        if (! $member) {
            return redirect()
                ->route('cashier.index')
                ->with('member_not_found_phone', $phone)
                ->with('success', 'Member dengan nomor tersebut belum ditemukan. Silakan daftar cepat di bawah.');
        }

        session([
            'cashier_member' => [
                'id' => $member->id,
                'name' => $member->name,
                'phone' => $member->phone,
                'points' => $member->points,
            ]
        ]);

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Member berhasil dipilih.');
    }

    public function quickRegister(Request $request)
    {
        $this->authorizeCashierAccess();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:members,phone',
        ]);

        $member = Member::create([
            'name' => trim($request->input('name')),
            'phone' => trim($request->input('phone')),
            'points' => 0,
            'is_active' => true,
        ]);

        session([
            'cashier_member' => [
                'id' => $member->id,
                'name' => $member->name,
                'phone' => $member->phone,
                'points' => $member->points,
            ]
        ]);

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Member baru berhasil dibuat dan langsung dipilih.');
    }

    public function detach()
    {
        $this->authorizeCashierAccess();

        session()->forget('cashier_member');

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Member berhasil dilepas dari transaksi.');
    }
}
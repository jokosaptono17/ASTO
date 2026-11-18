<?php

namespace App\Http\Controllers;

use App\Services\LmsDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, LmsDirectory $lms): View
    {
        $user = $request->user();
        $summary = $lms->summary($user->nrp);

        return view('portal.profile', [
            'user' => $user,
            'visitCount' => $user->visits()->count(),
            'favoriteCount' => $user->favorites()->count(),
            'menteeCount' => $user->mentees()->where('is_active', true)->count(),
            'lms' => ($summary['found'] ?? false) ? $summary : null,
        ]);
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'digits:6'],
            'pin' => ['required', 'digits:6', 'confirmed', 'different:current_pin'],
        ], [
            'pin.confirmed' => 'Ulangi PIN baru tidak sama.',
            'pin.different' => 'PIN baru harus berbeda dari PIN lama.',
            '*.digits' => 'PIN terdiri dari 6 angka.',
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_pin'], $user->pin)) {
            throw ValidationException::withMessages(['current_pin' => 'PIN lama salah.']);
        }
        $user->forceFill(['pin' => $data['pin']])->save();

        return back()->with('toast', 'PIN berhasil diganti.');
    }
}

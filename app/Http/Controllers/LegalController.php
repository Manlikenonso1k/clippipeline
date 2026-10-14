<?php

namespace App\Http\Controllers;

use App\Models\DataDeletionRequest;
use App\Services\Account\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Public compliance pages that need logic (CONTRACT §15, COMPLIANCE C-04/C-06). */
class LegalController extends Controller
{
    public function deletionStatus(Request $request): View
    {
        $code = substr(trim((string) $request->query('code', '')), 0, 64);
        $deletion = $code !== '' ? DataDeletionRequest::query()->where('code', $code)->first() : null;

        return view('legal.data-deletion-status', ['code' => $code, 'request' => $deletion]);
    }

    /** Sign in with Google, then come back to /delete-account to confirm. */
    public function deleteAccountSignIn(Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', url('/delete-account'));

        return redirect()->route('auth.redirect', ['provider' => 'google']);
    }

    public function deleteAccount(Request $request, AccountDeletionService $deletion): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE']], ['confirm.*' => 'Type DELETE to confirm.']);

        $user = $request->user();
        if (! $user) {
            return redirect('/delete-account');
        }

        $deletion->delete($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/delete-account')->with('status', 'Your account deletion has started. Connected accounts were revoked and all data will be deleted within 7 days.');
    }
}

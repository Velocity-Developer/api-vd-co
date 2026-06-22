<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $canManageTwoFactor = Features::canManageTwoFactorAuthentication();
        $supportsTwoFactorState = method_exists(
            $request->user(),
            'hasEnabledTwoFactorAuthentication',
        );

        if ($canManageTwoFactor && $supportsTwoFactorState) {
            $request->ensureStateIsValid();
        }

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canManageTwoFactor' => $canManageTwoFactor,
        ];

        if ($canManageTwoFactor) {
            $props['twoFactorEnabled'] = $supportsTwoFactorState
                ? $request->user()->hasEnabledTwoFactorAuthentication()
                : false;
            $props['requiresConfirmation'] = Features::optionEnabled(
                Features::twoFactorAuthentication(),
                'confirm',
            );
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}

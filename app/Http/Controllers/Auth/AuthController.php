<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the application login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectUserBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Deactivated accounts keep their records but cannot sign in.
        if (Auth::attemptWhen($credentials, fn (User $user): bool => ! $user->isDeactivated(), $remember)) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            return redirect()->intended($this->getRoleRedirectPath($user))
                ->with('status', "Welcome back, {$user->name}!");
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our academic records.',
        ])->onlyInput('email');
    }

    /**
     * One-click demo login switcher for rapid local testing across all roles.
     */
    public function quickLogin(string $email): RedirectResponse
    {
        // Demo switcher must never be reachable outside local development.
        abort_unless(app()->environment(['local', 'testing']), 404);

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => "User account '{$email}' not found. Please run seeders."]);
        }

        Auth::login($user);
        request()->session()->regenerate();

        return redirect($this->getRoleRedirectPath($user))
            ->with('status', "Logged in as {$user->name} ({$user->role->label()}).");
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'You have been successfully signed out.');
    }

    /**
     * Determine redirect path based on user's assigned role.
     */
    protected function getRoleRedirectPath(User $user): string
    {
        return self::homeFor($user);
    }

    /**
     * The landing page for the user's role: the student portal, the lecturer portal or repository workspace, or the archive.
     */
    public static function homeFor(User $user): string
    {
        if ($user->isStudent()) {
            return route('student.dashboard');
        }

        if ($user->usesStaffWorkspace()) {
            return '/supervisor';
        }

        return route('catalog');
    }

    protected function redirectUserBasedOnRole(User $user): RedirectResponse
    {
        return redirect($this->getRoleRedirectPath($user));
    }
}

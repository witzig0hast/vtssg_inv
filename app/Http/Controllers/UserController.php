<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->with('roles');
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        return view('users.index', ['users' => $q->orderBy('name')->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return view('users.form', ['u' => new User(['is_active' => true]), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + ['password' => ['required', Password::min(config('inventory.password_min'))->letters()->mixedCase()->numbers()]]);
        $user = User::create(collect($data)->except('role')->all() + ['must_change_password' => true]);
        $user->syncRoles([$data['role']]);

        return redirect()->route('users.index')->with('status', "Benutzer {$user->email} angelegt (Passwortwechsel beim ersten Login erforderlich).");
    }

    public function edit(User $user)
    {
        return view('users.form', ['u' => $user->load('roles'), 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user) + ['password' => ['nullable', Password::min(config('inventory.password_min'))->letters()->mixedCase()->numbers()]]);

        // Selbstschutz: letzten aktiven Administrator nicht entrechten
        if ($this->isLastAdmin($user) && ($data['role'] !== 'Administrator' || ! $request->boolean('is_active'))) {
            return back()->withErrors(['role' => 'Der letzte aktive Administrator kann nicht herabgestuft oder deaktiviert werden.']);
        }
        $fill = collect($data)->except(['role', 'password'])->all();
        $fill['is_active'] = $request->boolean('is_active');
        if (! empty($data['password'])) {
            $fill['password'] = $data['password'];
            $fill['must_change_password'] = true;
        }
        $user->update($fill);
        $user->syncRoles([$data['role']]);
        if ($request->boolean('reset_2fa')) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
            AuditLog::record('2fa_reset_by_admin', $user, summary: $user->email);
        }
        if (! $user->is_active) {
            $user->tokens()->delete();
            \DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('users.index')->with('status', 'Benutzer gespeichert.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'Du kannst dich nicht selbst löschen.']);
        }
        if ($this->isLastAdmin($user)) {
            return back()->withErrors(['delete' => 'Der letzte Administrator kann nicht gelöscht werden.']);
        }
        $user->tokens()->delete();
        \DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        return redirect()->route('users.index')->with('status', 'Benutzer gelöscht.');
    }

    protected function isLastAdmin(User $user): bool
    {
        return $user->hasRole('Administrator') && User::role('Administrator')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    protected function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::exists('roles', 'name')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

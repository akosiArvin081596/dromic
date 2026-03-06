<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with('region', 'province', 'cityMunicipality.province');

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        $query->when($request->input('role'), function ($q, $role) {
            $q->where('role', $role);
        });

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        $userCounts = [
            'total' => User::count(),
            'admin' => User::where('role', 'admin')->count(),
            'regional' => User::where('role', 'regional')->count(),
            'provincial' => User::where('role', 'provincial')->count(),
            'lgu' => User::where('role', 'lgu')->count(),
        ];

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
            'userCounts' => $userCounts,
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'provinces' => Province::with('region')->orderBy('name')->get(['id', 'name', 'region_id']),
            'cityMunicipalities' => CityMunicipality::with('province')->orderBy('name')->get(['id', 'name', 'province_id']),
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => ucfirst(str_replace('_', ' ', $role->value)),
            ])->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'region_id' => $data['region_id'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'city_municipality_id' => $data['city_municipality_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'region_id' => $data['region_id'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'city_municipality_id' => $data['city_municipality_id'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete yourself.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}

<?php

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\CityMunicipality;
use App\Models\Region;
use App\Models\User;

test('UserType::forRole returns correct types for each role', function () {
    expect(UserType::forRole(UserRole::Lgu))->toBe([UserType::Cmswdo, UserType::DswdLgu, UserType::Ldrrmo]);
    expect(UserType::forRole(UserRole::Provincial))->toBe([UserType::ProvincialDswd, UserType::Pdrrmo, UserType::Pswdo]);
    expect(UserType::forRole(UserRole::Regional))->toBe([UserType::Drims, UserType::Rros]);
    expect(UserType::forRole(UserRole::Admin))->toBe([]);
    expect(UserType::forRole(UserRole::Escort))->toBe([]);
});

test('UserType::defaultForRole returns correct defaults', function () {
    expect(UserType::defaultForRole(UserRole::Lgu))->toBe(UserType::Cmswdo);
    expect(UserType::defaultForRole(UserRole::Provincial))->toBe(UserType::ProvincialDswd);
    expect(UserType::defaultForRole(UserRole::Regional))->toBe(UserType::Drims);
    expect(UserType::defaultForRole(UserRole::Admin))->toBeNull();
});

test('store user with valid user_type succeeds', function () {
    $admin = User::factory()->admin()->create();
    $city = CityMunicipality::factory()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Test LGU User',
            'email' => 'lgu@test.com',
            'password' => 'password123',
            'role' => 'lgu',
            'user_type' => 'ldrrmo',
            'city_municipality_id' => $city->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'lgu@test.com',
        'role' => 'lgu',
        'user_type' => 'ldrrmo',
    ]);
});

test('store user with invalid user_type for role fails validation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Test User',
            'email' => 'bad@test.com',
            'password' => 'password123',
            'role' => 'lgu',
            'user_type' => 'drims',
        ])
        ->assertSessionHasErrors('user_type');
});

test('store user with null user_type succeeds', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/users', [
            'name' => 'Admin User',
            'email' => 'admin2@test.com',
            'password' => 'password123',
            'role' => 'admin',
            'user_type' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'admin2@test.com',
        'user_type' => null,
    ]);
});

test('update user with valid user_type succeeds', function () {
    $admin = User::factory()->admin()->create();
    $region = Region::factory()->create();
    $user = User::factory()->regional($region)->create();

    $this->actingAs($admin)
        ->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'regional',
            'user_type' => 'rros',
            'region_id' => $region->id,
        ])
        ->assertRedirect();

    expect($user->fresh()->user_type)->toBe(UserType::Rros);
});

test('update user with invalid user_type for role fails validation', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->regional()->create();

    $this->actingAs($admin)
        ->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'regional',
            'user_type' => 'cmswdo',
            'region_id' => $user->region_id,
        ])
        ->assertSessionHasErrors('user_type');
});

test('user factory sets correct default user_type per role', function () {
    $lgu = User::factory()->lgu()->create();
    $provincial = User::factory()->provincial()->create();
    $regional = User::factory()->regional()->create();
    $admin = User::factory()->admin()->create();

    expect($lgu->user_type)->toBe(UserType::Cmswdo);
    expect($provincial->user_type)->toBe(UserType::ProvincialDswd);
    expect($regional->user_type)->toBe(UserType::Drims);
    expect($admin->user_type)->toBeNull();
});

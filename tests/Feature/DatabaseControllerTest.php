<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('admin can view database index', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/database');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['tables'])->toBeArray()
        ->and(collect($props['tables'])->pluck('name')->toArray())->toContain('users');
});

test('non-admin cannot access database pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/database')->assertForbidden();
});

test('admin can browse a table', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/database/users');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['table'])->toBe('users')
        ->and($props['columns'])->toBeArray()
        ->and($props['columns'])->toContain('id', 'name', 'email')
        ->and($props['rows']['data'])->toBeArray();
});

test('admin can search rows in a table', function () {
    $admin = User::factory()->admin()->create(['name' => 'SearchableAdmin']);
    User::factory()->create(['name' => 'OtherUser']);

    $response = $this->actingAs($admin)->get('/database/users?search=SearchableAdmin');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    $names = collect($props['rows']['data'])->pluck('name')->toArray();
    expect($names)->toContain('SearchableAdmin')
        ->and($names)->not->toContain('OtherUser');
});

test('admin can sort rows in a table', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get('/database/users?sort=name&dir=desc');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['filters']['sort'])->toBe('name')
        ->and($props['filters']['dir'])->toBe('desc');
});

test('admin can delete a row', function () {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->create(['name' => 'ToBeDeleted']);

    $response = $this->actingAs($admin)->delete('/database/users/row', [
        'column' => 'id',
        'value' => $target->id,
    ]);

    $response->assertRedirect();
    expect(DB::table('users')->where('id', $target->id)->exists())->toBeFalse();
});

test('admin can truncate a table', function () {
    $admin = User::factory()->admin()->create();

    // Create a dummy table to truncate safely
    DB::statement('CREATE TABLE IF NOT EXISTS test_truncate (id INTEGER PRIMARY KEY, name TEXT)');
    DB::table('test_truncate')->insert(['name' => 'row1']);
    DB::table('test_truncate')->insert(['name' => 'row2']);

    expect(DB::table('test_truncate')->count())->toBe(2);

    $response = $this->actingAs($admin)->delete('/database/test_truncate/truncate');

    $response->assertRedirect();
    expect(DB::table('test_truncate')->count())->toBe(0);

    DB::statement('DROP TABLE IF EXISTS test_truncate');
});

test('admin cannot truncate protected table', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete('/database/migrations/truncate');

    $response->assertForbidden();
});

test('admin cannot delete rows from protected table', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->delete('/database/migrations/row', [
        'column' => 'id',
        'value' => 1,
    ]);

    $response->assertForbidden();
});

test('browsing a non-existent table returns 404', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/database/non_existent_table')->assertNotFound();
});

test('guest cannot access database pages', function () {
    $this->get('/database')->assertRedirect('/login');
});

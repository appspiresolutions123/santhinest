<?php

use App\Actions\Permissions\SyncPermissions;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('administrators can visit the dashboard', function () {
    $user = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users without dashboard permission are forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertForbidden();
});

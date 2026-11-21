<?php

use App\Actions\Permissions\SyncPermissions;
use App\Enums\StayRequestStatus;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\StayRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests and admins can complete a booking request and see its hold', function () {
    $room = Room::factory()->create();
    $property = $room->property;
    $checkIn = now()->addDays(2)->toDateString();
    $checkOut = now()->addDays(4)->toDateString();
    $details = [
        'name' => 'Test Guest',
        'email' => 'guest@example.test',
        'phone' => '+91 98765 43210',
        'guests' => 2,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'room_ids' => [$room->id],
    ];
    $this->post(route('stay-requests.store', $property), $details)
        ->assertSessionHasNoErrors()->assertRedirect(route('properties.show', $property));
    $stay = $property->stayRequests()->sole();
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')->where('pendingRequests.0.id', $stay->id));
    $this->get(route('admin.stay-requests.index'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/stay-requests/Index')->where('stayRequests.data.0.id', $stay->id));
    $this->post(route('admin.stay-requests.approve', $stay))
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($stay->refresh()->status)->toBe(StayRequestStatus::Approved);

    $this->get(route('admin.holds.index', ['from' => $checkIn]))->assertInertia(fn (Assert $page) => $page
        ->component('admin/holds/Index')->where('properties.0.rooms.0.holds.0.stayRequestReference', $stay->reference));
    $this->get(route('properties.show', $property))->assertInertia(fn (Assert $page) => $page
        ->component('properties/Show')->has('rooms.0.holds', 1));
    $this->post(route('stay-requests.store', $property), $details)->assertSessionHasErrors('room_ids');
    $this->assertDatabaseCount('stay_requests', 1);
});

test('admins can create change and release a manual hold', function () {
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);
    $room = Room::factory()->create();
    $dates = [
        'room_id' => $room->id,
        'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
    ];

    $this->actingAs($admin)->post(route('admin.holds.store'), $dates)->assertSessionHasNoErrors()->assertRedirect();
    $hold = RoomHold::query()->sole();
    $this->post(route('admin.holds.store'), $dates)->assertSessionHasErrors('starts_at');
    $this->put(route('admin.holds.update', $hold), [...$dates, 'note' => 'Confirmed by phone'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($hold->refresh()->note)->toBe('Confirmed by phone');
    $this->delete(route('admin.holds.destroy', $hold))->assertRedirect();
    $this->assertModelMissing($hold);
});

test('malformed filters leave the stay request list and hold board usable', function () {
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);

    $this->actingAs($admin)
        ->get(route('admin.stay-requests.index', ['status' => ['pending'], 'property' => [1]]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/stay-requests/Index')->where('filters.status', 'pending'));
    $this->get(route('admin.holds.index', ['from' => ['invalid']]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/holds/Index')->where('from', today()->toDateString()));
});

test('admins can decline a request and view the declined list', function () {
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);
    $stay = StayRequest::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.stay-requests.decline', $stay), ['admin_note' => 'Dates unavailable'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $this->get(route('admin.stay-requests.index', ['status' => 'declined', 'property' => $stay->property_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stayRequests.data.0.id', $stay->id)
            ->where('stayRequests.data.0.status', 'declined')
            ->where('stayRequests.data.0.adminNote', 'Dates unavailable'));
    $this->assertDatabaseCount('room_holds', 0);
});

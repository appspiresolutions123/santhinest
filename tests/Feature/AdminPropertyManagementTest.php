<?php

use App\Actions\Permissions\SyncPermissions;
use App\Models\Media;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;

test('an admin can manage a property with an unavailable legacy photo', function () {
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);
    $details = [
        'name' => 'Casa Bloom',
        'city' => 'Hyderabad',
        'country' => 'India',
        'max_guests' => 4,
        'bedrooms' => 2,
        'beds' => 2,
        'bathrooms' => 1,
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
        'is_published' => true,
    ];

    $this->actingAs($admin)->get(route('admin.properties.create'))->assertOk();
    $this->post(route('admin.properties.store'), $details)
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.properties.edit', 'casa-bloom'));

    $property = Property::query()->where('slug', 'casa-bloom')->sole();
    $legacy = Media::factory()->create(['disk' => 'site']);
    $image = $property->images()->create(['media_id' => $legacy->id]);

    $this->get(route('admin.properties.index'))->assertInertia(fn (Assert $page) => $page
        ->component('admin/properties/Index')
        ->where('properties.0.name', 'Casa Bloom')
        ->where('properties.0.coverUrl', null));
    $this->get(route('admin.properties.edit', $property))->assertInertia(fn (Assert $page) => $page
        ->component('admin/properties/Edit')
        ->where('images.0.id', $image->id)
        ->where('images.0.url', null));
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('properties.0.images', 0));
    $this->get(route('properties.show', $property))->assertInertia(fn (Assert $page) => $page->has('images', 0));

    $this->put(route('admin.properties.update', $property), [...$details, 'slug' => 'casa-bloom-stays'])
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.properties.edit', 'casa-bloom-stays'));
    $property->refresh();
    $this->post(route('admin.rooms.store', $property), ['name' => 'Garden Room', 'max_guests' => 2])
        ->assertSessionHasNoErrors()->assertRedirect();
    $room = $property->rooms()->sole();
    $this->put(route('admin.rooms.update', $room), ['name' => 'Garden Suite', 'max_guests' => 3])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($room->refresh()->name)->toBe('Garden Suite');
    $this->delete(route('admin.rooms.destroy', $room))->assertRedirect();
    $this->assertModelMissing($room);

    Exceptions::fake();
    $this->delete(route('admin.properties.destroy', $property))->assertRedirect(route('admin.properties.index'));
    $this->assertModelMissing($property);
    $this->assertModelMissing($image);
    Exceptions::assertReported(InvalidArgumentException::class);
});

test('a failed property photo upload returns a form error', function () {
    $admin = User::factory()->create()->assignRole(app(SyncPermissions::class)->handle()['role']);
    $property = Property::factory()->create();
    config(['filesystems.images_disk' => 'site']);
    Exceptions::fake();
    $photo = UploadedFile::fake()->createWithContent('property.jpg', file_get_contents(public_path('images/properties/casa-bloom/001.jpg')));
    Process::fake();

    $this->actingAs($admin)->from(route('admin.properties.edit', $property))
        ->post(route('admin.property-images.store', $property), ['images' => [$photo]])
        ->assertRedirect(route('admin.properties.edit', $property))
        ->assertSessionHasErrors('images');

    $this->assertDatabaseCount('media', 0);
    $this->assertDatabaseCount('property_images', 0);
    Exceptions::assertReported(InvalidArgumentException::class);
});

test('a user without property permissions cannot manage properties', function () {
    $property = Property::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.properties.index'))->assertForbidden();
    $this->get(route('admin.properties.edit', $property))->assertForbidden();
    $this->post(route('admin.properties.store'))->assertForbidden();
    $this->put(route('admin.properties.update', $property))->assertForbidden();
    $this->delete(route('admin.properties.destroy', $property))->assertForbidden();
    $this->assertModelExists($property);
});

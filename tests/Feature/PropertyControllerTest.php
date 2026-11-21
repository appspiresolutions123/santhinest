<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

test('contact details saved in admin appear on the landing page for published properties', function () {
    Gate::define('update properties', fn (User $user): bool => true);
    $admin = User::factory()->create();
    $property = Property::factory()->create(['city' => 'Hyderabad', 'state' => 'Telangana']);
    Property::factory()->unpublished()->create(['contact_email' => 'private@example.test']);

    $this->actingAs($admin)
        ->put(route('admin.properties.update', $property), [
            ...$property->getAttributes(),
            'amenities' => $property->amenities,
            'house_rules' => $property->house_rules,
            'address' => '12 Example Street',
            'contact_phone' => '+91 98765 43210',
            'contact_email' => 'host@example.test',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.properties.edit', $property));

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('properties', 1)
        ->where('properties.0.address', '12 Example Street')
        ->where('properties.0.location', 'Hyderabad, Telangana, India')
        ->where('properties.0.contactPhone', '+91 98765 43210')
        ->where('properties.0.contactEmail', 'host@example.test')
    );
});

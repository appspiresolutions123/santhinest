<?php

use App\Models\Media;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToDeleteFile;

test('admin photo uploads store a smaller lossless JPEG or fall back to the original', function (string $optimizerResult) {
    $disk = Storage::fake('s3');
    config(['filesystems.images_disk' => 's3']);
    Gate::define('manage property images', fn (User $user): bool => true);
    $admin = User::factory()->create();
    $property = Property::factory()->create();
    $jpeg = file_get_contents(public_path('images/properties/casa-bloom/001.jpg'))."\n";
    $original = $jpeg.str_repeat("\x00", 1024)."\n";
    $upload = UploadedFile::fake()->createWithContent('property.jpg', $original);
    Process::fake(fn () => match ($optimizerResult) {
        'smaller' => Process::result(output: $jpeg),
        'same size' => Process::result(output: $original),
        'unavailable' => Process::result(exitCode: 127),
    });

    $this->actingAs($admin)
        ->post(route('admin.property-images.store', $property), ['images' => [$upload]])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    Process::assertRan(['jpegtran', '-copy', 'all', '-optimize', $upload->getPathname()]);
    $media = $property->images()->sole()->media;
    $expected = $optimizerResult === 'smaller' ? $jpeg : $original;

    expect($media->original_name)->toBe('property.jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->size)->toBe(strlen($expected))
        ->and($disk->get($media->path))->toBe($expected);
})->with(['smaller', 'same size', 'unavailable']);

test('admin uploads can attach photos to a room in the property', function () {
    Storage::fake('public');
    config(['filesystems.images_disk' => 'public']);
    Gate::define('manage property images', fn (User $user): bool => true);
    $room = Room::factory()->create();
    $upload = UploadedFile::fake()->createWithContent(
        'room.jpg',
        file_get_contents(public_path('images/properties/casa-bloom/001.jpg')),
    );
    Process::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.property-images.store', $room->property), [
            'room_id' => $room->id,
            'images' => [$upload],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $image = $room->property->images()->sole();
    expect($image->room_id)->toBe($room->id);
    Storage::disk('public')->assertExists($image->media->path);
});

test('admin uploads reject a room from another property', function () {
    Storage::fake('public');
    config(['filesystems.images_disk' => 'public']);
    Gate::define('manage property images', fn (User $user): bool => true);
    $property = Property::factory()->create();
    $room = Room::factory()->create();
    $upload = UploadedFile::fake()->createWithContent(
        'room.jpg',
        file_get_contents(public_path('images/properties/casa-bloom/001.jpg')),
    );

    $this->actingAs(User::factory()->create())
        ->post(route('admin.property-images.store', $property), [
            'room_id' => $room->id,
            'images' => [$upload],
        ])
        ->assertSessionHasErrors('room_id');

    $this->assertDatabaseEmpty('property_images');
});

test('admin deletion completes and reports a failed storage cleanup', function (bool $deleteProperty, bool $storageThrows) {
    Gate::define('manage property images', fn (User $user): bool => true);
    Gate::define('delete properties', fn (User $user): bool => true);
    $admin = User::factory()->create();
    $property = Property::factory()->create();
    $media = Media::factory()->create(['disk' => 's3']);
    $image = $property->images()->create(['media_id' => $media->id]);
    $failure = UnableToDeleteFile::atLocation($media->path, 'Access denied');
    Exceptions::fake();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $delete = $disk->shouldReceive('delete')->once()->with($media->path);
    if ($storageThrows) {
        $delete->andThrow($failure);
    } else {
        $delete->andReturn(false);
    }
    Storage::set('s3', $disk);
    $url = $deleteProperty
        ? route('admin.properties.destroy', $property)
        : route('admin.property-images.destroy', $image);

    $this->actingAs($admin)->delete($url)->assertRedirect();

    $this->assertModelMissing($image);
    $this->assertModelExists($media);
    expect($property->fresh() === null)->toBe($deleteProperty);
    Exceptions::assertReported(fn (UnableToDeleteFile $exception): bool => $exception->location() === $media->path);
})->with([
    'photo when storage throws' => [false, true],
    'property when storage throws' => [true, true],
    'photo when storage returns false' => [false, false],
]);

test('admin photo deletion removes the file only after its last gallery reference is removed', function () {
    Gate::define('manage property images', fn (User $user): bool => true);
    $admin = User::factory()->create();
    $property = Property::factory()->create();
    $media = Media::factory()->create(['disk' => 's3']);
    $first = $property->images()->create(['media_id' => $media->id]);
    $last = $property->images()->create(['media_id' => $media->id]);
    $disk = Storage::fake('s3');
    $disk->put($media->path, 'Property photo');

    $this->actingAs($admin)->delete(route('admin.property-images.destroy', $first))->assertRedirect();
    $disk->assertExists($media->path);
    $this->assertModelExists($last);

    $this->delete(route('admin.property-images.destroy', $last))->assertRedirect();
    $disk->assertMissing($media->path);
    $this->assertModelMissing($media);
});

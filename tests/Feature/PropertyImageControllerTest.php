<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

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

<?php

use App\Models\Property;
use Illuminate\Support\Facades\Storage;

test('public property photos can move to S3 without replacing gallery entries', function () {
    $property = Property::factory()->create(['slug' => 'casa-bloom']);
    $localDisk = Storage::fake('public');
    $s3 = Storage::fake('s3');
    $path = 'images/properties/casa-bloom/001.jpg';
    $source = Storage::fake('image-source');
    $source->put($path, 'Property photo contents');
    $this->app->usePublicPath($source->path(''));

    $this->artisan('images:upload', ['--disk' => 'public'])->assertSuccessful();
    $localDisk->assertExists($path);
    $image = $property->images()->whereHas('media', fn ($query) => $query->where('path', $path))->firstOrFail();
    $image->update(['caption' => 'Edited by admin', 'sort_order' => 25]);

    $this->artisan('images:upload', ['--disk' => 's3'])->assertSuccessful();
    $this->artisan('images:upload', ['--disk' => 's3'])->assertSuccessful();

    expect($s3->get($path))->toBe($localDisk->get($path));
    $this->assertDatabaseHas('media', ['id' => $image->media_id, 'disk' => 's3', 'path' => $path]);
    $this->assertDatabaseHas('property_images', [
        'id' => $image->id,
        'media_id' => $image->media_id,
        'caption' => 'Edited by admin',
        'sort_order' => 25,
    ]);
    $this->assertDatabaseCount('property_images', 1);

    $source->deleteDirectory('images');
    $this->artisan('images:upload', ['--disk' => 's3'])->assertSuccessful();
    $s3->assertExists($path);
    $this->assertDatabaseCount('property_images', 1);
});

<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Property;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

#[Signature('images:upload {--disk= : The filesystem disk to upload the images to (defaults to filesystems.images_disk)} {--force : Re-upload images that already exist on the disk}')]
#[Description('Upload the default property photos to a filesystem disk and add them to each property gallery')]
class UploadImages extends Command
{
    /**
     * The folder on the disk that the images are uploaded into.
     */
    public const string PREFIX = 'images';

    /**
     * The Cache-Control header sent with every uploaded image.
     */
    public const string CACHE_CONTROL = 'public, max-age=604800';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('filesystems.images_disk'));

        if (config("filesystems.disks.{$diskName}") === null) {
            $this->components->error("The [{$diskName}] disk is not configured.");

            return self::FAILURE;
        }

        if (! File::isDirectory(self::sourcePath())) {
            $this->components->warn('No default images found in '.self::sourcePath().'.');

            return self::SUCCESS;
        }

        $disk = Storage::disk($diskName);
        $uploadedCount = 0;
        $skippedCount = 0;
        $failedPaths = [];

        foreach (File::allFiles(self::sourcePath()) as $file) {
            $path = self::PREFIX.'/properties/'.str_replace('\\', '/', $file->getRelativePathname());

            if (! $this->option('force') && $disk->exists($path)) {
                $skippedCount++;
            } elseif ($disk->put($path, File::get($file->getPathname()), ['CacheControl' => self::CACHE_CONTROL])) {
                $uploadedCount++;
                $this->components->task($path);
            } else {
                $failedPaths[] = $path;

                continue;
            }

            $this->recordPropertyImage($diskName, $path, $file);
        }

        $this->components->info("Uploaded {$uploadedCount} image(s) to [{$diskName}], skipped {$skippedCount} existing.");

        if ($failedPaths !== []) {
            $this->components->error('Failed to upload: '.implode(', ', $failedPaths));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Switch an existing public import to S3 or add the photo to the gallery
     * of the property named by its source folder.
     */
    protected function recordPropertyImage(string $diskName, string $path, SplFileInfo $file): void
    {
        if ($diskName === 's3' && ! Media::query()->where('disk', $diskName)->where('path', $path)->exists()) {
            $localMedia = Media::query()->where('disk', 'public')->where('path', $path)->first();

            if ($localMedia !== null) {
                $localMedia->update(['disk' => $diskName]);

                return;
            }
        }

        $caption = self::caption($file->getFilename());

        $media = Media::query()->updateOrCreate(
            ['disk' => $diskName, 'path' => $path],
            [
                'original_name' => $file->getFilename(),
                'mime_type' => File::mimeType($file->getPathname()) ?: null,
                'size' => $file->getSize(),
                'alt' => $caption,
            ],
        );

        $slug = Str::before(str_replace('\\', '/', $file->getRelativePathname()), '/');
        $property = Property::query()->where('slug', $slug)->first();

        if ($property === null || $property->images()->where('media_id', $media->id)->exists()) {
            return;
        }

        $property->images()->create([
            'media_id' => $media->id,
            'caption' => $caption,
            'sort_order' => (int) $property->images()->max('sort_order') + 1,
        ]);
    }

    /**
     * The caption for a file, e.g. "07-bedroom-1.jpg" becomes "Bedroom 1".
     */
    public static function caption(string $fileName): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $name = (string) preg_replace('/^\d+-/', '', $name);

        return Str::ucfirst(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * The local directory that holds the default photos, one folder per
     * property slug.
     */
    public static function sourcePath(): string
    {
        return public_path('images/properties');
    }
}

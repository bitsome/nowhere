<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FileService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function uploadMany(
        Model&HasMedia $model,
        array $files,
        string $collectionName = 'attachments',
        ?string $diskName = null,
    ): Collection {
        $mediaItems = collect();

        foreach ($files as $file) {
            $fileAdder = $model->addMedia($file);

            if ($diskName !== null) {
                $mediaItems->push($fileAdder->toMediaCollection($collectionName, $diskName));

                continue;
            }

            $mediaItems->push($fileAdder->toMediaCollection($collectionName));
        }

        return $mediaItems;
    }

    public function storeChunk(
        Model&HasMedia $model,
        UploadedFile $chunk,
        string $uploadId,
        int $chunkIndex,
        int $totalChunks,
        string $originalName,
        string $collectionName = 'attachments',
        ?string $diskName = null,
    ): ?Media {
        $chunkDirectory = $this->chunkDirectory($model, $uploadId);

        Storage::disk('local')->putFileAs(
            $chunkDirectory,
            $chunk,
            $this->chunkFileName($chunkIndex),
        );

        if (! $this->hasAllChunks($chunkDirectory, $totalChunks)) {
            return null;
        }

        $temporaryFilePath = $this->mergeChunks($chunkDirectory, $uploadId, $originalName, $totalChunks);

        $fileAdder = $model
            ->addMedia($temporaryFilePath)
            ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
            ->usingFileName($this->storedFileName($originalName));

        $media = $diskName !== null
            ? $fileAdder->toMediaCollection($collectionName, $diskName)
            : $fileAdder->toMediaCollection($collectionName);

        Storage::disk('local')->deleteDirectory($chunkDirectory);

        if (File::exists($temporaryFilePath)) {
            File::delete($temporaryFilePath);
        }

        return $media;
    }

    public function delete(Media $media): void
    {
        $media->delete();
    }

    public function downloadName(Media $media): string
    {
        return $media->file_name;
    }

    public function previewUrl(Media $media, string $conversionName = ''): string
    {
        if ($conversionName !== '' && $media->hasGeneratedConversion($conversionName)) {
            return $media->getUrl($conversionName);
        }

        return $media->getUrl();
    }

    /**
     * @return Collection<int, Media>
     */
    public function getFiles(Model&HasMedia $model, string $collectionName = 'attachments'): Collection
    {
        return $model->getMedia($collectionName);
    }

    private function chunkDirectory(Model $model, string $uploadId): string
    {
        return 'chunks/file-manager/'.$model->getTable().'/'.$model->getKey().'/'.$uploadId;
    }

    private function chunkFileName(int $chunkIndex): string
    {
        return str_pad((string) $chunkIndex, 5, '0', STR_PAD_LEFT).'.part';
    }

    private function hasAllChunks(string $chunkDirectory, int $totalChunks): bool
    {
        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            if (! Storage::disk('local')->exists($chunkDirectory.'/'.$this->chunkFileName($chunkIndex))) {
                return false;
            }
        }

        return true;
    }

    private function mergeChunks(string $chunkDirectory, string $uploadId, string $originalName, int $totalChunks): string
    {
        $temporaryDirectory = storage_path('app/private/tmp/file-manager');

        File::ensureDirectoryExists($temporaryDirectory);

        $temporaryFilePath = $temporaryDirectory.'/'.$uploadId.'-'.$this->storedFileName($originalName);
        $targetStream = fopen($temporaryFilePath, 'wb');

        if ($targetStream === false) {
            throw new \RuntimeException('임시 파일을 생성할 수 없습니다.');
        }

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            $chunkContents = Storage::disk('local')->get($chunkDirectory.'/'.$this->chunkFileName($chunkIndex));
            fwrite($targetStream, $chunkContents);
        }

        fclose($targetStream);

        return $temporaryFilePath;
    }

    private function storedFileName(string $originalName): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($baseName);

        if ($slug === '') {
            $slug = 'file';
        }

        return $extension !== ''
            ? $slug.'-'.Str::lower(Str::random(8)).'.'.$extension
            : $slug.'-'.Str::lower(Str::random(8));
    }
}

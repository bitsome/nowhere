<?php

return array_merge(
    require base_path('vendor/spatie/laravel-medialibrary/config/media-library.php'),
    [
        'disk_name' => env('MEDIA_DISK', 'public'),
        'max_file_size' => 1024 * 1024 * 10,
        'queue_conversions_by_default' => false,
        'queue_conversions_after_database_commit' => false,
    ],
);

<?php

namespace App\Models;

use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'type',
    'title',
    'content',
    'user_id',
    'status',
    'is_notice',
    'is_private',
    'view_count',
])]
class Board extends Model implements HasMedia
{
    public const TYPE_NOTICE = 'notice';
    public const TYPE_FREE = 'free';
    public const TYPE_QNA = 'qna';

    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN = 'hidden';
    public const ATTACHMENT_COLLECTION = 'board-attachments';

    /** @use HasFactory<BoardFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<int, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_NOTICE,
            self::TYPE_FREE,
            self::TYPE_QNA,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_NOTICE => '공지',
            self::TYPE_FREE => '자유',
            self::TYPE_QNA => '문의',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PUBLISHED => '게시',
            self::STATUS_HIDDEN => '숨김',
        ];
    }

    #[Scope]
    protected function search(Builder $query, string $search): void
    {
        $query->where(function (Builder $boardQuery) use ($search) {
            $boardQuery
                ->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENT_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 320, 240)
            ->performOnCollections(self::ATTACHMENT_COLLECTION)
            ->nonQueued();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_notice' => 'boolean',
            'is_private' => 'boolean',
        ];
    }
}

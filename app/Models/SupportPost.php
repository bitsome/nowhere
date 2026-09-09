<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 고객지원 공지·FAQ — 관리자가 작성해 사용자 고객지원 화면에 노출된다 (B-4).
 */
#[Fillable(['kind', 'title', 'body', 'author_id'])]
class SupportPost extends Model
{
    public const KIND_NOTICE = 'notice';

    public const KIND_FAQ = 'faq';

    /**
     * 게시물 종류 라벨 — 공지/FAQ.
     *
     * @return array<string, string>
     */
    public static function kindOptions(): array
    {
        return [
            self::KIND_NOTICE => '공지',
            self::KIND_FAQ => 'FAQ',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

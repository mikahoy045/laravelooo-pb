<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Page",
 *     required={"title", "slug", "banner_type", "banner_path", "content", "user_id"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="banner_type", type="string", enum={"image", "video"}),
 *     @OA\Property(property="banner_path", type="string"),
 *     @OA\Property(property="content", type="string"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'banner_type',
        'banner_path',
        'content',
        'user_id',
        'published_at'
    ];

    protected $hidden = [
        'user_id'
    ];

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Media",
 *     required={"name", "type", "file_path", "mime_type", "size", "user_id"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="type", type="string", enum={"image", "video"}),
 *     @OA\Property(property="file_path", type="string"),
 *     @OA\Property(property="mime_type", type="string"),
 *     @OA\Property(property="size", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class Media extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'file_path',
        'mime_type',
        'size',
        'user_id'
    ];

    protected $hidden = [
        'user_id'
    ];

    protected $casts = [
        'deleted_at' => 'datetime'
    ];

    protected $dates = ['deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 
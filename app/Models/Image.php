<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model {

    const STORAGE_SUPABASE = 'supabase';
    const STORAGE_VERCEL = 'vercel';

    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'images';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'storage_path',
        'storage_bucket',
        'storage_url',
        'storage_provider',
        'filename',
        'mime_type',
        'size',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the galleries that this image belongs to.
     */
    public function galleries() {
        return $this->belongsToMany(Gallery::class, 'gallery_images')
                    ->withPivot('order')
                    ->withTimestamps()
                    ->orderBy('gallery_images.order');
    }

    /**
     * Get the categories that this image belongs to.
     */
    public function categories() {
        return $this->belongsToMany(Category::class, 'image_category')
                    ->withTimestamps();
    }

    /**
     * Get the user that owns the image.
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the formatted file size.
     *
     * @return string
     */
    public function getFormattedSizeAttribute() {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get the file type.
     *
     * @return string
     */
    public function getFileTypeAttribute() {
        $parts = explode('/', $this->mime_type);
        return $parts[0] ?? 'unknown';
    }

    /**
     * Get the file subtype.
     *
     * @return string
     */
    public function getFileSubtypeAttribute() {
        $parts = explode('/', $this->mime_type);
        return $parts[1] ?? 'unknown';
    }

    /**
     * Determine if the file is an image.
     *
     * @return bool
     */
    public function getIsImageAttribute() {
        return $this->file_type === 'image';
    }

    /**
     * Determine if the file is a video.
     *
     * @return bool
     */
    public function getIsVideoAttribute() {
        return $this->file_type === 'video';
    }

    /**
     * Determine if the file is an audio file.
     *
     * @return bool
     */
    public function getIsAudioAttribute() {
        return $this->file_type === 'audio';
    }

    /**
     * Determine if the file is a document.
     *
     * @return bool
     */
    public function getIsDocumentAttribute() {
        return in_array($this->file_type, ['application', 'text']);
    }

    public function isVercelStorage() {
        return $this->storage_provider === self::STORAGE_VERCEL;
    }

    public function isSupabaseStorage() {
        return $this->storage_provider === self::STORAGE_SUPABASE;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model {

    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'galleries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'cover_image_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the images that belong to this gallery.
     */
    public function images() {
        return $this->belongsToMany(Image::class, 'gallery_images')
                    ->withPivot('order')
                    ->withTimestamps()
                    ->orderBy('gallery_images.order');
    }

    /**
     * Get the cover image for this gallery.
     */
    public function coverImage() {
        return $this->belongsTo(Image::class, 'cover_image_id');
    }

    /**
     * Get the user that owns the gallery.
     */
    public function user() {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the count of images in this gallery.
     *
     * @return int
     */
    public function getImageCount() {
        return $this->images()->count();
    }
}

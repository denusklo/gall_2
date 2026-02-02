<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Category model for organizing images.
 *
 * Categories are user-scoped, meaning each user has their own set of categories.
 * The slug is auto-generated from the name during create and update operations.
 */
class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description'
    ];

    /**
     * The "booted" method of the model.
     *
     * Registers model event listeners to auto-generate and update slugs
     * based on the category name.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name when creating
        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });

        // Auto-update slug when name changes
        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get the images that belong to this category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function images()
    {
        return $this->belongsToMany(Image::class, 'image_category')
                    ->withTimestamps();
    }

    /**
     * Get the user that owns this category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
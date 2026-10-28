<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'author_id',
    'title',
    'slug',
    'category',
    'summary',
    'body',
    'is_published',
    'published_at',
])]
class KnowledgeArticle extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(KnowledgeArticleImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(KnowledgeArticleReview::class);
    }
}

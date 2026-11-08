<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path'])]
class KnowledgeArticleImage extends Model
{
    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    public function url(): string
    {
        return route('public-images.show', ['path' => $this->path]);
    }
}

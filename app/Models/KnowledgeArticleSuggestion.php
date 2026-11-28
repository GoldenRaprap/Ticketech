<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'target_article_id',
    'title',
    'category',
    'summary',
    'proposed_body',
    'source_ticket_ids',
    'shared_terms',
    'similarity_score',
    'status',
    'fingerprint',
    'reviewed_by',
    'reviewed_at',
])]
class KnowledgeArticleSuggestion extends Model
{
    protected function casts(): array
    {
        return [
            'source_ticket_ids' => 'array',
            'shared_terms' => 'array',
            'similarity_score' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function targetArticle(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'target_article_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

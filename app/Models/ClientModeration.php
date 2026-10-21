<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'status', 'reason', 'source_report_id', 'acted_by_id', 'cleared_at'])]
class ClientModeration extends Model
{
    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function sourceReport(): BelongsTo
    {
        return $this->belongsTo(TicketMisuseReport::class, 'source_report_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_id');
    }
}

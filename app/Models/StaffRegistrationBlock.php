<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'reason', 'blocked_by_id', 'cleared_at'])]
class StaffRegistrationBlock extends Model
{
    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_id');
    }
}

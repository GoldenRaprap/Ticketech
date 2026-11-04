<?php

namespace App\Models;

use App\Notifications\WorkspaceAlert;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ticket_id', 'actor_id', 'type', 'title', 'message', 'read_at'])]
class WorkspaceNotification extends Model
{
    protected static function booted(): void
    {
        static::created(function (WorkspaceNotification $notification): void {
            $notification->user->notify(new WorkspaceAlert($notification));
        });
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

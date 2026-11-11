<?php

namespace App\Models;

use App\Notifications\WorkspaceAlert;
use App\Services\ImmediateNotificationSender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ticket_id', 'actor_id', 'type', 'title', 'message', 'read_at'])]
class WorkspaceNotification extends Model
{
    /**
     * @return Builder<WorkspaceNotification>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($user): void {
                $query->whereNull('ticket_id')
                    ->orWhereHas('ticket', fn (Builder $tickets) => $tickets->visibleTo($user));
            });
    }

    protected static function booted(): void
    {
        static::created(function (WorkspaceNotification $notification): void {
            app(ImmediateNotificationSender::class)->send(
                $notification->user,
                new WorkspaceAlert($notification),
            );
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

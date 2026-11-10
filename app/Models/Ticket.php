<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ticket_number',
    'requester_user_id',
    'assigned_to_user_id',
    'requester_name',
    'student_id',
    'requester_email',
    'requester_email_verified_at',
    'requester_email_verification_token_hash',
    'requester_email_verification_expires_at',
    'requester_phone',
    'department_id',
    'category_id',
    'subject',
    'description',
    'priority',
    'status',
    'assigned_at',
    'first_assigned_at',
    'resolved_at',
    'last_resolved_at',
    'last_reopened_at',
    'resolution_warning_at',
    'resolution_due_at',
    'sla_paused_minutes',
    'sla_paused_at',
])]
class Ticket extends Model
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->department_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $query->getModel()->qualifyColumn('department_id'),
            $user->department_id,
        );
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin()
            || ($user->department_id !== null && $this->department_id === $user->department_id);
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'requester_email_verified_at' => 'datetime',
            'requester_email_verification_expires_at' => 'datetime',
            'first_assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_resolved_at' => 'datetime',
            'last_reopened_at' => 'datetime',
            'resolution_warning_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'sla_paused_minutes' => 'integer',
            'sla_paused_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    public function misuseReports(): HasMany
    {
        return $this->hasMany(TicketMisuseReport::class);
    }

    public function canEmailRequester(): bool
    {
        return $this->requester_email_verified_at !== null
            || $this->requester_email_verification_token_hash === null;
    }

    public function requesterEmailVerificationPending(): bool
    {
        return $this->requester_email_verification_token_hash !== null
            && $this->requester_email_verified_at === null;
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }
}

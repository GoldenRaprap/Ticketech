<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    'requester_phone',
    'subject',
    'description',
    'priority',
    'status',
    'assigned_at',
    'first_assigned_at',
    'resolved_at',
    'last_resolved_at',
    'last_reopened_at',
])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'first_assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_resolved_at' => 'datetime',
            'last_reopened_at' => 'datetime',
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

    public function getRouteKeyName(): string
    {
        return 'ticket_number';
    }
}

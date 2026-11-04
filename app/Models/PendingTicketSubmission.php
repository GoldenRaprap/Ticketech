<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'requester_user_id',
    'requester_name',
    'requester_email',
    'payload',
    'attachments',
    'verification_token_hash',
    'expires_at',
])]
class PendingTicketSubmission extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attachments' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}

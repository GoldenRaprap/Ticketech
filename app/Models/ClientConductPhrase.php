<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phrase', 'reason', 'is_active'])]
class ClientConductPhrase extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

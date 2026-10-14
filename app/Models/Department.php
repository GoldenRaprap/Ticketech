<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_active'])]
class Department extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'actor_id', 'user_name', 'user_email', 'user_role', 'actor_name', 'before_is_active', 'after_is_active', 'reason'])]
class AccountStatusChange extends Model
{
    protected function casts(): array
    {
        return ['before_is_active' => 'boolean', 'after_is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['certification_body_id', 'actor_id', 'body_name', 'owner_email', 'actor_name', 'action', 'before_status', 'after_status', 'reason'])]
class InstitutionStatusChange extends Model
{
    public function certificationBody(): BelongsTo
    {
        return $this->belongsTo(CertificationBody::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Training extends Model
{
    use HasFactory;

    protected $fillable = ['certification_body_id', 'title', 'description', 'mode', 'duration_hours', 'weekly_hours', 'cost', 'source_url', 'status', 'version'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'version' => 'integer'];
    }

    public function body(): BelongsTo
    {
        return $this->belongsTo(CertificationBody::class, 'certification_body_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'training_skills')->withPivot('target_level')->withTimestamps();
    }
}

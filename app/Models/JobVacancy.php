<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobVacancy extends Model
{
    use HasFactory;

    public const TYPES = ['permanent' => 'Tetap', 'contract' => 'Kontrak'];

    public const STATUSES = ['draft' => 'Draf', 'active' => 'Aktif', 'inactive' => 'Nonaktif'];

    protected $fillable = ['title', 'company', 'location', 'description', 'employment_type', 'source_url', 'source_name', 'fetched_at', 'expires_at', 'status'];

    protected function casts(): array
    {
        return ['fetched_at' => 'date', 'expires_at' => 'date'];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_required_skills')->withPivot(['minimum_level', 'importance', 'weight', 'is_override'])->withTimestamps();
    }
}

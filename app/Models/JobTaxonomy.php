<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobTaxonomy extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'normalized_name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_taxonomy_skill')->withTimestamps();
    }
}

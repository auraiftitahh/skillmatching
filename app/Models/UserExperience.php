<?php

namespace App\Models;

use Database\Factories\UserExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'organization', 'title', 'start_date', 'end_date', 'is_current', 'description'])]
class UserExperience extends Model
{
    /** @use HasFactory<UserExperienceFactory> */
    use HasFactory;

    public const TYPES = ['work' => 'Pekerjaan', 'education' => 'Pendidikan', 'organization' => 'Organisasi'];

    public const FIELD_LABELS = [
        'work' => ['Nama perusahaan', 'Posisi pekerjaan'],
        'education' => ['Nama institusi pendidikan', 'Program studi atau jenjang'],
        'organization' => ['Nama organisasi', 'Peran dalam organisasi'],
    ];

    protected $attributes = ['is_current' => false];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

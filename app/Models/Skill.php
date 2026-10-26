<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function normalize(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    protected static function booted(): void
    {
        static::created(function (Skill $skill): void {
            DB::table('skill_names')->insert([
                'skill_id' => $skill->id, 'name' => $skill->name,
                'normalized_name' => self::normalize($skill->name), 'is_primary' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}

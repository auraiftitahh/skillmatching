<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class CatalogSkillService
{
    /** Resolve a catalog name inside the caller's transaction. */
    public function resolveName(string $name, string $category): int
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $normalized = mb_strtolower($name);
        $registry = DB::table('skill_name_registry')->where('normalized_name', $normalized);
        $registered = $registry->lockForUpdate()->value('skill_id');
        if ($registered) {
            return (int) $registered;
        }

        $existing = DB::table('skills')->whereRaw('LOWER(name) = ?', [$normalized]);
        $skillId = $existing->value('id');
        if (!$skillId) {
            DB::table('skills')->insertOrIgnore([
                'name' => $name,
                'category' => $category,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $skillId = DB::table('skills')->where('name', $name)->lockForUpdate()->firstOrFail()->id;
        }

        DB::table('skill_name_registry')->insertOrIgnore(['normalized_name' => $normalized, 'skill_id' => $skillId]);

        return (int) $registry->lockForUpdate()->firstOrFail()->skill_id;
    }
}

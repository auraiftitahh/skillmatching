<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

class WeightConfiguration extends Model
{
    use HasFactory;

    protected $fillable = ['version', 'required_weight', 'preferred_weight', 'related_factor', 'threshold', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'required_weight' => 'integer', 'preferred_weight' => 'integer', 'related_factor' => 'float', 'threshold' => 'float'];
    }

    public static function current(): self
    {
        return static::findOrFail(DB::table('weight_configuration_state')->where('id', 1)->value('active_configuration_id'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Konfigurasi yang telah diterbitkan tidak dapat ditimpa. Terbitkan versi baru.');
        });
        static::deleting(function (): void {
            throw new LogicException('Riwayat konfigurasi tidak dapat dihapus.');
        });
    }
}

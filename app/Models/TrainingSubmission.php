<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSubmission extends Model
{
    use HasFactory;

    public const LABELS = ['draft' => 'Draf', 'pending' => 'Menunggu tinjauan', 'revision' => 'Perlu perbaikan', 'rejected' => 'Ditolak', 'approved' => 'Disetujui'];

    protected $fillable = ['certification_body_id', 'training_id', 'base_version', 'payload', 'status', 'review_note', 'reviewed_by', 'submitted_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['base_version' => 'integer', 'payload' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function body(): BelongsTo
    {
        return $this->belongsTo(CertificationBody::class, 'certification_body_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function statusLabel(): string
    {
        return self::LABELS[$this->status] ?? 'Status tidak tersedia';
    }
}

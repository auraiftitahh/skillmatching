<?php

namespace App\Support;

use App\Models\CertificationBody;

class InstitutionReviewActions
{
    public const LABELS = [
        'pending' => 'Menunggu persetujuan',
        'active' => 'Disetujui',
        'rejected' => 'Ditolak',
        'disabled' => 'Dinonaktifkan',
    ];

    public const ACTION_LABELS = [
        'approve' => 'Persetujuan lembaga',
        'reject' => 'Penolakan pengajuan',
        'activate' => 'Pengaktifan kembali lembaga',
        'deactivate' => 'Penonaktifan lembaga',
    ];

    public static function forBody(CertificationBody $body): array
    {
        return match ($body->status) {
            CertificationBody::STATUS_PENDING => ['approve' => 'Setujui lembaga', 'reject' => 'Tolak pengajuan'],
            CertificationBody::STATUS_REJECTED => ['approve' => 'Setujui setelah tinjauan ulang'],
            CertificationBody::STATUS_ACTIVE => ['deactivate' => 'Nonaktifkan lembaga'],
            CertificationBody::STATUS_DISABLED => $body->verified_at
                ? ['activate' => 'Aktifkan kembali lembaga']
                : ['approve' => 'Setujui setelah tinjauan'],
            default => [],
        };
    }
}

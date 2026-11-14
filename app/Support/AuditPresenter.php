<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Mengubah catatan audit teknis menjadi penjelasan yang layak ditampilkan kepada Admin. */
final class AuditPresenter
{
    private const ACTIONS = [
        'account.activate' => 'Mengaktifkan akun',
        'account.deactivate' => 'Menonaktifkan akun',
        'account.verify' => 'Memverifikasi lembaga',
        'account.reject' => 'Menolak verifikasi lembaga',
        'catalog.create' => 'Menambahkan data',
        'catalog.save' => 'Memperbarui data',
        'catalog.status' => 'Mengubah status data',
        'catalog.bulk_skills' => 'Memperbarui keterampilan terpilih',
        'skill_submission.approved' => 'Menyetujui usulan keterampilan',
        'skill_submission.rejected' => 'Menolak usulan keterampilan',
        'training_submission.approved' => 'Menyetujui pengajuan pelatihan',
        'training_submission.revision' => 'Meminta perbaikan pengajuan',
        'training_submission.rejected' => 'Menolak pengajuan pelatihan',
        'vacancy_submission.approved' => 'Menyetujui dan menerbitkan lowongan terkait',
        'vacancy_submission.revision' => 'Meminta perbaikan lowongan terkait',
        'vacancy_submission.rejected' => 'Menolak lowongan terkait',
        'external_certificate.submit' => 'Menyimpan pengajuan bukti eksternal',
        'external_certificate.edit' => 'Memperbarui bukti eksternal',
        'external_certificate.link_issuer' => 'Menghubungkan bukti dengan penerbit',
        'external_certificate.withdraw' => 'Menarik pengajuan bukti',
        'external_certificate.verified' => 'Memverifikasi bukti eksternal',
        'external_certificate.rejected' => 'Menolak bukti eksternal',
        'skill.status' => 'Mengubah status keterampilan',
        'skill.delete' => 'Menghapus keterampilan',
        'catalog.csv_import' => 'Mengimpor data',
        'weight.publish' => 'Menerbitkan aturan pencocokan',
        'vacancy.requirement' => 'Memperbarui persyaratan lowongan',
        'training.skill' => 'Memperbarui capaian pelatihan',
        'training.prerequisite' => 'Mengatur prasyarat pelatihan',
        'taxonomy.mapping' => 'Memetakan keterampilan jabatan',
        'taxonomy.relation' => 'Menghubungkan keterampilan',
        'relation.remove' => 'Menghapus hubungan data',
        'graduation.confirm' => 'Mengonfirmasi kelulusan',
        'certificate.verified' => 'Menyetujui sertifikat',
        'certificate.rejected' => 'Menolak sertifikat',
        'evaluation.profile' => 'Menyimpan profil evaluasi',
        'evaluation.pair' => 'Menyimpan penilaian evaluasi',
    ];

    private const SUBJECTS = [
        'training_submissions'=>['Pengajuan pelatihan','payload'],
        'external_certificates'=>['Bukti eksternal','title'],
        'skills' => ['Keterampilan', 'name'],
        'jobs_taxonomy' => ['Jabatan', 'name'],
        'job_vacancies' => ['Lowongan', 'title'],
        'trainings' => ['Pelatihan', 'title'],
        'users' => ['Akun', 'name'],
        'certificates' => ['Sertifikat', 'title'],
        'weight_configurations' => ['Konfigurasi bobot', 'version'],
        'training_enrollments' => ['Peserta pelatihan', null],
        'job_required_skills' => ['Persyaratan lowongan', null],
        'training_skills' => ['Capaian pelatihan', null],
        'training_prerequisites' => ['Prasyarat pelatihan', null],
        'job_skills_mapping' => ['Pemetaan jabatan', null],
        'skill_relations' => ['Hubungan keterampilan', null],
        'evaluation_profiles' => ['Profil uji', 'code'],
        'evaluation_pairs' => ['Pasangan evaluasi', null],
    ];

    public static function entries(Collection $logs): Collection
    {
        if ($logs->isEmpty()) return collect();

        $actors = DB::table('users')->whereIn('id', $logs->pluck('user_id')->filter()->unique()->all())
            ->pluck('name', 'id');
        $nameMaps = [];
        foreach (self::SUBJECTS as $table => [$label, $field]) {
            if ($field === null) continue;
            $ids = $logs->where('subject_type', $table)->pluck('subject_id')->filter()->unique()->all();
            $nameMaps[$table] = $ids ? DB::table($table)->whereIn('id', $ids)->pluck($field, 'id') : collect();
        }

        return $logs->map(static function ($entry) use ($actors, $nameMaps) {
            $details = json_decode((string) ($entry->details ?? '{}'), true);
            if (!is_array($details)) $details = [];
            [$kind] = self::SUBJECTS[$entry->subject_type] ?? ['Data platform'];
            $name = $nameMaps[$entry->subject_type][$entry->subject_id] ?? $details['name'] ?? null;
            if ($entry->subject_type === 'weight_configurations' && $name) $name = 'Versi '.$name;

            if($entry->subject_type==='training_submissions' && $name)$name=json_decode($name,true)['title']??'Pengajuan pelatihan';
            $description = [];
            if (filled($details['skill'] ?? null)) $description[] = 'Skill: '.$details['skill'];
            if (filled($details['status'] ?? null)) $description[] = 'Status: '.$details['status'];
            if (filled($details['note'] ?? null)) $description[] = 'Catatan: '.$details['note'];
            if (filled($details['reason'] ?? null)) $description[] = 'Alasan: '.$details['reason'];
            if (isset($details['success'])) $description[] = 'Berhasil: '.(int)$details['success'].' data';
            if (isset($details['errors'])) $description[] = 'Perlu diperbaiki: '.(int)$details['errors'].' data';

            return (object) [
                'when' => Display::date($entry->created_at,true),
                'actor' => $actors[$entry->user_id] ?? 'Sistem / akun tidak tersedia',
                'label' => self::ACTIONS[$entry->action] ?? 'Memperbarui data platform',
                'subject' => $kind.($name ? ' — '.$name : ''),
                'description' => implode(' · ', $description),
            ];
        });
    }
}

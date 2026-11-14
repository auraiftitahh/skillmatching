<?php
namespace Database\Seeders;

use App\Models\User;
use App\Support\Publication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        if(!filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOLEAN))return;
        if(app()->environment('production') && !filter_var(env('ALLOW_DEMO_IN_PRODUCTION',false),FILTER_VALIDATE_BOOLEAN))return;
        $body = DB::table('certification_bodies')
            ->whereIn('id', Publication::bodyIds())
            ->where('user_id', User::where('email', 'kontak@logistiknusantara.id')->value('id'))
            ->first();
        if (!$body) {
            return;
        }

        $this->seedReviewStages($body);
        foreach(['pending'=>'Identifikasi Bahaya di Gudang','draft'=>'Praktik Pengemasan Aman','revision'=>'Pencatatan Inventori Digital','rejected'=>'Kompetensi Operasional Umum'] as $status=>$title){
            $name='Demo Pengajuan — '.$title;
            if(DB::table('training_submissions')->where('certification_body_id',$body->id)->where('payload','like','%'.$title.'%')->exists())continue;
            $payload=['title'=>$name,'description'=>'Contoh pengajuan untuk menguji alur tinjauan. Pelatihan dilaksanakan di luar aplikasi.','mode'=>'offline','duration_hours'=>8,'weekly_hours'=>4,'cost'=>100000,'source_url'=>'https://example.com/demo-training','outcomes'=>[['new_name'=>$title,'category'=>'Logistik','description'=>'Kemampuan operasional pada konteks gudang, memerlukan peninjauan makna sebelum diterima sebagai skill baku.','target_level'=>2]]];
            DB::table('training_submissions')->insert(['certification_body_id'=>$body->id,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'status'=>$status,'review_note'=>in_array($status,['revision','rejected'])?'Perjelas batas kompetensi dan periksa padanan skill dalam katalog.':null,'submitted_at'=>$status!=='draft'?now():null,'created_at'=>now(),'updated_at'=>now()]);
        }
    }

    private function seedReviewStages(object $body): void
    {
        $skillId = DB::table('skills')->where('name', 'Administrasi Dokumen')->where('is_active', true)->value('id');
        $reviewerId = User::where('email', env('OWNER_EMAIL', 'admin@skillmatching.local'))->where('role', 'admin')->value('id');
        if (!$skillId || !$reviewerId) {
            return;
        }

        $proposal = [
            'new_name' => 'Penelusuran Jejak Arsip',
            'category' => 'Administrasi',
            'description' => 'Menelusuri perpindahan dokumen melalui catatan pengelolaan arsip.',
            'target_level' => 4,
        ];
        $approvedReview = [
            'decision' => 'approved',
            'note' => 'Kompetensi sesuai dengan Administrasi Dokumen di katalog.',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now()->toDateTimeString(),
        ];
        $reviewed = [
            ...$proposal,
            'new_name' => 'Pengurusan Dokumen Kantor',
            'description' => 'Mencatat, menata, dan mencari kembali dokumen operasional kantor.',
            'resolved_skill_id' => (int) $skillId,
            'skill_review' => $approvedReview,
        ];
        $stages = [
            'skills' => ['Demo Alur 1 - Tinjau Skill Baru', 'pending', [
                $proposal,
                [
                    ...$proposal, 'new_name' => 'Pemeriksaan Retensi Arsip',
                    'description' => 'Memeriksa masa simpan arsip dan mencatat arsip yang perlu ditindaklanjuti.',
                    'target_level' => 2,
                ],
            ]],
            'training' => ['Demo Alur 2 - Tinjau Pelatihan', 'pending', [$reviewed]],
            'vacancy' => ['Demo Alur 3 - Tinjau Lowongan', 'approved', [$reviewed]],
            'revision' => ['Demo Alur Revisi - Perbaiki Skill', 'revision', [
                $reviewed,
                [
                    ...$proposal, 'new_name' => 'Pengelolaan Arsip Umum',
                    'skill_review' => [
                        ...$approvedReview, 'decision' => 'rejected',
                        'note' => 'Perjelas jenis arsip dan kegiatan yang akan dinilai.',
                    ],
                ],
            ]],
        ];

        DB::transaction(function () use ($body, $stages, $skillId, $reviewerId): void {
            DB::table('certification_bodies')->where('id', $body->id)->lockForUpdate()->first();

            foreach ($stages as $stage => [$title, $status, $outcomes]) {
                $scenario = 'training-review-'.$stage;
                if (DB::table('training_submissions')->where('certification_body_id', $body->id)
                    ->where('payload->demo_scenario', $scenario)->exists()) {
                    continue;
                }

                $training = [
                    'title' => $title,
                    'description' => 'Contoh sintetis pengajuan lembaga untuk latihan pengelolaan dokumen dan arsip.',
                    'mode' => 'offline', 'duration_hours' => 12, 'weekly_hours' => 4, 'cost' => 0,
                    'source_url' => 'https://example.com/demo-training',
                ];
                $payload = [
                    ...$training, 'demo_scenario' => $scenario, 'outcomes' => $outcomes,
                    'vacancy' => [
                        'title' => $title.' - Petugas Arsip', 'company' => 'PT Contoh Arsip',
                        'location' => 'Surabaya', 'employment_type' => 'Kontrak',
                        'description' => 'Contoh sintetis lowongan untuk latihan peninjauan persyaratan skill.',
                        'source_url' => 'https://example.com/demo-vacancy',
                        'expires_at' => today()->addMonths(3)->toDateString(),
                        'requirements' => [['minimum_level' => 3, 'importance' => 'required']],
                        'status' => 'pending',
                    ],
                ];

                $trainingId = null;
                if ($status === 'approved') {
                    $trainingId = DB::table('trainings')->insertGetId([
                        ...$training, 'provider' => $body->name, 'source' => 'DEMO ALUR PENGAJUAN',
                        'status' => 'active', 'fetched_at' => today()->toDateString(),
                        'certification_body_id' => $body->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('training_skills')->insert([
                        'training_id' => $trainingId, 'skill_id' => $skillId, 'target_level' => $outcomes[0]['target_level'],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                DB::table('training_submissions')->insert([
                    'certification_body_id' => $body->id, 'training_id' => $trainingId,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'status' => $status,
                    'review_note' => $trainingId ? 'Pelatihan demo disetujui. Lowongan menunggu tinjauan.' : null,
                    'reviewed_by' => $trainingId ? $reviewerId : null, 'reviewed_at' => $trainingId ? now() : null,
                    'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }, 3);
    }
}

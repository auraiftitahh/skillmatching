<?php

namespace Tests\Feature;

use App\Models\{User, WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CertificationDetailNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WeightConfiguration::create([
            'version' => 1, 'required_weight' => 3, 'preferred_weight' => 1,
            'related_factor' => .5, 'threshold' => 40, 'is_active' => true,
        ]);
    }

    public function test_participant_list_is_scoped_to_selected_training(): void
    {
        $bodyAccount = User::create([
            'name'=>'Lembaga Uji', 'email'=>'issuer-detail@example.test',
            'password'=>'valid-password-for-testing', 'role'=>'sertifikasi',
            'is_active'=>true, 'email_verified_at'=>now(),
        ]);
        $bodyId = DB::table('certification_bodies')->insertGetId([
            'user_id'=>$bodyAccount->id, 'name'=>'Lembaga Uji', 'status'=>'active',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $trainingData = [
            'provider'=>'Pusat Pelatihan', 'mode'=>'online', 'cost'=>0,
            'duration_hours'=>8, 'weekly_hours'=>2, 'fetched_at'=>now()->toDateString(),
            'status'=>'active', 'certification_body_id'=>$bodyId,
            'created_at'=>now(), 'updated_at'=>now(),
        ];
        $firstTrainingId = DB::table('trainings')->insertGetId([
            ...$trainingData, 'title'=>'Pelatihan Forklift',
        ]);
        $secondTrainingId = DB::table('trainings')->insertGetId([
            ...$trainingData, 'title'=>'Pelatihan Spreadsheet',
        ]);
        foreach ([[$firstTrainingId,'Peserta Forklift','forklift'],[$secondTrainingId,'Peserta Spreadsheet','sheet']] as [$trainingId,$name,$mail]) {
            $applicant = User::create([
                'name'=>$name, 'email'=>$mail.'@example.test',
                'password'=>'valid-password-for-testing', 'role'=>'pelamar',
                'is_active'=>true, 'email_verified_at'=>now(),
            ]);
            DB::table('training_enrollments')->insert([
                'user_id'=>$applicant->id, 'training_id'=>$trainingId,
                'status'=>'waiting_confirmation', 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }

        $this->actingAs($bodyAccount);
        $this->get('/sertifikasi/pelatihan/peserta?training_id='.$firstTrainingId)
            ->assertOk()->assertSee('Peserta Forklift')->assertDontSee('Peserta Spreadsheet');
        $this->get('/sertifikasi/pelatihan/peserta?training_id='.$secondTrainingId)
            ->assertOk()->assertSee('Peserta Spreadsheet')->assertDontSee('Peserta Forklift');
        $this->get('/sertifikasi/pelatihan/peserta?training_id=999999')
            ->assertOk()->assertDontSee('Peserta Forklift')->assertDontSee('Peserta Spreadsheet');
    }
}

<?php
namespace App\Http\Controllers;
use App\Models\{JobVacancy,Training,Skill,JobTaxonomy,User,WeightConfiguration};
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class WorkspaceController {
  private const VIEWS = [
'pelamar'=>['bukti/form'=>'external-evidence-form','bukti/detail'=>'external-evidence-detail','mulai'=>'start','profil'=>'profile','riwayat'=>'experience-list','riwayat/form'=>'experience-form','saran-skill'=>'skill-suggestions','skill/konfirmasi'=>'skill-edit','profil/kesiapan'=>'readiness','dashboard'=>'dashboard','lowongan'=>'vacancy-list','lowongan/detail'=>'vacancy-detail','lowongan/skor'=>'vacancy-score','lowongan/kesenjangan'=>'gap','lowongan/riwayat'=>'history','pelatihan'=>'training-list','pelatihan/detail'=>'training-detail','rencana-belajar'=>'learning-plan','simulasi'=>'simulation-form','simulasi/hasil'=>'simulation-result','pelatihan/aktivitas'=>'training-progress','pelatihan/selesaikan'=>'training-progress','sertifikat'=>'certificate-list','sertifikat/ajukan'=>'certificate-form','sertifikat/detail'=>'certificate-detail','lamaran'=>'application-list','lamaran/form'=>'application-form','lamaran/detail'=>'application-detail','umpan-balik'=>'feedback','pengaturan'=>'settings'],
'admin'=>['pengajuan'=>'submissions','pengajuan/detail'=>'submission-detail','dashboard'=>'dashboard','skill'=>'catalog-list','skill/form'=>'catalog-form','skill/detail'=>'catalog-detail','jabatan'=>'catalog-list','jabatan/form'=>'catalog-form','jabatan/detail'=>'catalog-detail','jabatan/pemetaan'=>'taxonomy-map','lowongan'=>'catalog-list','lowongan/form'=>'catalog-form','lowongan/persyaratan'=>'vacancy-skills','lowongan/detail'=>'catalog-detail','lowongan/impor'=>'import','lowongan/hasil-impor'=>'import','pelatihan'=>'catalog-list','pelatihan/form'=>'catalog-form','pelatihan/skill'=>'training-skills','pelatihan/prasyarat'=>'prerequisites','pelatihan/detail'=>'catalog-detail','pelatihan/impor'=>'import','pelatihan/hasil-impor'=>'import','bobot'=>'weights','bobot/baru'=>'weights-form','bobot/riwayat'=>'weights-history','bobot/detail'=>'weights-detail','evaluasi'=>'evaluation-dashboard','evaluasi/profil'=>'evaluation-profiles','evaluasi/pasangan'=>'evaluation-pairs','evaluasi/penilaian'=>'evaluation-pairs','evaluasi/umpan-balik'=>'feedback-list','laporan'=>'report','laporan/ekspor'=>'report','akun'=>'accounts','akun/detail'=>'account-detail','akun/verifikasi'=>'account-detail','akun/status'=>'account-detail','aktivitas'=>'audit'],
'sertifikasi'=>['pengajuan'=>'submissions','pengajuan/form'=>'submission-form','pengajuan/detail'=>'submission-detail','bukti'=>'external-evidence-list','bukti/detail'=>'external-evidence-detail','menunggu-verifikasi'=>'pending','dashboard'=>'dashboard','pelatihan'=>'trainings','pelatihan/peserta'=>'participants','pelatihan/peserta/detail'=>'participant-detail','pelatihan/kelulusan'=>'participant-detail','sertifikat'=>'certificates','sertifikat/detail'=>'certificate-detail','sertifikat/keputusan'=>'certificate-detail','riwayat'=>'history','akun'=>'profile']
];
  public const TITLES = [
    'pelamar'=>['bukti/form'=>'Perbarui Bukti Eksternal','bukti/detail'=>'Detail Bukti Kompetensi','mulai'=>'Selamat Datang','profil'=>'Profil Dasar','riwayat'=>'Profil dan Riwayat','riwayat/form'=>'Tambah atau Ubah Riwayat','saran-skill'=>'Saran Keterampilan','skill/konfirmasi'=>'Konfirmasi Skill dan Level','profil/kesiapan'=>'Kesiapan Profil','dashboard'=>'Beranda','lowongan'=>'Pilih Posisi Tujuan','lowongan/detail'=>'Detail Lowongan','lowongan/skor'=>'Rincian Skor','lowongan/kesenjangan'=>'Analisis Kesenjangan','lowongan/riwayat'=>'Riwayat Rekomendasi','pelatihan'=>'Rekomendasi Pelatihan','pelatihan/detail'=>'Detail Pelatihan','rencana-belajar'=>'Rencana Belajar','simulasi'=>'Simulasi Skor','simulasi/hasil'=>'Hasil Simulasi','pelatihan/aktivitas'=>'Aktivitas Pelatihan','pelatihan/selesaikan'=>'Penyelesaian Pelatihan','sertifikat'=>'Bukti Kompetensi','sertifikat/ajukan'=>'Tambah Bukti Eksternal','sertifikat/detail'=>'Detail Verifikasi','lamaran'=>'Catatan Lamaran','lamaran/form'=>'Tambah atau Ubah Catatan','lamaran/detail'=>'Detail Lamaran','umpan-balik'=>'Umpan Balik','pengaturan'=>'Pengaturan'],
    'admin'=>['pengajuan'=>'Tinjauan Pengajuan','pengajuan/detail'=>'Tinjauan Pelatihan dan Skill','dashboard'=>'Dashboard Admin','skill'=>'Taksonomi Skill','skill/form'=>'Form Skill','skill/detail'=>'Detail Skill','jabatan'=>'Taksonomi Jabatan','jabatan/form'=>'Form Jabatan','jabatan/detail'=>'Detail Jabatan','jabatan/pemetaan'=>'Pemetaan Jabatan–Skill','lowongan'=>'Manajemen Lowongan','lowongan/form'=>'Form Lowongan','lowongan/persyaratan'=>'Persyaratan Skill Lowongan','lowongan/detail'=>'Detail Lowongan','lowongan/impor'=>'Impor CSV Lowongan','lowongan/hasil-impor'=>'Hasil Impor Lowongan','pelatihan'=>'Manajemen Pelatihan','pelatihan/form'=>'Form Pelatihan','pelatihan/skill'=>'Skill yang Diajarkan','pelatihan/prasyarat'=>'Prasyarat Pelatihan','pelatihan/detail'=>'Detail Pelatihan','pelatihan/impor'=>'Impor CSV Pelatihan','pelatihan/hasil-impor'=>'Hasil Impor Pelatihan','bobot'=>'Konfigurasi Bobot','bobot/baru'=>'Buat Versi Bobot','bobot/riwayat'=>'Riwayat Versi Bobot','bobot/detail'=>'Detail Versi Bobot','evaluasi'=>'Evaluasi Kualitas','evaluasi/profil'=>'Profil Uji','evaluasi/pasangan'=>'Pasangan Profil–Lowongan','evaluasi/penilaian'=>'Penilaian Ahli','evaluasi/umpan-balik'=>'Feedback Pelamar','laporan'=>'Laporan Evaluasi','laporan/ekspor'=>'Ekspor Laporan','akun'=>'Akun Platform','akun/detail'=>'Detail Akun','akun/verifikasi'=>'Verifikasi Akun Lembaga','akun/status'=>'Status Akun','aktivitas'=>'Riwayat Aktivitas'],
    'sertifikasi'=>['pengajuan'=>'Pengajuan Pelatihan','pengajuan/form'=>'Susun Pengajuan Pelatihan','pengajuan/detail'=>'Detail Pengajuan','bukti'=>'Bukti Eksternal','bukti/detail'=>'Tinjau Bukti Eksternal','menunggu-verifikasi'=>'Menunggu Verifikasi Admin','dashboard'=>'Dashboard Lembaga','pelatihan'=>'Pelatihan Lembaga','pelatihan/peserta'=>'Peserta Pelatihan','pelatihan/peserta/detail'=>'Detail Peserta','pelatihan/kelulusan'=>'Konfirmasi Kelulusan','sertifikat'=>'Permintaan Verifikasi Sertifikat','sertifikat/detail'=>'Detail Sertifikat','sertifikat/keputusan'=>'Keputusan Sertifikat','riwayat'=>'Riwayat Proses','akun'=>'Informasi Lembaga']
  ];
  public function home(Request $r){return redirect('/'.$r->user()->role.'/dashboard');}
  public function show(Request $r,string $role,string $page,MatchingService $match){
    $u=$r->user();abort_unless($u->role===$role,403);
    abort_unless(isset(self::TITLES[$role][$page]),404);
    if($role==='admin' && in_array($page,['akun/detail','akun/verifikasi','akun/status'],true) && $r->integer('id')>0){abort_if(User::whereKey($r->integer('id'))->where('role','admin')->exists(),404);}
    if($role==='admin' && in_array($page,['pelatihan/form','pelatihan/skill','pelatihan/impor','pelatihan/hasil-impor']))return redirect('/admin/pengajuan')->with('success','Pelatihan dibuat dan direvisi melalui pengajuan lembaga.');
    if($role==='admin' && $page==='skill/form' && !$r->integer('id'))return redirect('/admin/pengajuan')->with('success','Skill baru berasal dari usulan lembaga.');
    $body=$u->certificationBody;
    if($role==='sertifikasi' && $body?->status!=='active' && $page!=='menunggu-verifikasi')return redirect('/sertifikasi/menunggu-verifikasi');
    if($role==='sertifikasi' && $body?->status==='active' && $page==='menunggu-verifikasi')return redirect('/sertifikasi/dashboard');
    $data=['u'=>$u,'role'=>$role,'page'=>$page,'title'=>self::TITLES[$role][$page],
      'navigation'=>self::TITLES[$role], 'viewKey'=>self::VIEWS[$role][$page], 'body'=>$body, 'skills'=>Skill::where('is_active',true)->orderBy('name')->get(),
      'jobsTax'=>JobTaxonomy::when($role==='pelamar',fn($q)=>$q->where('is_active',true))->orderBy('name')->get(),'config'=>WeightConfiguration::where('is_active',true)->first(),
      'allBodies'=>DB::table('certification_bodies')->whereIn('id',\App\Support\Publication::bodyIds())->get(),
      'allTraining'=>\App\Support\Publication::trainings()->get(),
      'allVacancies'=>\App\Support\Publication::jobs()->get()];
    if($role==='pelamar'){
      $data['experiences']=$u->experiences()->latest()->get();
      $data['ownedSkills']=DB::table('user_skills as us')->join('skills as s','s.id','=','us.skill_id')->where('us.user_id',$u->id)->select('us.*','s.name')->get();
      $data['levels']=$match->levels($u);$data['rank']=$match->rank($u,true);
      $r->validate(['job_id'=>'nullable|integer|min:1','jobs'=>'nullable|array|max:60','jobs.*'=>'integer|min:1|distinct','training_id'=>'nullable|integer|min:1','training_ids'=>'nullable|array|max:20','training_ids.*'=>'integer|min:1|distinct','budget'=>'nullable|numeric|min:0','hours'=>'nullable|integer|min:1|max:168','mode'=>'nullable|in:online,offline,hybrid']);
      $ids=array_values(array_unique(array_filter(array_map('intval',(array)$r->input('jobs',[])))));
      if($r->filled('job_id'))$ids=[$r->integer('job_id')];
      abort_unless(count($ids)===$data['allVacancies']->whereIn('id',$ids)->count(),404,'Lowongan target tidak tersedia.');
      $data['selectedJobIds']=$ids;
      $data['currentJob']=count($ids)===1?$data['allVacancies']->firstWhere('id',$ids[0]):null;
      $data['score']=$data['currentJob']?$match->score($data['currentJob'],$data['levels']):null;
      $data['gaps']=$match->gap($data['selectedJobIds'],$data['levels']);
      $data['options']=$match->trainingOptions($data['gaps'],$data['levels'],$r->filled('budget')?(float)$r->budget:null,$r->filled('hours')?(int)$r->hours:null,$r->mode);
      $data['path']=$match->learningPath($data['options'],DB::table('training_enrollments')->where('user_id',$u->id)->where('status','completed')->pluck('training_id')->all());
      $data['recommendations']=DB::table('recommendation_histories')->where('user_id',$u->id)->latest()->get();
      $data['configVersions']=WeightConfiguration::pluck('version','id');
      $data['applications']=DB::table('job_applications')->where('user_id',$u->id)->latest()->get();
      $data['certificates']=DB::table('certificates')->where('user_id',$u->id)->latest()->get();
      $data['enrollments']=DB::table('training_enrollments as e')->join('trainings as t','t.id','=','e.training_id')->where('e.user_id',$u->id)->select('e.*','t.title as training_title')->get();
      if($page==='simulasi/hasil' && $data['currentJob']) {
        $trainingIds=array_values(array_unique(array_filter(array_map('intval',(array)$r->input('training_ids',[$r->integer('training_id')])))));
        if($trainingIds)$data['simulation']=$match->simulate($u,$data['currentJob'],$trainingIds);
      }
    }elseif($role==='admin'){
      // Halaman detail selalu mengambil data berdasarkan ID yang diminta, bukan dari
      // daftar hasil filter/paginasi. Ini mencegah detail akun/data tidak ditemukan
      // hanya karena barisnya berada pada halaman daftar yang berbeda.
      $detailTables = [
        'skill/detail' => 'skills',
        'jabatan/detail' => 'jobs_taxonomy',
        'lowongan/detail' => 'job_vacancies',
        'pelatihan/detail' => 'trainings',
      ];
      if (isset($detailTables[$page])) {
        $detailId = $r->integer('id');
        $data['catalogDetail'] = $detailId > 0 ? DB::table($detailTables[$page])->find($detailId) : null;
        $data['catalogDetailRelations'] = collect();
        $data['catalogDetailPrerequisites'] = collect();
        if ($data['catalogDetail']) {
          if ($page === 'jabatan/detail') {
            $data['catalogDetailRelations'] = DB::table('job_skills_mapping as m')
              ->join('skills as s', 's.id', '=', 'm.skill_id')
              ->where('m.job_taxonomy_id', $detailId)
              ->select('s.id', 's.name', 's.category', 's.is_active')
              ->orderBy('s.name')->get();
          } elseif ($page === 'skill/detail') {
            $data['catalogDetailRelations'] = DB::table('job_skills_mapping as m')
              ->join('jobs_taxonomy as j', 'j.id', '=', 'm.job_taxonomy_id')
              ->where('m.skill_id', $detailId)
              ->select('j.id', 'j.name', 'j.is_active')->orderBy('j.name')->get();
          } elseif ($page === 'lowongan/detail') {
            $data['catalogDetailRelations'] = DB::table('job_required_skills as r')
              ->join('skills as s', 's.id', '=', 'r.skill_id')
              ->where('r.job_vacancy_id', $detailId)
              ->select('s.name', 'r.minimum_level', 'r.importance', 'r.weight')
              ->orderBy('s.name')->get();
          } elseif ($page === 'pelatihan/detail') {
            $data['catalogDetailRelations'] = DB::table('training_skills as ts')
              ->join('skills as s', 's.id', '=', 'ts.skill_id')
              ->where('ts.training_id', $detailId)
              ->select('s.name', 'ts.target_level')->orderBy('s.name')->get();
            $data['catalogDetailPrerequisites'] = DB::table('training_prerequisites as p')
              ->join('trainings as t', 't.id', '=', 'p.prerequisite_id')
              ->where('p.training_id', $detailId)
              ->select('t.id', 't.title')->orderBy('t.title')->get();
          }
        }
      }
      if (in_array($page, ['akun/detail', 'akun/verifikasi', 'akun/status'], true)) {
        $data['accountDetail'] = $r->integer('id') > 0
          ? User::whereKey($r->integer('id'))->whereIn('role', ['pelamar', 'sertifikasi'])->first()
          : null;
        if (!$data['accountDetail'] && $page === 'akun/verifikasi' && !$r->filled('id')) {
          $pendingAccountId = DB::table('certification_bodies')->where('status', 'pending')->value('user_id');
          $data['accountDetail'] = $pendingAccountId
            ? User::whereKey($pendingAccountId)->where('role', 'sertifikasi')->first() : null;
        }
        $data['accountDetailBody'] = $data['accountDetail']?->role === 'sertifikasi'
          ? DB::table('certification_bodies')->where('user_id', $data['accountDetail']->id)->first()
          : null;
      }
      // Daftar untuk Admin tidak dibatasi pada data aktif. Data nonaktif tetap tersimpan dan bisa dicari.
      if ($page === 'skill') {
        $search = trim((string) $r->query('q', ''));
        $search = mb_substr($search, 0, 120);
        $statusFilter = (string) $r->query('status', 'all');
        if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) $statusFilter = 'all';
        $query = Skill::query();
        if ($search !== '') {
          $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
              ->orWhere('category', 'like', '%'.$search.'%')
              ->orWhere('aliases', 'like', '%'.$search.'%');
          });
        }
        if ($statusFilter !== 'all') $query->where('is_active', $statusFilter === 'active');
        $data['skillListing'] = $query->orderBy('name')->paginate(15)->withQueryString();
        $data['skillSearch'] = $search;
        $data['skillStatusFilter'] = $statusFilter;
        $data['skillCounts'] = ['all'=>Skill::count(), 'active'=>Skill::where('is_active',true)->count(), 'inactive'=>Skill::where('is_active',false)->count()];
      }
      // Filter tersimpan di server agar pencarian tetap bekerja pada jumlah data besar.
      $keyword = trim(mb_substr((string)$r->query('q',''),0,100));
      $status = (string)$r->query('status','');
      if($page==='jabatan'){
        $query=JobTaxonomy::query();
        if($keyword!=='')$query->where('name','like','%'.$keyword.'%');
        if(in_array($status,['active','inactive'],true))$query->where('is_active',$status==='active');
        $data['filteredTaxonomy']=$query->orderBy('name')->paginate(15)->withQueryString();
      }
      if($page==='lowongan'){
        $query=JobVacancy::query();
        if($keyword!=='')$query->where(fn($q)=>$q->where('title','like','%'.$keyword.'%')->orWhere('company','like','%'.$keyword.'%')->orWhere('location','like','%'.$keyword.'%'));
        if(in_array($status,['draft','active','closed','expired','disabled'],true))$query->where('status',$status);
        $data['filteredVacancies']=$query->latest()->paginate(15)->withQueryString();
      }
      if($page==='pelatihan'){
        $query=Training::query();
        if($keyword!=='')$query->where(fn($q)=>$q->where('title','like','%'.$keyword.'%')->orWhere('provider','like','%'.$keyword.'%'));
        if(in_array($status,['draft','active','disabled'],true))$query->where('status',$status);
        $data['filteredTrainings']=$query->latest()->paginate(15)->withQueryString();
      }
      if($page==='akun'){
        $query=User::query()->whereIn('role',['pelamar','sertifikasi']);
        if($keyword!=='')$query->where(fn($q)=>$q->where('name','like','%'.$keyword.'%')->orWhere('email','like','%'.$keyword.'%'));
        if(in_array($r->query('role'),['pelamar','sertifikasi'],true))$query->where('role',$r->query('role'));
        if(in_array($status,['active','inactive'],true))$query->where('is_active',$status==='active');
        if($r->query('body_status')==='pending')$query->whereIn('id',DB::table('certification_bodies')->where('status','pending')->select('user_id'));
        $data['filteredUsers']=$query->latest()->paginate(15)->withQueryString();
      }
      $data['allJobs']=JobVacancy::latest()->get();$data['trainings']=Training::latest()->get();
      $data['records']=\App\Support\AuditPresenter::entries(DB::table('audit_logs')->latest()->limit(100)->get());
      $data['users']=($data['filteredUsers']??User::select('id','name','email','role','is_active','created_at')->where('role','!=','admin')->latest()->get());
      $data['bodies']=DB::table('certification_bodies')->get();
      $data['weights']=WeightConfiguration::orderByDesc('version')->get();
      $data['feedback']=DB::table('feedback')->latest()->get();
      $data['evalProfiles']=DB::table('evaluation_profiles')->get();$data['evalPairs']=DB::table('evaluation_pairs')->get();
      $data['skillNames']=Skill::pluck('name','id');
      $data['jobNames']=JobVacancy::pluck('title','id');
      $data['evalProfileNames']=$data['evalProfiles']->pluck('code','id');
      $data['metrics']=['skills'=>Skill::count(),'jobs'=>JobVacancy::count(),'trainings'=>Training::count(),'pending'=>DB::table('certification_bodies')->where('status','pending')->count()];
    }else{
      if($body?->status==='active'){
        $id=$body->id;
        $data['trainings']=Training::where('certification_body_id',$id)->get();
        $data['participants']=DB::table('training_enrollments as e')->join('trainings as t','t.id','=','e.training_id')->join('users as u','u.id','=','e.user_id')->where('t.certification_body_id',$id)->where('u.is_active',true)->whereNotNull('u.email_verified_at')->select('e.*','t.title as training_title','u.name as user_name')->get();
        $data['certificates']=DB::table('certificates as c')->join('users as u','u.id','=','c.user_id')->where('c.certification_body_id',$id)->where('u.is_active',true)->whereNotNull('u.email_verified_at')->select('c.*','u.name as applicant_name')->latest('c.created_at')->get();
      }
    }
    // Pencarian ringan untuk daftar milik pengguna. Objek tetap dibatasi oleh user/lembaga.
    $q = mb_strtolower(trim(mb_substr((string)$r->query('q',''),0,100)));
    $requestedStatus=(string)$r->query('status','');
    $requestedType=(string)$r->query('type','');
    $filterRows = static function($rows,array $searchFields,?string $stateField=null,?string $typeField=null)use($q,$requestedStatus,$requestedType){
      return collect($rows)->filter(static function($item)use($searchFields,$stateField,$typeField,$q,$requestedStatus,$requestedType){
        if($stateField!==null && $requestedStatus!=='' && (string)data_get($item,$stateField)!==$requestedStatus)return false;
        if($typeField!==null && $requestedType!=='' && (string)data_get($item,$typeField)!==$requestedType)return false;
        if($q==='')return true;
        foreach($searchFields as $field){if(str_contains(mb_strtolower((string)data_get($item,$field,'')),$q))return true;}
        return false;
      })->values();
    };
    if($role==='pelamar'){
      if($page==='riwayat')$data['experiences']=$filterRows($data['experiences'],['title','organization','description'],null,'type');
      if($page==='pelatihan')$data['options']=$filterRows($data['options'],['title','provider']);
      if($page==='pelatihan/aktivitas'){$data['enrollments']=$filterRows($data['enrollments'],['training_title'],'status');if($r->integer('training_id'))$data['enrollments']=$data['enrollments']->where('training_id',$r->integer('training_id'))->values();}
      if($page==='sertifikat')$data['certificates']=$filterRows($data['certificates'],['title'],'status');
      if($page==='lamaran')$data['applications']=$filterRows($data['applications'],['job_title','company'],'status');
      if($page==='lowongan/riwayat')$data['recommendations']=$filterRows($data['recommendations'],['created_at']);
      if($page==='rencana-belajar'){$data['enrollments']=$filterRows($data['enrollments'],['training_title'],'status');$data['path']=$filterRows($data['path'],['title']);}
    }
    if($role==='admin'){
      if($page==='evaluasi/profil')$data['evalProfiles']=$filterRows($data['evalProfiles'],['code','group']);
      if(in_array($page,['evaluasi/pasangan','evaluasi/penilaian'],true)){
        $data['evalPairs']=collect($data['evalPairs'])->filter(static function($x)use($q,$requestedStatus,$data){
          $haystack=mb_strtolower(($data['evalProfileNames'][$x->evaluation_profile_id]??'').' '.($data['jobNames'][$x->job_vacancy_id]??''));
          return ($q===''||str_contains($haystack,$q))&&($requestedStatus===''||($requestedStatus==='rated'?$x->expert_rating!==null:$x->expert_rating===null));
        })->values();
      }
      if($page==='evaluasi/umpan-balik'){
        $data['feedback']=collect($data['feedback'])->filter(static fn($x)=>($requestedStatus===''||$x->rating===$requestedStatus)&&($q===''||str_contains(mb_strtolower(($data['jobNames'][$x->job_vacancy_id]??'').' '.($x->comment??'')),$q)))->values();
      }
      if($page==='bobot/riwayat')$data['weights']=$filterRows($data['weights'],['reason','version'])->filter(static fn($w)=>$requestedStatus===''||($requestedStatus==='active'?(bool)$w->is_active:!(bool)$w->is_active))->values();
      if($page==='aktivitas')$data['records']=$filterRows($data['records'],['actor','label','subject','description']);
    }
    if($role==='sertifikasi'&&$body?->status==='active'){
      if($page==='pelatihan')$data['trainings']=$filterRows($data['trainings'],['title','provider'],'status');
      if($page==='pelatihan/peserta'){
        // Tautan dari daftar pelatihan membawa training_id. Filter hanya dalam
        // data milik lembaga yang telah dibatasi pada query sebelumnya.
        if($r->filled('training_id')){
          $selectedTrainingId=$r->integer('training_id');
          $data['participants']=collect($data['participants'])
            ->filter(static fn($e)=>(int)$e->training_id===$selectedTrainingId)->values();
        }
        $data['participants']=$filterRows($data['participants'],['user_name','training_title'],'status');
      }
      if(in_array($page,['sertifikat','riwayat'],true))$data['certificates']=$filterRows($data['certificates'],['applicant_name','title'],'status');
    }
    $data['externalEvidence']=collect();
    if($role==='pelamar'){
      $data['externalEvidence']=DB::table('external_certificates')->where('user_id',$u->id)->latest()->get();
      $data['skillEvidence']=DB::table('skill_evidence')->where('user_id',$u->id)->get();
      if($page==='pelatihan' && $r->integer('skill_id'))$data['options']=collect($data['options'])->filter(fn($t)=>collect($t['coverage_items'])->contains(fn($g)=>(int)$g['skill_id']===$r->integer('skill_id') && $g['improvement']>0))->values()->all();
    }
    if($role==='sertifikasi' && $body?->status==='active')$data['externalEvidence']=DB::table('external_certificates as c')->join('users as u','u.id','=','c.user_id')->where('c.certification_body_id',$body->id)->where('u.is_active',true)->whereNotNull('u.email_verified_at')->select('c.*','u.name as applicant_name')->latest('c.created_at')->get();
    if($role==='sertifikasi' && $body?->status==='active' && $page==='riwayat')$data['submissionHistory']=DB::table('training_submissions')->where('certification_body_id',$body->id)->whereIn('status',['approved','revision','rejected'])->latest('reviewed_at')->get();
    if($role==='sertifikasi' && $page==='bukti')$data['externalEvidence']=$filterRows($data['externalEvidence'],['title','issuer_name','applicant_name'],'status');
    if($role==='pelamar' && $page==='bukti/form'){
      $data['evidenceEdit']=$r->integer('id')?$data['externalEvidence']->firstWhere('id',$r->integer('id')):null;
      $data['evidenceResubmit']=$r->integer('source_certificate_id')?$data['externalEvidence']->firstWhere('id',$r->integer('source_certificate_id')):null;
      abort_unless($data['evidenceEdit']||$data['evidenceResubmit'],404);
      abort_if($data['evidenceEdit'] && $data['evidenceResubmit'],422);
      if($data['evidenceEdit'])abort_unless(in_array($data['evidenceEdit']->status,['unverified','cancelled']),422,'Bukti sedang diproses atau sudah diputuskan.');
      if($data['evidenceResubmit'])abort_unless($data['evidenceResubmit']->status==='rejected',422,'Hanya bukti ditolak yang diajukan ulang melalui halaman ini.');
    }
    if($page==='bukti/detail')$data['evidenceDetail']=$data['externalEvidence']->firstWhere('id',$r->integer('id'))??abort(404);
    if(in_array($role,['admin','sertifikasi']) && str_starts_with($page,'pengajuan')){
      $query=DB::table('training_submissions as s')->join('certification_bodies as b','b.id','=','s.certification_body_id')->select('s.*','b.name as body_name');
      if($role==='sertifikasi')$query->where('s.certification_body_id',$body->id);
      if($role==='admin')$query->where('s.status','!=','draft');
      if($r->filled('status') && $page==='pengajuan' && $r->query('status')!=='vacancy_pending')$query->where('s.status',$r->query('status'));
      $data['submissions']=$query->latest('s.updated_at')->get();
      if($page==='pengajuan' && $r->query('status')==='vacancy_pending'){
        $data['submissions']=$data['submissions']->filter(fn($submission)=>$submission->status==='approved' && (json_decode($submission->payload,true)['vacancy']['status']??null)==='pending');
      }
      $data['submission']=$r->integer('id')?$data['submissions']->firstWhere('id',$r->integer('id')):null;
      if($r->integer('id') && !$data['submission'])abort(404);
      if($page==='pengajuan/detail' && !$data['submission'])abort(404);
      $data['submissionPayload']=$data['submission']?json_decode($data['submission']->payload,true):[];
      if($page==='pengajuan/form' && !$data['submission'] && $r->integer('training_id')){
        $training=DB::table('trainings')->where('id',$r->integer('training_id'))->where('certification_body_id',$body->id)->first()??abort(404);
        $data['submissionPayload']=[...(array)$training,'outcomes'=>DB::table('training_skills')->where('training_id',$training->id)->get()->map(fn($s)=>['skill_id'=>$s->skill_id,'target_level'=>$s->target_level])->all()];
      }
      if($page==='pengajuan/form' && $data['submission'])abort_unless(in_array($data['submission']->status,['draft','revision','rejected']),422,'Pengajuan tidak dapat diubah saat sedang ditinjau.');
    }
    return view('workspace',\App\Support\PageExperience::enrich($data,$r));
  }
}

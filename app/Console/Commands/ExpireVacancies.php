<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class ExpireVacancies extends Command {
    protected $signature = 'vacancies:expire';
    protected $description = 'Menandai lowongan aktif yang telah kedaluwarsa';
    public function handle(): int { $n=DB::table('job_vacancies')->where('status','active')->whereDate('expires_at','<',now()->toDateString())->update(['status'=>'expired','updated_at'=>now()]); $this->info("{$n} lowongan kedaluwarsa."); return self::SUCCESS; }
}

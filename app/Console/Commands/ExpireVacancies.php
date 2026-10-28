<?php

namespace App\Console\Commands;

use App\Models\JobVacancy;
use Illuminate\Console\Command;

class ExpireVacancies extends Command
{
    protected $signature = 'skillmatching:expire-jobs';

    protected $description = 'Menonaktifkan publikasi lowongan yang telah melewati batas berlaku';

    public function handle(): int
    {
        $count = JobVacancy::where('status', 'active')->whereDate('expires_at', '<', now('Asia/Jakarta')->toDateString())->update(['status' => 'inactive']);
        $this->info($count.' lowongan kedaluwarsa dinonaktifkan.');

        return self::SUCCESS;
    }
}

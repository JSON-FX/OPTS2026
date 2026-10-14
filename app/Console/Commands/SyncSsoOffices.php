<?php

namespace App\Console\Commands;

use App\Services\SSOOfficeService;
use Illuminate\Console\Command;

class SyncSsoOffices extends Command
{
    protected $signature = 'opts:sync-sso-offices';

    protected $description = 'Refresh office references from SSO while preserving local history';

    public function handle(SSOOfficeService $offices): int
    {
        $result = $offices->sync();
        $this->info("Synced {$result['active']} active SSO offices; {$result['unmapped']} historical offices remain unmapped.");

        return self::SUCCESS;
    }
}

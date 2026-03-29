<?php

namespace Edzero\LicenseClient\Commands;

use Edzero\LicenseClient\Services\LicenseService;
use Illuminate\Console\Command;

class LicenseHeartbeatCommand extends Command
{
    protected $signature = 'license:heartbeat';

    protected $description = 'Sinkronkan status lisensi dengan license-server (wajib berkala jika LICENSE_HEARTBEAT_ENABLED=true).';

    public function handle(LicenseService $licenses): int
    {
        $result = $licenses->runHeartbeat();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}

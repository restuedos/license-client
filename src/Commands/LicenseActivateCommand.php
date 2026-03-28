<?php

namespace Edzero\LicenseClient\Commands;

use Edzero\LicenseClient\Services\LicenseService;
use Illuminate\Console\Command;
use RuntimeException;

class LicenseActivateCommand extends Command
{
    protected $signature = 'license:activate {key? : License key value}';

    protected $description = 'Activate application license from CLI';

    public function handle(LicenseService $licenseService): int
    {
        if (! $licenseService->shouldEnforce()) {
            $this->info('License enforcement is off (empty LICENSE_VERIFY_URL or LICENSE_ENFORCEMENT_ENABLED=false).');

            return self::SUCCESS;
        }

        $key = (string) ($this->argument('key') ?? '');
        if ($key === '') {
            $key = (string) $this->secret('Masukkan license key');
        }

        if ($key === '') {
            $this->error('License key wajib diisi.');

            return self::FAILURE;
        }

        try {
            $result = $licenseService->activate($key);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $result['ok']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);

        return self::SUCCESS;
    }
}

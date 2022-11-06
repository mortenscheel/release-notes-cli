<?php

namespace App\Commands;

use App\LocalConfig;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;

class InitCacheCommand extends Command
{
    protected $signature = 'cache:init';

    protected $description = 'Initialize local caching';

    public function handle(LocalConfig $config): int
    {
        if ($config->databaseExists()) {
            $this->comment('Cache is already initialized');

            return self::SUCCESS;
        }
        $config->createDatabaseFile();
        Artisan::call('migrate', ['--force' => true]);
        $this->info("Cache created at {$config->getDatabasePath()}");

        return self::SUCCESS;
    }
}

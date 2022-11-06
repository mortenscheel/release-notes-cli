<?php

namespace App\Commands;

use Cache;
use LaravelZero\Framework\Commands\Command;

class CacheClearCommand extends Command
{
    protected $signature = 'cache:clear';

    protected $description = 'Clear the local cache';

    public function handle(): int
    {
        if (Cache::clear()) {
            $this->info('Cache flushed');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}

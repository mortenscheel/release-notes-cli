<?php

namespace App\Commands;

use Cache;
use LaravelZero\Framework\Commands\Command;

class CacheClearCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cache:clear';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Clear the application cache';

    public function handle(): int
    {
        if (Cache::clear()) {
            $this->info('Cache flushed');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}

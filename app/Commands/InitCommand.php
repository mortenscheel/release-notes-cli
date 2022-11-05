<?php

namespace App\Commands;

use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;

class InitCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'init';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dbPath = config('database.connections.sqlite.database');
        if (! file_exists($dbPath)) {
            if ($this->confirm("Create cache at $dbPath")) {
                if (touch($dbPath)) {
                    Artisan::call('migrate', ['--force' => true]);
                }
                $this->info('Preparing cache...');
            }
        }
    }
}

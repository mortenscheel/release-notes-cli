<?php

namespace App\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;
use function Termwind\render;

class OutdatedCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'outdated';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show release notes for newer version of outdated packages in current project';

    /**
     * Execute the console command.
     *
     * @return mixed
     *
     * @throws \JsonException
     */
    public function handle()
    {
        if (! file_exists(getcwd().'/composer.json')) {
            $this->error('No composer.json file found in current folder.');

            return self::FAILURE;
        }
        $process = Process::fromShellCommandline('composer outdated --direct --locked --format=json');
        $this->task('Analyzing outdated composer packages', fn () => $process->run(), 'running...');
        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput());

            return self::FAILURE;
        }
        $outdated = Arr::get(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'locked', []);
        if (empty($outdated)) {
            $this->info('No outdated packages found');

            return self::SUCCESS;
        }
        foreach ($outdated as $package) {
            $name = Arr::get($package, 'name');
            $currentVersion = Arr::get($package, 'version');
            $latestVersion = Arr::get($package, 'latest');
            render(<<<HTML
                <div class='w-full text-center bg-slate-700 text-white'>
                    <span class='font-bold'>$name</span> updated from
                    <span class='text-orange'>$currentVersion</span> to
                    <span class='text-green'>$latestVersion</span>
                </div>
                HTML
            );
            Artisan::call('release-notes', [
                'name' => Arr::get($package, 'name'),
                '--since' => Arr::get($package, 'version'),
            ], $this->output);
        }
    }
}

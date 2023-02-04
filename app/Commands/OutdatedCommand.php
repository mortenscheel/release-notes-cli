<?php

namespace App\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
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
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $outdated = collect(Arr::get($data, 'locked', []))
            ->filter(fn (array $package) => ! str_starts_with($package['version'], 'dev-'))
            ->mapWithKeys(fn (array $package) => [
                Arr::get($package, 'name') => [
                    'name' => Arr::get($package, 'name'),
                    'current' => Str::after(Arr::get($package, 'version'), 'v'),
                    'latest' => Str::after(Arr::get($package, 'latest'), 'v'),
                    'safe' => Arr::get($package, 'latest-status') === 'semver-safe-update',
                    'abandoned' => Arr::get($package, 'abandoned'),
                ],
            ]);
        if ($outdated->isEmpty()) {
            $this->info('No outdated packages found');

            return self::SUCCESS;
        }
        $this->showTable($outdated);

        return $this->promptChoice($outdated);
    }

    private function promptChoice(Collection $outdated): int
    {
        $choice = $this->anticipate('Select package (or quit/table)', $outdated->keys()->push('table', 'quit'));
        if ($choice === 'quit') {
            return self::SUCCESS;
        }
        if ($choice === 'table') {
            $this->showTable($outdated);

            return $this->promptChoice($outdated);
        }
        if (! $outdated->has($choice)) {
            $this->warn("No outdated package with name: $choice");

            return $this->promptChoice($outdated);
        }
        $versions = $outdated->get($choice);
        render(<<<HTML
                <div class='w-full py-1 text-center bg-slate-700 text-white'>
                    <span class='font-bold'>$choice</span> updated from
                    <span class='text-orange'>{$versions['current']}</span> to
                    <span class='text-green'>{$versions['latest']}</span>
                </div>
                HTML
        );
        Artisan::call('release-notes', [
            'name' => $choice,
            '--since' => $versions['current'],
        ], $this->output);

        return $this->promptChoice($outdated);
    }

    private function showTable(Collection $outdated)
    {
        $tableRows = $outdated->map(function (array $package) {
            $nameColor = $package['abandoned'] ? 'yellow' : 'brightwhite';
            $latestColor = $package['safe'] ? 'green' : 'yellow';

            return <<<HTML
            <tr>
                <td><span class="text-$nameColor">{$package['name']}</span></td>
                <td><span class='text-red'>{$package['current']}</span></td>
                <td><span class='text-$latestColor'>{$package['latest']}</span></td>
            </tr>
            HTML;
        }
        )->join('');
        render(sprintf('<div>%d outdated packages found:<table><thead><tr><th>Package</th><th>Current</th><th>Latest</th></tr></thead><tbody>%s</tbody></table></div>', $outdated->count(), $tableRows));
    }
}

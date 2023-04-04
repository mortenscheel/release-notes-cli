<?php

namespace App\Commands;

use App\OutdatedPackage;
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
    protected $signature = 'outdated {package? : Only show release notes for a specific package}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show release notes for newer version of outdated packages in current project';

    /**
     * Execute the console command.
     *
     * @return int
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
        /** @var array{locked: array<int, array{name: string, version: string, latest: string, latest-status: string, abandoned: bool}>} $data */
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $outdated = collect($data['locked'])
            ->filter(fn (array $package) => ! str_starts_with($package['version'], 'dev-'))
            ->when($this->argument('package'), fn ($result, $package) => $result->where('name', $package))
            ->mapWithKeys(fn (array $package) => [
                Arr::get($package, 'name') => new OutdatedPackage(
                    name: $package['name'],
                    current: Str::after($package['version'], 'v'),
                    latest: Str::after($package['latest'], 'v'),
                    safe: $package['latest-status'] === 'semver-safe-update',
                    abandoned: $package['abandoned'],
                ),
            ]);
        if ($outdated->isEmpty()) {
            $this->info('No outdated packages found');

            return self::SUCCESS;
        }
        $this->showTable($outdated);
        if ($outdated->count() === 1) {
            $this->showReleaseNotes($outdated->first());

            return self::SUCCESS;
        }

        return $this->promptChoice($outdated);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, OutdatedPackage>  $outdated
     */
    private function promptChoice(Collection $outdated): int
    {
        /** @var string $choice */
        $choice = $this->anticipate('Select package (or quit/table)', $outdated->keys()->push('table', 'quit')->toArray());
        if ($choice === 'quit') {
            return self::SUCCESS;
        }
        if ($choice === 'table') {
            $this->showTable($outdated);

            return $this->promptChoice($outdated);
        }
        if ($package = $outdated->get($choice)) {
            $this->showReleaseNotes($package);
        } else {
            $this->warn("No outdated package with name: $choice");

            return $this->promptChoice($outdated);
        }

        return $this->promptChoice($outdated);
    }

    private function showReleaseNotes(OutdatedPackage $package): void
    {
        render(<<<HTML
                <div class='w-full py-1 text-center bg-slate-700 text-white'>
                    <span class='font-bold'>$package->name</span> updated from
                    <span class='text-orange'>$package->current</span> to
                    <span class='text-green'>$package->latest</span>
                </div>
                HTML
        );
        Artisan::call('release-notes', [
            'name' => $package->name,
            '--since' => $package->current,
        ], $this->output);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, OutdatedPackage>  $outdated
     */
    private function showTable(Collection $outdated): void
    {
        $tableRows = $outdated->map(function (OutdatedPackage $package) {
            $nameColor = $package->abandoned ? 'yellow' : 'brightwhite';
            $latestColor = $package->safe ? 'green' : 'yellow';

            return <<<HTML
            <tr>
                <td><span class="text-$nameColor">$package->name</span></td>
                <td><span class='text-red'>$package->current</span></td>
                <td><span class='text-$latestColor'>$package->latest</span></td>
            </tr>
            HTML;
        }
        )->join('');
        render(sprintf('<div>%d outdated packages found:<table><thead><tr><th>Package</th><th>Current</th><th>Latest</th></tr></thead><tbody>%s</tbody></table></div>', $outdated->count(), $tableRows));
    }
}

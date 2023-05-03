<?php

namespace App\Commands;

use App\Release;
use App\Repository;
use App\Services\Github\Github;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Console\Output\BufferedOutput;
use function Termwind\render;
use function Termwind\renderUsing;

class ShowReleaseNotesCommand extends Command
{
    protected $signature = 'release-notes {name?      : Name of the repository or package}
                                          {--tag=     : Specific tag}
                                          {--from=    : From version}
                                          {--to=      : To version}
                                          {--since=   : From version (but not including)}
                                          {--markdown : Output markdown}';

    protected $description = 'Shows release notes for a Github repository or Composer package.';

    protected $help = <<<'HTML'
<div>
   <div>
      <span class="text-green">name</span> can be any of the following:
   </div>
   <ul class="ml-2">
      <li>Name of a Github Repository, e.g.
         <span class="text-brightblue">username/repository</span>
      </li>
      <li>Full Github URL, e.g.
         <span class="text-brightblue">https://github.com/username/repository</span>
      </li>
      <li>Name of a Composer package, e.g.
         <span class="text-brightblue">vendor/package</span>
      </li>
   </ul>
   <div class="mt-1">
      If
      <span class="text-green">--tag</span> is provided, it must match the tag name of the release exactly
   </div>
   <div class="mt-1">
      <span class="text-green">--from</span> and
      <span class="text-green">--to</span> are interpreted loosely as semver version numbers ("v" prefix is ignored), e.g.
      <ul class="ml-2">
         <li class="text-brightblue">3</li>
         <li class="text-brightblue">v1.2</li>
         <li class="text-brightblue">3.0-beta</li>
         </li>
      </ul>
   </div>
   <div class="mt-1">
      If neither
      <span class="text-green">--tag</span>,
      <span class="text-green">--from</span> or
      <span class="text-green">--to</span> is provided, only the latest release will be displayed
   </div>
   <div class="mt-1">
      Tip: Pipe the output to a pager while preserving colors and formatting, e.g.
      <div>
         <code>release-notes organization/repository --from 2.1 --to 4.0 --ansi | less -r</code>
      </div>
   </div>
</div>
HTML;

    protected function configure(): void
    {
        parent::configure();
        $this->help = $this->renderBuffered($this->help);
    }

    /**
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    public function handle(Github $github, VersionParser $versionParser): int
    {
        if (! $this->argument('name')) {
            Artisan::call(self::class, ['--help' => true], outputBuffer: $this->getOutput());
            $this->info('Other commands:');
            Artisan::call('list', outputBuffer: $this->getOutput());

            return self::SUCCESS;
        }
        /** @var string $name */
        $name = $this->argument('name');
        $repository = Repository::resolve($name);
        if (! $repository) {
            $this->error("Unable to resolve $name");

            return self::FAILURE;
        }
        if ($tag = $this->option('tag')) {
            /** @var string $tag */
            $release = $github->getReleaseForTag($repository, $tag);
            if (! $release) {
                $this->error("Unable to find a release from tag $tag in $repository->fullName");

                return self::FAILURE;
            }
            $this->renderReleases([$release]);

            return self::SUCCESS;
        }
        /** @var string|null $from */
        $from = $this->option('from');
        /** @var string|null $to */
        $to = $this->option('to');
        /** @var string|null $since */
        $since = $this->option('since');
        if (! $from && ! $to && ! $since) {
            $latest = $github->getLatestRelease($repository);
            if (! $latest) {
                $this->error("Unable to find a latest release in $repository->fullName");

                return self::FAILURE;
            }
            $this->renderReleases([$latest]);

            return self::SUCCESS;
        }
        $includeFrom = true;
        if ($from) {
            $from = $versionParser->normalize($from);
        }
        if ($since) {
            $from = $versionParser->normalize($since);
            $includeFrom = false;
        }
        if ($to) {
            $to = $versionParser->normalize($to);
        }
        $releases = array_filter($github->getAllReleases($repository), function (Release $release) use ($from, $to, $includeFrom) {
            if (! $release->normalizedVersion || ! $release->notes) {
                return false;
            }
            if ($from && version_compare($release->normalizedVersion, $from, ($includeFrom ? '<' : '<='))) {
                return false;
            }
            if ($to && version_compare($release->normalizedVersion, $to, '>')) {
                return false;
            }

            return true;
        });
        if (empty($releases)) {
            $this->warn('No release notes found');

            return self::SUCCESS;
        }
        $this->renderReleases($releases);

        return self::SUCCESS;
    }

    private function renderBuffered(string $string): string
    {
        $buffer = new BufferedOutput(decorated: true);
        renderUsing($buffer);
        render($string);
        renderUsing($this->output);

        return $buffer->fetch();
    }

    /**
     * @param  Release[]  $releases
     *
     * @throws \League\CommonMark\Exception\CommonMarkException
     */
    private function renderReleases(array $releases): void
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        foreach ($releases as $release) {
            if ($this->option('markdown')) {
                $this->output->writeln(<<<EOF
> Release: $release->tag, published {$release->publishedOn->toDateString()}
$release->notes

EOF);
            } else {
                $header = <<<HTML
                <div class='w-full flex justify-between bg-white text-black px-1'>
                  <span>$release->tag</span>
                  <a href="$release->url">Show on Github</a>
                  <span>Published {$release->publishedOn->diffForHumans()}</span>
                </div>
                HTML;
                $this->output->write($this->renderBuffered($header));
                $html = $converter->convert($release->notes ?: 'No release notes');
                $this->output->write($this->renderBuffered("<div class='mb-1 mx-1'>$html</div>"));
            }
        }
    }
}

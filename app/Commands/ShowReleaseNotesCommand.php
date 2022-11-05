<?php

namespace App\Commands;

use App\GithubRepository;
use App\RepoResolver;
use App\RepositoryNotFoundException;
use Cache;
use Carbon\Carbon;
use Composer\Semver\VersionParser;
use Github\ResultPager;
use GrahamCampbell\GitHub\GitHubManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use LaravelZero\Framework\Commands\Command;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\Console\Output\BufferedOutput;
use function Termwind\render;
use function Termwind\renderUsing;

class ShowReleaseNotesCommand extends Command
{
    protected $signature = 'release-notes {name?   : Name of the repository or package}
                                          {--tag=  : Specific tag}
                                          {--from= : From version}
                                          {--to=   : To version}';

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
      <span class="text-green">--to</span> is provided, only the
      <span class="italic">latest</span> release will be displayed
   </div>
   <div class="mt-1">
      Tip: Pipe the output to a pager while preserving colors and formatting, e.g.
      <div>
         <code>release-notes organization/repository --from 2.1 --to 4.0 | less -r</code>
      </div>
   </div>
</div>
HTML;

    protected function configure()
    {
        parent::configure();
        $buffer = new BufferedOutput(decorated: true);
        renderUsing($buffer);
        render($this->help);
        renderUsing($this->output);
        $this->help = $buffer->fetch();
    }

    public function handle(GitHubManager $github, RepoResolver $resolver, VersionParser $versionParser): int
    {
        if (! $this->argument('name')) {
            Artisan::call(self::class, ['--help' => true], outputBuffer: $this->getOutput());
            $this->info('Other commands:');
            Artisan::call('list', outputBuffer: $this->getOutput());

            return self::SUCCESS;
        }
        try {
            $api = $github->repository()->releases();
            $repo = $resolver->find($this->argument('name'));
            $tag = $this->option('tag');
            if ($tag) {
                try {
                    $this->renderReleases([$api->tag($repo->username, $repo->repository, $tag)]);

                    return self::SUCCESS;
                } catch (\Throwable) {
                    $this->error("No release found for tag: $tag");

                    return self::FAILURE;
                }
            }
            $from = $this->option('from');
            $to = $this->option('to');
            if (! $from && ! $to) {
                // Default to just show the latest release
                try {
                    $this->renderReleases([$api->latest($repo->username, $repo->repository)]);

                    return self::SUCCESS;
                } catch (\Throwable) {
                    $this->error('No releases found');

                    return self::FAILURE;
                }
            }
            if ($from) {
                $from = $versionParser->normalize($from);
            }
            if ($to) {
                $to = $versionParser->normalize($to);
            }
            $allReleases = Cache::remember("all-releases-$repo->fullName", now()->addHour(), fn () => $this->fetchAllReleases($repo, $github));
            $releases = collect($allReleases)
                ->mapWithKeys(fn ($release) => [$versionParser->normalize(Arr::get($release, 'tag_name') ?: '') => $release])
                ->sortKeys()
                ->filter(function ($release, $version) use ($from, $to) {
                    if (! $version) {
                        return false;
                    }
                    if ($from && version_compare($version, $from, '<')) {
                        return false;
                    }
                    if ($to && version_compare($version, $to, '>')) {
                        return false;
                    }

                    return true;
                });
            if ($releases->isEmpty()) {
                $this->error('No releases found');
            }
            $this->renderReleases($releases->toArray());

            return self::SUCCESS;
        } catch (RepositoryNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function fetchAllReleases(GithubRepository $repo, GitHubManager $github): array
    {
        try {
            return (new ResultPager($github->connection()))->fetchAll($github->repository()->releases(), 'all', [
                $repo->username,
                $repo->repository,
            ]);
        } catch (\Throwable) {
            return [];
        }
    }

    private function renderReleases(array $releases)
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        foreach ($releases as $release) {
            $created = Carbon::parse($release['published_at'])->diffForHumans();
            $link = "<a href='{$release['url']}'>Show on Github</a>";
            $tag = $release['tag_name'];
            $header = <<<HTML
                <div class='w-full flex justify-between bg-white text-black px-1'>
                  <span class="font-bold">$tag</span>
                  <span>$link</span>
                  <span class="italic">$created</span>
                </div>
                HTML;

            render($header);
            $markdown = $release['body'] ?: 'No release notes';
            $html = $converter->convert($markdown);
            render("<div class='mb-1 mx-1'>$html</div>");
        }
    }
}

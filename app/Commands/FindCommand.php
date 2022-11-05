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
use LaravelZero\Framework\Commands\Command;
use League\CommonMark\CommonMarkConverter;
use function Termwind\render;

class FindCommand extends Command
{
    protected $signature = 'find {repository : Repository or package name}
                                 {--tag=     : Specific tag}
                                 {--from=    : From tag}
                                 {--to=      : To tag}';

    protected $description = 'Command description';

    public function handle(GitHubManager $github, RepoResolver $resolver): int
    {
        try {
            $api = $github->repository()->releases();
            $versionParser = new VersionParser();
            $repo = $resolver->find($this->argument('repository'));
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
            $from = $versionParser->normalize($from);
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
                    if (version_compare($version, $from, '<')) {
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
<span class="font-bold">$tag</span><span>$link</span><span class="italic">$created</span>
</div>
HTML;

            render($header);
            $markdown = $release['body'] ?: 'No release notes';
            $html = $converter->convert($markdown);
            render("<div class='mb-1 mx-1'>$html</div>");
        }
    }

    private function colorTest()
    {
        $colors = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
        $weights = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
        foreach ($colors as $color) {
            foreach ($weights as $weight) {
                $class = "bg-$color-$weight";
                $text = $weight < 500 ? 'text-black' : 'text-white';
                render("<div class='$class $text w-full text-center'>$class</div>");
            }
        }
    }
}

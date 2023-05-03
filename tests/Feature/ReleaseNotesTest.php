<?php

/** @noinspection NullPointerExceptionInspection */

use App\Repository;
use App\Services\Github\Github;
use App\Services\Github\IndexReleasesRequest;
use App\Services\Github\ShowLatestReleaseRequest;
use App\Services\Github\ShowReleaseForTagRequest;
use App\Services\Github\ShowRepoRequest;
use App\Services\Packagist\ShowPackageRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Saloon\Helpers\MockConfig;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function () {
    //MockConfig::throwOnMissingFixtures();
});

it('shows extended help section when run without repo name', function () {
    $this->artisan('release-notes')->expectsOutput('Help:')->assertOk();
});

it('can resolve Github repositories from Packagist packages', function () {
    Saloon::fake([
        ShowRepoRequest::class => function (PendingRequest $request) {
            if (Str::endsWith($request->getUrl(), 'getsentry/sentry-laravel')) {
                return MockResponse::fixture('github/repo/show/sentry-correct');
            }

            return MockResponse::fixture('github/repo/show/sentry-wrong');
        },
        ShowPackageRequest::class => MockResponse::fixture('packagist/packages/sentry'),
    ]);
    $repo = Repository::resolve('sentry/sentry-laravel');
    $this->assertEquals('getsentry/sentry-laravel', $repo->fullName ?? '');
});

it('shows the latest release if no other arguments are used', function () {
    Saloon::fake([
        ShowRepoRequest::class => MockResponse::fixture('github/repo/show/laravel-framework'),
        ShowLatestReleaseRequest::class => MockResponse::fixture('github/releases/latest/laravel-framework'),
    ]);
    $latest = app(Github::class)->getLatestRelease(Repository::resolve('laravel/framework'));
    Artisan::call('release-notes laravel/framework');
    $this->assertStringContainsString($latest->tag, Artisan::output());
});

it('can show the release for a specific tag', function () {
    Saloon::fake([
        ShowRepoRequest::class => MockResponse::fixture('github/repo/show/laravel-framework'),
        ShowReleaseForTagRequest::class => MockResponse::fixture('github/releases/tag/laravel-framework/v5.5.25'),
    ]);
    $release = app(Github::class)->getReleaseForTag(Repository::resolve('laravel/framework'), 'v5.5.25');
    Artisan::call('release-notes laravel/framework --tag=v5.5.25');
    $this->assertStringContainsString($release->tag, Artisan::output());
});

it('can show all releases', function () {
    Saloon::fake([
        ShowRepoRequest::class => MockResponse::fixture('github/repo/show/laravel-framework'),
        IndexReleasesRequest::class => function (PendingRequest $request) {
            $page = $request->query()->get('page');

            return MockResponse::fixture('github/releases/all/saloon/'.$page);
        },
    ]);
    $releases = app(Github::class)->getAllReleases(Repository::resolve('sammyjo20/saloon-laravel'));
    Artisan::call('release-notes sammyjo20/saloon-laravel --from=0.0');
    $output = Artisan::output();
    foreach ($releases as $release) {
        $this->assertStringContainsString($release->tag, $output);
    }
});

it('shows error message if repository does not exist', function () {
    Saloon::fake([
        ShowRepoRequest::class => MockResponse::fixture('github/repo/show/non-existing'),
    ]);
    $name = 'gsasfghafuigdfg/adsjhjhalfgljfhga';
    $exitCode = Artisan::call("release-notes $name");
    $this->assertEquals(1, $exitCode);
    $this->assertStringContainsString("Unable to resolve $name", Artisan::output());
});

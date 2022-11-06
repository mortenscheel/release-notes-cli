<?php

/** @noinspection NullPointerExceptionInspection */

use App\Repository;
use App\Services\Github;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('shows extended help section when run without repo name', function () {
    $this->artisan('release-notes')->expectsOutput('Help:')->assertOk();
});

it('can resolve Github repositories from Packagist packages', function () {
    $repo = Repository::resolve('sentry/sentry-laravel');
    $this->assertEquals('getsentry/sentry-laravel', $repo->fullName ?? '');
});

it('shows the latest release if no other arguments are used', function () {
    $latest = app(Github::class)->getLatestRelease(Repository::resolve('laravel/framework'));
    Artisan::call('release-notes laravel/framework');
    $this->assertStringContainsString($latest->tag, Artisan::output());
});

it('can show the release for a specific tag', function () {
    $release = app(Github::class)->getReleaseForTag(Repository::resolve('laravel/framework'), 'v5.5.25');
    Artisan::call('release-notes laravel/framework --tag=v5.5.25');
    $this->assertStringContainsString($release->tag, Artisan::output());
});

it('can show all releases', function () {
    $releases = app(Github::class)->getAllReleases(Repository::resolve('laravel/framework'));
    Artisan::call('release-notes laravel/framework --from=0.0');
    $output = Artisan::output();
    foreach ($releases as $release) {
        $this->assertStringContainsString($release->tag, $output);
    }
});

it('shows error message if repository does not exist', function () {
    $name = Str::random().'/'.Str::random();
    $exitCode = Artisan::call("release-notes $name");
    $this->assertEquals(1, $exitCode);
    $this->assertStringContainsString("Unable to resolve $name", Artisan::output());
});

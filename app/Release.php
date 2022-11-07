<?php

namespace App;

use Carbon\Carbon;
use Composer\Semver\VersionParser;
use Illuminate\Support\Arr;

class Release
{
    public ?string $normalizedVersion;

    public function __construct(
        public string $tag,
        public string $url,
        public Carbon $publishedOn,
        public string $notes
    ) {
        try {
            $this->normalizedVersion = (new VersionParser)->normalize($this->tag);
        } catch (\Throwable) {
            $this->normalizedVersion = null;
        }
    }

    public static function fromApi(mixed $data): self
    {
        return new self(
            Arr::get($data, 'tag_name'),
            Arr::get($data, 'html_url'),
            Carbon::parse(Arr::get($data, 'published_at')),
            Arr::get($data, 'body') ?: 'No release notes'
        );
    }
}

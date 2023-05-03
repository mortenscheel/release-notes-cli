<?php

namespace App;

use Carbon\Carbon;
use Composer\Semver\VersionParser;

class Release
{
    public ?string $normalizedVersion;

    public string $notes;

    public function __construct(
        public string $tag,
        public string $url,
        public Carbon $publishedOn,
        ?string $notes
    ) {
        try {
            $this->normalizedVersion = (new VersionParser)->normalize($this->tag);
        } catch (\Throwable) {
            $this->normalizedVersion = null;
        }
        $this->notes = $notes ?: 'No release notes';
    }

    /**
     * @param  array{tag_name: string, html_url: string, published_at: string, body: string}  $data
     * @return \App\Release
     */
    public static function fromApi(mixed $data): Release
    {
        return new self(
            $data['tag_name'],
            $data['html_url'],
            Carbon::parse($data['published_at']),
            $data['body']
        );
    }
}

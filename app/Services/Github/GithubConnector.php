<?php

namespace App\Services\Github;

use Saloon\Contracts\HasPagination;
use Saloon\Contracts\Paginator;
use Saloon\Contracts\Request;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class GithubConnector extends Connector implements HasPagination
{
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return 'https://api.github.com';
    }

    protected function defaultHeaders(): array
    {
        if ($token = config('github.token')) {
            /** @var string $token */
            $authHeaders = [
                'Authorization' => "Bearer $token",
            ];
        } else {
            $authHeaders = [];
        }

        return array_merge([
            'Accept' => 'application/vnd.github.v3+json',
        ], $authHeaders);
    }

    protected function defaultQuery(): array
    {
        return [
            'per_page' => 50,
        ];
    }

    public function paginate(Request $request, ...$additionalArguments): Paginator
    {
        return new GithubPaginator($this, $request, 50);
    }
}

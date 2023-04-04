<?php

namespace App\Services\Github;

use RuntimeException;
use Saloon\Contracts\Request;
use Saloon\Http\Connector;
use Saloon\Http\Paginators\PagedPaginator;
use Saloon\Http\Response;

class GithubPaginator extends PagedPaginator
{
    protected string $limitKeyName = 'per_page';

    public function __construct(Connector $connector, Request $originalRequest, int $perPage, int $page = 1)
    {
        parent::__construct($connector, $originalRequest, $perPage, $page);
        $this->limit = $perPage;
        $this->currentPage = $page;
    }

    public function totalPages(): int
    {
        if ($this->currentResponse instanceof Response) {
            /** @var string $header */
            $header = $this->currentResponse->header('Link');
            if (preg_match('/&page=(\d+)>; rel="last"/', $header, $match)) {
                return (int) $match[1];
            }
        }

        throw new RuntimeException('Unable to determine total pages');
    }

    /**
     * Check if the paginator has finished
     *
     * @throws \Saloon\Exceptions\PaginatorException
     */
    protected function isFinished(): bool
    {
        if ($this->isAsync()) {
            return $this->getCurrentPage() > $this->totalPages();
        }

        if ($this->currentResponse instanceof Response) {
            /** @var string $header */
            $header = $this->currentResponse->header('Link');
            if (preg_match('/&page=(\d+)>; rel="next"/', $header, $match)) {
                return false;
            }
        }

        return true;
    }
}

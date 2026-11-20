<?php

declare(strict_types=1);

namespace App\Models;

class Paginate
{
    public function __construct(
        public int $page = 1,
        public int $pageSize = 10,
        public int $total = 0,
        public int $totalPages = 1
    ) {}

    public static function create(int $page, int $pageSize, int $total): self
    {
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        return new self(
            page: $page,
            pageSize: $pageSize,
            total: $total,
            totalPages: max(1, $totalPages)
        );
    }

    public static function response(array $data, int $total, int $page, int $pageSize): array
    {
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        return [
            'data'     => $data,
            'paginate' => (new self($page, $pageSize, $total, max(1, $totalPages)))->toArray(),
        ];
    }

    public function toArray(): array
    {
        return [
            'total'       => $this->total,
            'total_pages' => $this->totalPages,
            'page'        => $this->page,
            'page_size'   => $this->pageSize,
            'per_page'    => $this->pageSize,
        ];
    }
}

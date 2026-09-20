<?php

namespace App\Services;

use Closure;
use Generator;
use Illuminate\Database\Query\Builder;

/**
 * A drill-down report: a query that can be paged for the API or streamed row by row for a CSV
 * export, the same rows either way, plus totals over everything the query matches.
 */
final class FinanceListing
{
    /**
     * @param  Closure(object): array<string, mixed>  $map  Turns a database row into a report row.
     * @param  Closure(): array<string, mixed>  $summary  Totals over the whole result, not just one page.
     * @param  list<string>  $columns  CSV header, in the order of the mapped row.
     */
    public function __construct(
        private Builder $query,
        private Closure $map,
        private Closure $summary,
        public array $columns,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(int $page, int $perPage): array
    {
        $paginator = $this->query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())->map($this->map)->values()->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return ($this->summary)();
    }

    /**
     * Every row, for reports that are already limited by their own query.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return iterator_to_array($this->rows(), false);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(): Generator
    {
        foreach ($this->query->cursor() as $row) {
            yield ($this->map)($row);
        }
    }
}

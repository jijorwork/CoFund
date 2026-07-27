<?php

namespace App\Domain\Reports\ValueObjects;

final readonly class ReportDefinition
{
    /**
     * @param  list<string>  $filters
     * @param  list<string>  $sorting
     * @param  list<string>  $grouping
     * @param  list<string>  $columns
     * @param  list<string>  $exportFormats
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $permission,
        public array $filters,
        public array $sorting,
        public array $grouping,
        public array $columns,
        public array $exportFormats = ['csv'],
        public int $maximumSynchronousDays = 366,
        public int $maximumSynchronousRows = 10000,
    ) {}
}

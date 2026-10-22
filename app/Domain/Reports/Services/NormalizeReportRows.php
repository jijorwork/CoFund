<?php

namespace App\Domain\Reports\Services;

class NormalizeReportRows
{
    /** @return list<array<string, mixed>> */
    public function rows(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        $rows = [];
        foreach ($data as $section => $sectionRows) {
            if (! is_array($sectionRows)) {
                continue;
            }
            foreach ($sectionRows as $row) {
                if (is_array($row)) {
                    $rows[] = ['section' => $section, ...$row];
                }
            }
        }

        return $rows;
    }
}

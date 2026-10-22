<x-filament-panels::page>
    {{ $this->content }}

    @if ($this->canDisplayPreview())
        <x-filament::section>
            <x-slot name="heading">Report preview</x-slot>
            <x-slot name="description">{{ $this->previewPeriod }} · Showing up to 100 rows</x-slot>

            @if ($this->previewTruncated)
                <p class="mb-4 text-sm text-amber-700 dark:text-amber-300">The preview is limited to 100 rows. Export CSV for the full result.</p>
            @endif

            @if ($this->previewRows === [])
                <p class="text-sm text-gray-600 dark:text-gray-400">No rows matched this report.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                @foreach ($this->previewColumns as $column)
                                    <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ str($column)->replace('_', ' ')->headline() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->previewRows as $row)
                                <tr>
                                    @foreach ($this->previewColumns as $column)
                                        <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row[$column] }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($this->previewSummary !== null)
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-gray-900 dark:text-gray-100">Summary</summary>
                    <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $this->previewSummary }}</pre>
                </details>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>

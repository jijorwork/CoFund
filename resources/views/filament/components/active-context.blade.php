@php
    $tenantContext = app(\App\Domain\Organisations\Tenancy\TenantContext::class);
    $role = $tenantContext->hasRole() ? $tenantContext->role() : null;
    $systemRole = $role ? \App\Domain\Identity\Enums\SystemRole::tryFrom($role->name) : null;
@endphp

@if ($tenantContext->hasOrganisation() && $role)
    <div class="flex min-w-0 items-center px-2 sm:px-4">
        <x-filament::button
            :href="\App\Filament\Pages\SwitchContext::getUrl()"
            color="gray"
            icon="heroicon-o-arrows-right-left"
            size="sm"
            tag="a"
            wire:navigate
        >
            <span class="sr-only sm:not-sr-only sm:inline">Active context:</span>
            <span class="max-w-32 truncate sm:max-w-48">
                {{ $tenantContext->organisation()->name }}
            </span>
            <span class="hidden text-gray-500 md:inline dark:text-gray-400">
                · {{ $tenantContext->branch()?->name ?? 'Organisation-wide' }}
                · {{ $systemRole?->label() ?? \Illuminate\Support\Str::headline($role->name) }}
            </span>
        </x-filament::button>
    </div>
@endif

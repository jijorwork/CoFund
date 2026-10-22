<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->contexts as $context)
            <x-filament::section wire:key="context-{{ $context->public_id }}">
                <x-slot name="heading">
                    {{ $context->organisation->name }}
                </x-slot>

                <x-slot name="description">
                    {{ \App\Domain\Identity\Enums\SystemRole::from($context->role->name)->label() }}
                    @if ($context->branch)
                        · {{ $context->branch->name }}
                    @else
                        · Organisation-wide
                    @endif
                </x-slot>

                <x-filament::button
                    wire:click="switchContext('{{ $context->public_id }}')"
                    wire:loading.attr="disabled"
                    wire:target="switchContext('{{ $context->public_id }}')"
                >
                    Use this context
                </x-filament::button>
            </x-filament::section>
        @empty
            <x-filament::section>
                <x-slot name="heading">No available context</x-slot>
                Your organisation or role assignment is no longer active.
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>

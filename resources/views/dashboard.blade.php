<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-3xl flex-1 items-center py-10 sm:py-16">
        <div class="w-full rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-10 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:badge color="zinc">CoFund</flux:badge>
            <flux:heading size="xl" class="mt-5">Welcome, {{ auth()->user()->name }}</flux:heading>
            <flux:text class="mt-3 max-w-xl">
                Your account does not currently have access to an organisation or platform workspace.
                Ask your organisation administrator to assign access, then refresh this page.
            </flux:text>
        </div>
    </div>
</x-layouts::app>

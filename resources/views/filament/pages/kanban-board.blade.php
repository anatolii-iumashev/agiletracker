<div>
    <x-filament-panels::page>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            @foreach($columns as $status => $label)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $label }}</h3>
                    <p class="text-xs text-gray-500">Kanban board coming soon.</p>
                </div>
            @endforeach
        </div>
    </x-filament-panels::page>
</div>

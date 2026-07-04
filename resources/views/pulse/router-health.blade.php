<x-pulse::card :cols="$cols" :rows="$rows" :class="$class" wire:poll.5s="">
    <x-pulse::card-header name="MikroTik Router Health">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
            </svg>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($cpu)
            <div class="grid grid-cols-2 gap-4 p-4">

                {{-- CPU --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">CPU Load</div>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $cpu->avg ?? 0 }}%</span>
                        <span class="text-xs text-gray-400 mb-1">avg</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-0.5 ml-auto">max {{ $cpu->max ?? 0 }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-1.5 rounded-full {{ ($cpu->avg ?? 0) > 80 ? 'bg-red-500' : (($cpu->avg ?? 0) > 60 ? 'bg-yellow-500' : 'bg-green-500') }}"
                             style="width: {{ min(100, $cpu->avg ?? 0) }}%"></div>
                    </div>
                </div>

                {{-- Memory --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Memory Used</div>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $memory->avg ?? 0 }}%</span>
                        <span class="text-xs text-gray-400 mb-1">avg</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-0.5 ml-auto">max {{ $memory->max ?? 0 }}%</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-1.5 rounded-full {{ ($memory->avg ?? 0) > 85 ? 'bg-red-500' : (($memory->avg ?? 0) > 70 ? 'bg-yellow-500' : 'bg-blue-500') }}"
                             style="width: {{ min(100, $memory->avg ?? 0) }}%"></div>
                    </div>
                </div>

                {{-- API Latency --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">API Latency</div>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $latency->avg ?? 0 }}</span>
                        <span class="text-xs text-gray-400 mb-1">ms avg</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 mb-0.5 ml-auto">max {{ $latency->max ?? 0 }}ms</span>
                    </div>
                </div>

                {{-- Uptime Ratio --}}
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Uptime</div>
                    @php
                        $sum   = $beats->sum ?? 0;
                        $count = $beats->count ?? 1;
                        $pct   = $count > 0 ? round($sum / $count * 100, 1) : 0;
                    @endphp
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold {{ $pct >= 99 ? 'text-green-600 dark:text-green-400' : ($pct >= 95 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                            {{ $pct }}%
                        </span>
                        <span class="text-xs text-gray-400 mb-1">{{ $sum }}/{{ $count }} beats</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="h-1.5 rounded-full {{ $pct >= 99 ? 'bg-green-500' : ($pct >= 95 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ $pct }}%"></div>
                    </div>
                </div>

            </div>
        @else
            <x-pulse::no-results />
        @endif
    </x-pulse::scroll>
</x-pulse::card>

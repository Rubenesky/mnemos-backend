<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Tarjetas de estadísticas --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total assets</p>
                    <p class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-1">{{ $totalAssets }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Categorías</p>
                    <p class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-1">{{ $totalCategories }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tu rol</p>
                    <p class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-1 capitalize">{{ auth()->user()->role }}</p>
                </div>
            </div>

            {{-- Gráficos --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Assets por día --}}
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-4">Assets subidos (últimos 7 días)</h3>
                    <canvas id="assetsByDayChart"></canvas>
                </div>

                {{-- Assets por tipo --}}
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-4">Assets por tipo</h3>
                    <canvas id="assetsByTypeChart"></canvas>
                </div>

            </div>

            {{-- Actividad reciente --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-4">Actividad reciente</h3>
                @if($recentActivity->isEmpty())
                <p class="text-gray-500 dark:text-gray-400 text-sm">No hay actividad todavía.</p>
                @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($recentActivity as $log)
                    <li class="py-3 flex justify-between items-center text-sm">
                        <div>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $log->user->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400 ml-2">
                                @if($log->action === 'upload') subió un archivo
                                @elseif($log->action === 'edit') editó un asset
                                @elseif($log->action === 'delete') eliminó un archivo
                                @else {{ $log->action }}
                                @endif
                            </span>
                            @if(isset($log->metadata['filename']))
                            <span class="text-gray-400 dark:text-gray-500 ml-1">
                                — {{ $log->metadata['filename'] }}
                            </span>
                            @endif
                        </div>
                        <span class="text-gray-400 dark:text-gray-500">
                            {{ $log->created_at->diffForHumans() }}
                        </span>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

        </div>
    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Assets por día
        const dayData = @json($assetsByDay);
        new Chart(document.getElementById('assetsByDayChart'), {
            type: 'bar',
            data: {
                labels: dayData.map(d => d.date),
                datasets: [{
                    label: 'Assets subidos',
                    data: dayData.map(d => d.total),
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        // Assets por tipo
        const typeData = @json($assetsByType);
        new Chart(document.getElementById('assetsByTypeChart'), {
            type: 'doughnut',
            data: {
                labels: typeData.map(d => d.label),
                datasets: [{
                    data: typeData.map(d => d.total),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                    ]
                }]
            },
            options: { responsive: true }
        });
    </script>
</x-app-layout>
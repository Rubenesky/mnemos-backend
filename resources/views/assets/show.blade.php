<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $asset->metadata?->title ?? $asset->original_name }}
            </h2>
            <div class="flex gap-3">
                @if(auth()->user()->isAdmin() || auth()->user()->isEditor())
                <a href="{{ route('assets.edit', $asset) }}"
                   class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">
                    Editar
                </a>
                @endif
                @if(auth()->user()->isAdmin())
                <form action="{{ route('assets.destroy', $asset) }}" method="POST"
                      onsubmit="return confirm('¿Seguro que quieres eliminar este asset?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        Eliminar
                    </button>
                </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            @if(session('warning'))
            <div class="mb-4 p-4 bg-yellow-100 text-yellow-800 rounded-lg">
                {{ session('warning') }}
            </div>
            @endif

            {{-- Previsualización --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                @if(str_starts_with($asset->mime_type, 'image/'))
                <img src="{{ $asset->cloudinary_url ?? Storage::url($asset->path) }}"
                    alt="{{ $asset->original_name }}"
                    class="max-h-96 mx-auto rounded-lg">
                @else
                <div class="flex items-center justify-center h-40 bg-gray-100 dark:bg-gray-700 rounded-lg">
                    <span class="text-6xl">📄</span>
                </div>
                @endif
            </div>

            {{-- Información --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">
                    Información
                </h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Nombre original</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->original_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->mime_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Tamaño</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ number_format($asset->size / 1024, 1) }} KB</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Subido por</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Estado</dt>
                        <dd>
                            @if($asset->status === 'pending')
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">
                                Pendiente de IA
                            </span>
                            @elseif($asset->status === 'processed')
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                Procesado
                            </span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Fecha</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->created_at->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Metadatos --}}
            @if($asset->metadata)
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">
                        Metadatos
                    </h3>
                    @if($asset->metadata->ai_generated)
                    <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full">
                        ✨ Generado por IA
                    </span>
                    @endif
                </div>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Título</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->metadata->title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Descripción</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $asset->metadata->description ?? '—' }}</dd>
                    </div>
                    @if($asset->metadata->tags)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400 mb-1">Etiquetas</dt>
                        <dd class="flex flex-wrap gap-2">
                            @foreach($asset->metadata->tags as $tag)
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                {{ $tag }}
                            </span>
                            @endforeach
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

            {{-- Categorías --}}
            @if($asset->categories->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">
                <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">
                    Categorías
                </h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($asset->categories as $category)
                    <span class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 text-sm px-3 py-1 rounded-full">
                        {{ $category->name }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
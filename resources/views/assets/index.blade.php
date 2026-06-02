<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Assets
            </h2>
            <div class="flex gap-3">
                @if(auth()->user()->isAdmin())
                <a href="{{ route('export.assets') }}"
                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    ↓ Exportar Excel
                </a>
                @endif
                @if(auth()->user()->isAdmin() || auth()->user()->isEditor())
                <a href="{{ route('assets.create') }}"
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    + Subir asset
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
            <div class="p-4 bg-green-100 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            {{-- Filtros --}}
            <form method="GET" action="{{ route('assets.index') }}"
                  class="bg-white dark:bg-gray-800 shadow rounded-xl p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    {{-- Búsqueda --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Buscar
                        </label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Nombre del archivo..."
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    </div>

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tipo
                        </label>
                        <select name="type"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">Todos</option>
                            <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>Imágenes</option>
                            <option value="application/pdf" {{ request('type') === 'application/pdf' ? 'selected' : '' }}>PDFs</option>
                            <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>Vídeos</option>
                            <option value="application" {{ request('type') === 'application' ? 'selected' : '' }}>Documentos</option>
                        </select>
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Estado
                        </label>
                        <select name="status"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">Todos</option>
                            <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Procesado</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                        </select>
                    </div>

                    {{-- Categoría --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Categoría
                        </label>
                        <select name="category"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                            <option value="">Todas</option>
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                            @if($category->children->isNotEmpty())
                                @foreach($category->children as $child)
                                <option value="{{ $child->id }}" {{ request('category') == $child->id ? 'selected' : '' }}>
                                    └ {{ $child->name }}
                                </option>
                                @endforeach
                            @endif
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="flex justify-end gap-3 mt-4">
                    @if(request()->hasAny(['search', 'type', 'status', 'category']))
                    <a href="{{ route('assets.index') }}"
                       class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:underline text-sm">
                        Limpiar filtros
                    </a>
                    @endif
                    <button type="submit"
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 text-sm">
                        Buscar
                    </button>
                </div>
            </form>

            {{-- Resultados --}}
            @if($assets->isEmpty())
            <div class="text-center py-20 text-gray-500 dark:text-gray-400">
                No se encontraron assets con esos filtros.
            </div>
            @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach($assets as $asset)
                <a href="{{ route('assets.show', $asset) }}"
                   class="bg-white dark:bg-gray-800 rounded-xl shadow hover:shadow-lg transition p-4 block">

                    @if(str_starts_with($asset->mime_type, 'image/'))
                    <img src="{{ $asset->cloudinary_url ?? Storage::url($asset->path) }}"
                         alt="{{ $asset->original_name }}"
                         class="w-full h-40 object-cover rounded-lg mb-3">
                    @else
                    <div class="w-full h-40 bg-gray-100 dark:bg-gray-700 rounded-lg mb-3 flex items-center justify-center">
                        <span class="text-4xl">📄</span>
                    </div>
                    @endif

                    <p class="font-medium text-gray-800 dark:text-gray-200 truncate">
                        {{ $asset->metadata?->title ?? $asset->original_name }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $asset->user->name }} •
                        {{ number_format($asset->size / 1024, 1) }} KB
                    </p>

                    @if($asset->status === 'pending')
                    <span class="inline-block mt-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">
                        Pendiente de IA
                    </span>
                    @elseif($asset->status === 'processed')
                    <span class="inline-block mt-2 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                        Procesado
                    </span>
                    @endif
                </a>
                @endforeach
            </div>

            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                {{ $assets->total() }} resultado(s) encontrado(s)
            </div>

            <div class="mt-4">
                {{ $assets->links() }}
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
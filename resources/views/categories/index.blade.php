<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Categorías
            </h2>
            <a href="{{ route('categories.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                + Nueva categoría
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            @if($categories->isEmpty())
            <div class="text-center py-20 text-gray-500 dark:text-gray-400">
                No hay categorías todavía.
            </div>
            @else
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-6 py-3 text-left">Nombre</th>
                            <th class="px-6 py-3 text-left">Descripción</th>
                            <th class="px-6 py-3 text-left">Subcategorías</th>
                            <th class="px-6 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($categories as $category)
                        <tr class="text-gray-800 dark:text-gray-200">
                            <td class="px-6 py-4 font-medium">{{ $category->name }}</td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                {{ $category->description ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($category->children->isNotEmpty())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($category->children as $child)
                                    <span class="bg-gray-100 dark:bg-gray-700 text-xs px-2 py-1 rounded-full">
                                        {{ $child->name }}
                                    </span>
                                    @endforeach
                                </div>
                                @else
                                <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('categories.edit', $category) }}"
                                       class="text-blue-600 hover:underline text-sm">Editar</a>
                                    <form action="{{ route('categories.destroy', $category) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar esta categoría?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-sm">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
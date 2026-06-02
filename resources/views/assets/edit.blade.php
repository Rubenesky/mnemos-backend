<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar asset: {{ $asset->original_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">

                <form action="{{ route('assets.update', $asset) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    {{-- Título --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Título
                        </label>
                        <input type="text" name="title"
                               value="{{ old('title', $asset->metadata?->title) }}"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>

                    {{-- Descripción --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Descripción
                        </label>
                        <textarea name="description" rows="3"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">{{ old('description', $asset->metadata?->description) }}</textarea>
                    </div>

                    {{-- Tags --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Etiquetas <span class="text-gray-400 font-normal">(separadas por comas)</span>
                        </label>
                        <input type="text" name="tags"
                               value="{{ old('tags', $asset->metadata?->tags ? implode(',', $asset->metadata->tags) : '') }}"
                               placeholder="diseño, logo, web"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    </div>

                    {{-- Categorías --}}
                    @if($categories->isNotEmpty())
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Categorías
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($categories as $category)
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="categories[]" value="{{ $category->id }}"
                                    {{ $asset->categories->contains($category->id) ? 'checked' : '' }}>
                                {{ $category->name }}
                            </label>
                            @if($category->children->isNotEmpty())
                                @foreach($category->children as $child)
                                <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 ml-4">
                                    <input type="checkbox" name="categories[]" value="{{ $child->id }}"
                                        {{ $asset->categories->contains($child->id) ? 'checked' : '' }}>
                                    └ {{ $child->name }}
                                </label>
                                @endforeach
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Botones --}}
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('assets.show', $asset) }}"
                           class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:underline">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Guardar cambios
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
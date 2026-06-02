<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Subir asset
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow rounded-xl p-6">

                <form action="{{ route('assets.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Archivo --}}
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Archivo
                        </label>
                        <input type="file" name="file" required
                               class="w-full text-gray-700 dark:text-gray-300">
                        @error('file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                                <input type="checkbox" name="categories[]" value="{{ $category->id }}">
                                {{ $category->name }}
                            </label>
                            @if($category->children->isNotEmpty())
                                @foreach($category->children as $child)
                                <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 ml-4">
                                    <input type="checkbox" name="categories[]" value="{{ $child->id }}">
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
                        <a href="{{ route('assets.index') }}"
                           class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:underline">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                            Subir
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
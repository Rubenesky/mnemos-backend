<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles web UI CRUD operations for hierarchical asset categories.
 *
 * @package App\Http\Controllers
 */
class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::whereNull('parent_id')
                              ->with('children')
                              ->latest()
                              ->get();

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        $categories = Category::whereNull('parent_id')->get();
        return view('categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
        ]);

        Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'parent_id'   => $request->parent_id,
        ]);

        return redirect()->route('categories.index')
                         ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $categories = Category::whereNull('parent_id')
                              ->where('id', '!=', $category->id)
                              ->get();

        return view('categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
        ]);

        $category->update([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'parent_id'   => $request->parent_id,
        ]);

        return redirect()->route('categories.index')
                         ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('categories.index')
                         ->with('success', 'Category deleted successfully.');
    }
}
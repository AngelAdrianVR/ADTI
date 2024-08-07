<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Feature;
use App\Models\MeasureUnit;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        $features = Feature::latest('id')->get(['id', 'name']);
        $measure_units = MeasureUnit::latest('id')->get(['id', 'name']);

        return inertia('Category/Create', compact('features', 'measure_units'));
    }

    public function storeWithSubcategories(Request $request) {
        dd($request->all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'required|string|max:10',
        ]);

        Category::create($request->all());
    }

    public function show(Category $category)
    {
        //
    }

    public function edit(Category $category)
    {
        //
    }

    public function update(Request $request, Category $category)
    {
        //
    }

    public function destroy(Category $category)
    {
        //
    }

    //Obtiene toda la informacion de las subcategorías.
    //rutas: create de producto.
    public function fetchSubcategories(Category $category)
    {
        $category->load('subcategories');

        return response()->json(compact('category'));
    }
}

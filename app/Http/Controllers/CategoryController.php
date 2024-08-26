<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Feature;
use App\Models\MeasureUnit;
use App\Models\Subcategory;
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

    public function storeWithSubcategories(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'key' => 'required|string|max:10',
        ]);

        $category = Category::create([
            'name' => $validated['category'],
            'key' => $validated['key'],
        ]);

        // Guardar la imagen de categoria temporalmente
        if ($request->hasFile('image')) {
            $path = $request->file('image')->storeAs('temp', $request->file('image')->getClientOriginalName());
            $category->addMedia(storage_path('app/' . $path))->toMediaCollection();
        }

        $this->createSubcategories($request->subCategories, $category->id, null, 1);

        return to_route('settings.index', ['currentTab' => 1]);
    }

    private function createSubcategories($subCategories, $categoryId, $prevSubcategoryId, $level)
    {
        foreach ($subCategories as $key => $subCategoryData) {
            $current_key = $key + 1;
            $subcategory = Subcategory::create([
                'name' => $subCategoryData['name'],
                'key' => $current_key,
                'level' => $level,
                'features' => isset($subCategoryData['features']) ? $subCategoryData['features'] : null,
                'category_id' => $categoryId,
                'prev_subcategory_id' => $prevSubcategoryId,
            ]);

            // Guardar la imagen de subcategoria temporalmente
            if (isset($subCategoryData['image'])) {
                $path = $subCategoryData['image']->storeAs('temp', $subCategoryData['image']->getClientOriginalName());
                $subcategory->addMedia(storage_path('app/' . $path))->toMediaCollection();
            }

            if (isset($subCategoryData['subCategories'])) {
                $this->createSubcategories($subCategoryData['subCategories'], $categoryId, $subcategory->id, $level + 1);
            }
        }
    }

    public function updateWithSubcategories(Request $request, Category $category)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'key' => 'required|string|max:10',
        ]);

        // Actualizar la categoría
        $category->update([
            'name' => $validated['category'],
            'key' => $validated['key'],
        ]);

        // Actualizar la imagen de la categoría si se proporciona una nueva
        if ($request->hasFile('image')) {
            // Eliminar la imagen actual si existe
            if ($category->getFirstMedia()) {
                $category->clearMediaCollection();
            }
            // Guardar la nueva imagen temporalmente
            $path = $request->file('image')->storeAs('temp', $request->file('image')->getClientOriginalName());
            $category->addMedia(storage_path('app/' . $path))->toMediaCollection();
        } else if ($category->getFirstMedia() && boolval($request->imageDeleted)) {
            $category->clearMediaCollection();
        }

        // Manejar las subcategorías
        $existingSubcategories = $category->subcategories->keyBy('id');
        $this->updateOrCreateSubcategories($request->subCategories, $category->id, null, 1, $existingSubcategories);

        // Eliminar las subcategorías que no están en la nueva lista
        foreach ($existingSubcategories as $subcategory) {
            $subcategory->delete();
        }

        return to_route('settings.index', ['currentTab' => 1]);
    }

    private function updateOrCreateSubcategories($subCategories, $categoryId, $prevSubcategoryId, $level, &$existingSubcategories)
    {
        foreach ($subCategories as $key => $subCategoryData) {
            $current_key = $key + 1;
            $subcategoryId = $subCategoryData['id'] ?? null;

            if ($subcategoryId && $existingSubcategories->has($subcategoryId)) {
                // Actualizar subcategoría existente
                $subcategory = $existingSubcategories->get($subcategoryId);
                $subcategory->update([
                    'name' => $subCategoryData['name'],
                    'key' => $current_key,
                    'level' => $level,
                    'features' => $subCategoryData['features'] ?? null,
                    'prev_subcategory_id' => $prevSubcategoryId,
                ]);

                // Actualizar la imagen de la subcategoría si se proporciona una nueva
                if (isset($subCategoryData['image'])) {
                    if ($subcategory->getFirstMedia()) {
                        $subcategory->clearMediaCollection();
                    }
                    $path = $subCategoryData['image']->storeAs('temp', $subCategoryData['image']->getClientOriginalName());
                    $subcategory->addMedia(storage_path('app/' . $path))->toMediaCollection();
                } else if ($subcategory->getFirstMedia() && boolval($subCategoryData['imageDeleted'])) {
                    $subcategory->clearMediaCollection();
                }

                // Remover la subcategoría del conjunto de existentes (ya no necesita ser eliminada)
                $existingSubcategories->forget($subcategoryId);
            } else {
                // Crear nueva subcategoría
                $subcategory = Subcategory::create([
                    'name' => $subCategoryData['name'],
                    'key' => $current_key,
                    'level' => $level,
                    'features' => $subCategoryData['features'] ?? null,
                    'category_id' => $categoryId,
                    'prev_subcategory_id' => $prevSubcategoryId,
                ]);

                // Guardar la imagen de la nueva subcategoría si existe
                if (isset($subCategoryData['image'])) {
                    $path = $subCategoryData['image']->storeAs('temp', $subCategoryData['image']->getClientOriginalName());
                    $subcategory->addMedia(storage_path('app/' . $path))->toMediaCollection();
                }
            }

            // Recursivamente manejar sub-subcategorías
            if (isset($subCategoryData['subCategories'])) {
                $this->updateOrCreateSubcategories($subCategoryData['subCategories'], $categoryId, $subcategory->id, $level + 1, $existingSubcategories);
            }
        }
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
        $features = Feature::latest('id')->get(['id', 'name']);
        $measure_units = MeasureUnit::latest('id')->get(['id', 'name']);
        $category->load(['media', 'subcategories.media']);

        return inertia('Category/Edit', compact('features', 'measure_units', 'category'));
    }

    public function update(Request $request, Category $category)
    {
        //
    }

    public function destroy(Category $category)
    {
        $subcategories = $category->subcategories;

        // borrar cada subcategoria y sus productos por separado para que se elimine correctamente la imagen relacionada
        foreach ($subcategories as $subcategory) {
            $products = $subcategory->products;
            $products->each(fn ($product) => $product->delete());
            $subcategory->delete();
        }

        // finalmente borrar la categoria
        $category->delete();
    }

    // API
    //Obtiene toda la informacion de las subcategorías.
    //rutas: create de producto.
    public function fetchSubcategories(Category $category)
    {
        $category->load('subcategories');

        return response()->json(compact('category'));
    }

    public function getAll()
    {
        $items = Category::with(['subcategories', 'media'])->latest('id')->get();

        return response()->json(compact('items'));
    }
}

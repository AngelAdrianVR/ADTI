<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /* tipos de comentarios

    * Importante
    ! no usar hasta que..
    ? no sé si usar este..
    TODO: No sé para qué sirve jajaja
    */

    public function index()
    {
        $products = Product::with(['subcategory:id,name,category_id,prev_subcategory_id' => ['category:id,name']])->get(['id', 'name', 'description', 'part_number', 'location', 'subcategory_id', 'bread_crumbles']);

        return inertia('Product/Index', compact('products'));
    }
    
    public function create()
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();

        return inertia('Product/Create', compact('categories', 'measure_units'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:255',
            'features' => 'nullable|array',
            'part_number' => 'required|string|max:20',
            'location' => 'nullable|string|max:100',
        ]);

        $product = Product::create($request->except(['imageCover', 'subcategory_id']) + 
        ['subcategory_id' => collect($request->subcategory_id)->last()]); //guarda el ultimo id del arreglo de subcategorías

        // Guardar la imagen de portada del producto en la colección 'imageCover'
        if ($request->hasFile('imageCover')) {
            $product->addMediaFromRequest('imageCover')->toMediaCollection('imageCover');
        }

        // Guardar los archivos descargables si existen
        if ($request->hasFile('media')) {
            $product->addAllMediaFromRequest('media')->each(fn ($file) => $file->toMediaCollection('files'));
        }

        return to_route('products.show', $product->id); //manda al show despues de crear el producto
        // return response()->json(['id' => $product->id]); //en caso de agregar boton de acciones para mostrar, crear y seguir creando
    }
    
    public function show(Product $product)
    {   
        $product->load(['media', 'subcategory:id,name,category_id,prev_subcategory_id' => ['category:id,name']]);

        return inertia('Product/Show', compact('product'));
    }
    
    public function edit(Product $product)
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();
        $product->load(['media', 'subcategory.category']);

        return inertia('Product/Edit', compact('product', 'categories', 'measure_units'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:255',
            'features' => 'nullable|array',
            'part_number' => 'required|string|max:20',
            'location' => 'nullable|string|max:100',
        ]);

        $product->update($request->except(['imageCover', 'subcategory_id']) + 
        ['subcategory_id' => collect($request->subcategory_id)->last()]); //guarda el ultimo id del arreglo de subcategorías

        // media -------------------------
        // Eliminar imagen sólo si se borró desde el input y no se agregó una nueva
        if ($request->imageCoverCleared) {
            $product->clearMediaCollection('imageCover');
        }

        // Eliminar Archivos adjuntos si se seleccionó el check para borrarlos
        if ($request->deleteMedia) {
            $product->clearMediaCollection('files');
        }

        return to_route('products.show', $product->id); //manda al show despues de crear el producto
    }

    public function updateWithMedia(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:255',
            'features' => 'nullable|array',
            'part_number' => 'required|string|max:20',
            'location' => 'nullable|string|max:100',
        ]);
        
        $product->update($request->except(['imageCover', 'subcategory_id']) + 
        ['subcategory_id' => collect($request->subcategory_id)->last()]); //guarda el ultimo id del arreglo de subcategorías
        
        // media ------------
        // Eliminar imagen antigua solo si se proporciona nueva imagen
        if ($request->hasFile('imageCover')) {
            $product->clearMediaCollection('imageCover');
        }

        // Guardar el archivo en la colección 'imageCover'
        if ($request->hasFile('imageCover')) {
            $product->addMediaFromRequest('imageCover')->toMediaCollection('imageCover');
        }

        // Guardar los archivos descargables si existen
        if ($request->hasFile('media')) {
            $product->addMediaFromRequest('media')->toMediaCollection('files');
            // $product->addAllMediaFromRequest('media')->each(fn ($file) => $file->toMediaCollection('files'));
        }
        
        return to_route('products.show', $product->id); //manda al show despues de crear el producto
    }
    
    public function destroy(Product $product)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->items_ids as $id) {
                $item = Product::find($id);
                $item?->delete();
        }
    }

    //busca productos por nombre o numero de parte
    //ultilizado en barra buscadora de show de productos
    public function searchProduct(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda en la base de datos
        $products = Product::where(function ($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                    ->orWhere('part_number', 'like', "%$query%");
            })
            ->get();

        return response()->json(['items' => $products]);
    }
}

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

        // return $products;
        return inertia('Product/Index', compact('products'));
    }
    
    public function create()
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();

        // return $categories;
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

        $product = Product::create($request->except(['imageCover', 'subcategory_id']) + ['subcategory_id' => collect($request->subcategory_id)->last()]);

        // Guardar el archivo en la colección 'imageCover'
        if ($request->hasFile('imageCover')) {
            $product->addMediaFromRequest('imageCover')->toMediaCollection('imageCover');
        }
    }
    
    public function show(Product $product)

    {   
        $product->load('media');

        // return $product;
        return inertia('Product/Show', compact('product'));
    }
    
    public function edit(Product $product)
    {
        return inertia('Product/Edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        //
    }

    // public function updateWithMedia(Request $request, Product $product)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:100|unique:products,name,' . $product->id,
    //         'code' => ['nullable', 'string', 'max:100', new \App\Rules\UniqueProductCode($product->id)],
    //         'public_price' => 'required|numeric|min:0|max:9999',
    //         'currency' => 'required|string',
    //         'cost' => 'nullable|numeric|min:0|max:9999',
    //         'description' => 'nullable|string|max:255',
    //         'current_stock' => 'nullable|numeric|min:0|max:9999',
    //         'min_stock' => 'nullable|numeric|min:0|max:9999',
    //         'max_stock' => 'nullable|numeric|min:0|max:9999',
    //         'category_id' => 'nullable',
    //         'brand_id' => 'nullable',
    //     ]);

    //     //precio actual para checar si se cambió el precio y registrarlo
    //     $current_price = $product->public_price;
    //     if ($current_price != $request->public_price) {
    //         ProductHistory::create([
    //             'description' => 'Cambio de precio de $' . $current_price . 'MXN a $ ' . $request->public_price . 'MXN.',
    //             'type' => 'Precio',
    //             'historicable_id' => $product->id,
    //             'historicable_type' => Product::class
    //         ]);
    //     }

    //     $product->update($request->except('imageCover'));

    //     // media ------------
    //     // Eliminar imágenes antiguas solo si se proporcionan nuevas imágenes
    //     if ($request->hasFile('imageCover')) {
    //         $product->clearMediaCollection('imageCover');
    //     }

    //     // Guardar el archivo en la colección 'imageCover'
    //     if ($request->hasFile('imageCover')) {
    //         $product->addMediaFromRequest('imageCover')->toMediaCollection('imageCover');
    //     }

    //     //codifica el id del producto
    //     $encoded_product_id = base64_encode($product->id);

    //     return to_route('products.show', ['product' => $encoded_product_id]);
    // }
    
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

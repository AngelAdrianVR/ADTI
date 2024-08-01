<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
        $products = Product::all(['id', 'name']);

        return inertia('Product/Index', compact('products'));
    }

    
    public function create()
    {
        $categories = Category::all();

        // return $categories;
        return inertia('Product/Create', compact('categories'));
    }

    
    public function store(Request $request)
    {
        //
    }

    
    public function show(Product $product)
    {
        //
    }

    
    public function edit(Product $product)
    {
        //
    }

    
    public function update(Request $request, Product $product)
    {
        //
    }

    
    public function destroy(Product $product)
    {
        //
    }
}

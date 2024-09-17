<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['subcategory:id,name,category_id,prev_subcategory_id' => ['category:id,name']])
            ->latest('id')
            ->get(['id', 'name', 'description', 'part_number_supplier', 'location', 'subcategory_id', 'bread_crumbles']);

        return inertia('Product/Index', compact('products'));
    }

    public function create()
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();
        $last_product = Product::latest()->first();
        $next_product_id = $last_product ? $last_product->id + 1 : 0;

        return inertia('Product/Create', compact('categories', 'measure_units', 'next_product_id'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:34',
            'features' => 'nullable|array',
            'features_keys' => 'nullable|array',
            'part_number' => 'required|string|max:17',
            'part_number_supplier' => 'nullable|string|max:20|unique:products,part_number_supplier',
            'location' => 'nullable|string|max:100',
            'currency' => 'required|string|max:255',
            'line_cost' => 'nullable|numeric|min:0|max:99999',
        ]);

        $product = Product::create($request->except(['imageCover', 'subcategory_id']) +
            ['subcategory_id' => collect($request->subcategory_id)->last()]); //guarda el ultimo id del arreglo de subcategorías

        //busca la cantidad de productos agregados a la subcategoría del producto recién agregado para agregarle su consecutivo 
        $last_consecutivo = Product::where('subcategory_id', $product->subcategory_id)->count();

        $product->consecutivo = $last_consecutivo;
        $product->save();


        // Guardar la imagen de categoria temporalmente
        if ($request->hasFile('imageCover')) {
            $path = $request->file('imageCover')->storeAs('temp', $request->file('imageCover')->getClientOriginalName());
            $product->addMedia(storage_path('app/' . $path))->toMediaCollection('imageCover');
        }

        // Guardar los archivos descargables si existen
        if ($request->hasFile('media')) {
            $product->addAllMediaFromRequest('media')->each(fn($file) => $file->toMediaCollection('files'));
        }

        return to_route('products.show', $product->id); //manda al show despues de crear el producto
        // return response()->json(['id' => $product->id]); //en caso de agregar boton de acciones para mostrar, crear y seguir creando
    }

    public function show(Product $product)
    {
        $product->load(['media', 'subcategory:id,name,category_id,prev_subcategory_id' => ['category:id,name']]);

        // return $product;
        return inertia('Product/Show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();
        $product->load(['media', 'subcategory.category']);

        // return $product;
        return inertia('Product/Edit', compact('product', 'categories', 'measure_units'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'nullable|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:34',
            'features' => 'nullable|array',
            'features_keys' => 'nullable|array',
            'part_number' => 'required|string|max:17',
            'part_number_supplier' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:100',
            'currency' => 'required|string|max:255',
            'line_cost' => 'nullable|numeric|min:0|max:99999',
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
            'name' => 'nullable|string|max:100',
            'category_id' => 'required',
            'subcategory_id' => 'required|array|min:1', //se recibe en arreglo porque se guardan todas las subcategorías
            'description' => 'nullable|string|max:34',
            'features' => 'nullable|array',
            'features_keys' => 'nullable|array',
            'part_number' => 'required|string|max:17',
            'part_number_supplier' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:100',
            'currency' => 'required|string|max:255',
            'line_cost' => 'nullable|numeric|min:0|max:99999',
        ]);

        $product->update($request->except(['imageCover', 'subcategory_id']) +
            ['subcategory_id' => collect($request->subcategory_id)->last()]); //guarda el ultimo id del arreglo de subcategorías

        // media ------------
        // Eliminar imagen antigua solo si se proporciona nueva imagen
        if ($request->hasFile('imageCover')) {
            $product->clearMediaCollection('imageCover');

            // Guardar la imagen de categoria temporalmente
            $path = $request->file('imageCover')->storeAs('temp', $request->file('imageCover')->getClientOriginalName());
            $product->addMedia(storage_path('app/' . $path))->toMediaCollection('imageCover');
        }

        // Guardar los archivos descargables si existen
        if ($request->hasFile('media')) {
            $product->addAllMediaFromRequest('media')->each(fn($file) => $file->toMediaCollection('files'));
        }

        return to_route('products.show', $product->id); //manda al show despues de crear el producto
    }

    public function destroy(Product $product)
    {
        //
    }

    public function deleteFile($file_id)
    {
        // Buscar el archivo por su ID
        $media = Media::findOrFail($file_id);

        // Eliminar el archivo
        $media->delete();
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
                ->orWhere('part_number', 'like', "%$query%")
                ->orWhere('part_number_supplier', 'like', "%$query%")
                ->orWhere('description', 'like', "%$query%");
        })
            ->get();

        return response()->json(['items' => $products]);
    }

    //Recupera los productos de la subcategoría seleccionada
    //ultilizado en LandingPage/ShowSubcategory
    public function fetchSubcategoryProducts($subcategory_id)
    {
        $products = Product::where('subcategory_id', $subcategory_id)
            ->with('media')
            ->get(['id', 'name', 'description', 'part_number', 'part_number_supplier', 'location']);

        return response()->json(compact('products'));
    }

    public function import(Request $request)
    {
        // Validar el archivo Excel
        $request->validate([
            // 'file' => 'required|mimes:xlsx,xls',
            'file' => 'required',
        ]);

        // Obtener el archivo Excel
        $file = $request->file('file');

        if (is_array($file)) {
            // Si se enviaron múltiples archivos, toma el primero
            $file = reset($file);
        }

        // Guardar el archivo en el almacenamiento temporal de Laravel
        $path = $file->store('temp');

        // Obtener la ruta completa del archivo
        $filePath = Storage::path($path);

        // Cargar el archivo Excel
        $spreadsheet = IOFactory::load($filePath);

        // Obtener la primera hoja de trabajo
        $worksheet = $spreadsheet->getActiveSheet();

        // validar informacion
        $errorsBag = $this->validateProductsFromFile($worksheet);

        // Si hay errores, devolverlos al cliente
        if ($errorsBag) {
            return response()->json(['errors' => $errorsBag], 400);
        } else {
            // Si no hay errores, proceder a guardar en la base de datos
            $this->storeProductsFromFile($worksheet);
        }
    }

    private function validateProductsFromFile($worksheet)
    {
        // Almacenar los errores de validación
        $errorsBag = [];

        $columnNames = [];
        $productNameColumnIndex = null;

        // Obtener datos y guardar en la base de datos
        foreach ($worksheet->getRowIterator() as $row) {
            if ($row->getRowIndex() < 3) {
                continue; // Saltar las primeras 2 filas
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            if ($row->getRowIndex() == 3) {
                // Obtener los nombres de columna de la fila 3 del archivo Excel
                foreach ($cellIterator as $cell) {
                    $columnNames[] = $cell->getValue();
                }

                // Buscar la posición de la columna 'Número de parte de fabricante'
                $productNameColumnIndex = array_search('Número de parte de fabricante', $columnNames);
                continue;
            }

            $data = [];
            $currentColumn = 0;
            foreach ($cellIterator as $cell) {
                $columnName = $columnNames[$currentColumn++]; // Obtener el nombre de columna
                $data[$columnName] = $cell->getValue(); // Asignar el valor al array asociativo usando el nombre de columna
            }

            // Validar los datos
            $validator = Validator::make($data, [
                $columnNames[$productNameColumnIndex] => 'nullable|string|max:255',
                $columnNames[$productNameColumnIndex + 1] => $data[$columnNames[$productNameColumnIndex + 1]] ? 'string|max:255' : '',
                $columnNames[$productNameColumnIndex + 2] => $data[$columnNames[$productNameColumnIndex + 2]] ? 'string|max:30' : '',
            ]);

            // Si la validación falla, almacenar los errores
            if ($validator->fails()) {
                $errorsBag[] = [
                    'row' => $row->getRowIndex(),
                    'errors' => $validator->errors()->all(),
                ];
            }
        }

        return $errorsBag;
    }

    private function storeProductsFromFile($worksheet)
    {
        $columnNames = [];
        $productNameColumnIndex = null;

        foreach ($worksheet->getRowIterator() as $row) {
            if ($row->getRowIndex() < 3) {
                continue; // Saltar las primeras 2 filas
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            if ($row->getRowIndex() == 3) {
                // Obtener los nombres de columna de la cuarta fila del archivo Excel
                foreach ($cellIterator as $cell) {
                    $columnNames[] = $cell->getValue();
                }

                // Buscar la posición de la columna 'Número de parte de fabricante'
                $productNameColumnIndex = array_search('Número de parte de fabricante', $columnNames);
                continue;
            }

            $data = [];
            foreach ($cellIterator as $cell) {
                $data[] = $cell->getValue(); // Asignar el valor al array asociativo usando el nombre de columna
            }

            // Obtener la categoría principal
            $categoryName = $data[0]; // Primer valor de la primer columna
            $category = Category::where('name', $categoryName)->firstOrFail();
            $categoryKey = $category->key;

            // Obtener el path de la subcategoría desde el encabezado antes de 'Nombre del producto'
            $subCategoryHeader = $columnNames[$productNameColumnIndex - 1];
            $path = str_replace('.', '', explode(' ', $subCategoryHeader)[1]);

            // Obtener el último ID de Product y generar el partNumber
            $lastProduct = Product::latest('id')->first();
            $consecutive = $lastProduct ? $lastProduct->id + 1 : 1;
            $partNumber = $categoryKey . $path . '-' . $consecutive;

            // Obtener las características después de 'Ubicación en almacén'
            $features = [];
            for ($i = $productNameColumnIndex + 3; $i < count($columnNames); $i += 2) {
                $features[] = [
                    'name' => $columnNames[$i],
                    'value' => $data[$i],
                    'measure_unit' => $data[$i + 1]
                ];
            }

            // Obtener el ID de la subcategoría final antes de 'Nombre del producto'
            $subCategoryName = $data[$productNameColumnIndex - 1];
            $subcategory = Subcategory::where('name', $subCategoryName)->firstOrFail();

            // Primero, recorre hasta llegar al nivel más alto (primer nivel de subcategoría)
            $currentSubcategory = $subcategory;
            $subcategoryStack = [];
            while ($currentSubcategory !== null) {
                array_unshift($subcategoryStack, $currentSubcategory->name);
                $currentSubcategory = Subcategory::find($currentSubcategory->prev_subcategory_id);
            }

            // Guardar el producto en la base de datos
            Product::create([
                'part_number_supplier' => $data[$productNameColumnIndex],
                'description' => $data[$productNameColumnIndex + 1], // Asumimos que la columna 'Descripción' está inmediatamente después de 'Número de parte de fabricantes'
                'location' => $data[$productNameColumnIndex + 2], // Asumimos que la columna 'Ubicación en almacén' está inmediatamente después de 'Descripción'
                'part_number' => $partNumber,
                'bread_crumbles' => $subcategoryStack,
                'features' => $features,
                'subcategory_id' => $subcategory->id,
            ]);
        }
    }

    public function printBarcodes()
    {
        $products = Product::whereIn('id', request('items_ids'))->get(['id', 'name', 'part_number']);

        return inertia('Product/BarcodeTemplate', compact('products'));
    }

    public function getConsecutivo($subcategory_id)
    {
        $last_consecutivo = Product::where('subcategory_id', $subcategory_id)->count();

        return response()->json(['next_consecutivo' => $last_consecutivo ? $last_consecutivo + 1 : 1]);
    }
}

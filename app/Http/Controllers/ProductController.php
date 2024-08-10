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
        $products = Product::with(['subcategory:id,name,category_id,prev_subcategory_id' => ['category:id,name']])->get(['id', 'name', 'description', 'part_number_supplier', 'location', 'subcategory_id', 'bread_crumbles']);

        return inertia('Product/Index', compact('products'));
    }

    public function create()
    {
        $categories = Category::all();
        $measure_units = MeasureUnit::all();
        $last_product = Product::latest()->first();
        $next_product_id = $last_product->id + 1;

        return inertia('Product/Create', compact('categories', 'measure_units', 'next_product_id'));
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
            'part_number_supplier' => 'required|string|max:20|unique:products,part_number_supplier',
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
            $product->addAllMediaFromRequest('media')->each(fn($file) => $file->toMediaCollection('files'));
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
            'part_number_supplier' => 'required|string|max:20|unique:products,part_number_supplier,' . $product->id,
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
            'part_number_supplier' => 'required|string|max:20|unique:products,part_number_supplier,' . $product->id,
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
                ->orWhere('part_number', 'like', "%$query%")
                ->orWhere('part_number_supplier', 'like', "%$query%");
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

    // private function validateProductsFromFile($worksheet)
    // {
    //     // Almacenar los errores de validación
    //     $errorsBag = [];

    //     $columnNames = [];
    //     // Obtener datos y guardar en la base de datos
    //     foreach ($worksheet->getRowIterator() as $row) {
    //         if ($row->getRowIndex() < 3) {
    //             continue; // Saltar las primeras 2 filas
    //         }

    //         $cellIterator = $row->getCellIterator();
    //         $cellIterator->setIterateOnlyExistingCells(false);

    //         if ($row->getRowIndex() == 3) {
    //             // Obtener los nombres de columna de la fila 3 del archivo Excel
    //             foreach ($cellIterator as $cell) {
    //                 $columnNames[] = $cell->getValue();
    //             }
    //             continue;
    //         }

    //         $data = [];
    //         $currentColumn = 0;
    //         foreach ($cellIterator as $cell) {
    //             $columnName = $columnNames[$currentColumn++]; // Obtener el nombre de columna
    //             $data[$columnName] = $cell->getValue(); // Asignar el valor al array asociativo usando el nombre de columna
    //         }

    //         // Validar los datos
    //         $validator = Validator::make($data, [
    //             $columnNames[0] => 'required|string|max:120',
    //         ]);

    //         // Si la validación falla, almacenar los errores
    //         if ($validator->fails()) {
    //             $errorsBag[] = [
    //                 'row' => $row->getRowIndex(),
    //                 'errors' => $validator->errors()->all(),
    //             ];
    //         }
    //     }

    //     return $errorsBag;
    // }

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

                // Buscar la posición de la columna 'Nombre del producto'
                $productNameColumnIndex = array_search('Nombre del producto', $columnNames);
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
                $columnNames[$productNameColumnIndex] => 'required|string|max:120',
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


    // private function storeProductsFromFile($worksheet)
    // {
    //     foreach ($worksheet->getRowIterator() as $row) {
    //         if ($row->getRowIndex() < 3) {
    //             continue; // Saltar las primeras 2 filas
    //         }

    //         $cellIterator = $row->getCellIterator();
    //         $cellIterator->setIterateOnlyExistingCells(false);

    //         if ($row->getRowIndex() == 3) {
    //             // Obtener los nombres de columna de la cuarta fila del archivo Excel
    //             foreach ($cellIterator as $cell) {
    //                 $columnNames[] = $cell->getValue();
    //             }
    //             continue;
    //         }

    //         $data = [];
    //         foreach ($cellIterator as $cell) {
    //             $data[] = $cell->getValue(); // Asignar el valor al array asociativo usando el nombre de columna
    //         }

    //         /**
    //          * Primero buscar la categoria por nombre en el modelo Category. El nombre de la categoria se obtiene del primer valor de la primer columna del archivo
    //          * Despues obtener la propiedad $category->key
    //          * Despues de la ultima subcategoria (columa anterior a la llamada 'Nombre del producto'), obtener en una variable el encabezado y separar el texto por espacio en blanco y obtener el index 1
    //          * Despues del texto obtenido, retirar los puntos si es que los hay y guardar en $path
    //          * Finalmente obtener el ultimo id del modelo Product y sumarle 1 para seguir con el consecutivo y guardarlo en $consecutive
    //          * $partNumber = $category->key . $path . $consecutive; 
    //          */
    //         $partNumber = 0;

    //         /**
    //          * arreglo formado por todas las columnas en pares despues de 'Ubicación en almacén' con formato:
    //          * ["encabezado de columa 1 del par" => "valor de registro en esta columna", "measure_unit" => "valor de registro en siguiente columna del mismo par"]
    //          */
    //         $features = [];
    //         Product::create([
    //             'name' => $data[], //index el cual corresponda a valor de columna llamada 'Nombre del producto'
    //             'description' => $data[], //index el cual corresponda a valor de columna llamada 'Descripción'
    //             'part_number' => $partNumber,
    //             'part_number_supplier' => $data[], //index el cual corresponda a valor de columna llamada 'Número de parte de fabricante'
    //             'location' => $data[], //index el cual corresponda a valor de columna llamada 'Ubicación en almacén'
    //             'features' => $features,
    //             'subcategory_id' => $data[], //id de último nivel de subcategoría. antes de la columna llamada 'Nombre del producto' tomar el nombre de la categoria y buscarla en el modelo Subcategory
    //         ]);
    //     }
    // }

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

                // Buscar la posición de la columna 'Nombre del producto'
                $productNameColumnIndex = array_search('Nombre del producto', $columnNames);
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
            $partNumber = $categoryKey . $path . $consecutive;

            // Obtener las características después de 'Ubicación en almacén'
            $features = [];
            for ($i = $productNameColumnIndex + 2; $i < count($columnNames); $i += 2) {
                $features[] = [
                    'name' => $columnNames[$i],
                    'value' => $data[$i],
                    'measure_unit' => $data[$i + 1]
                ];
            }

            // Obtener el ID de la subcategoría final antes de 'Nombre del producto'
            $subCategoryName = $data[$productNameColumnIndex - 1];
            $subcategory = Subcategory::where('name', $subCategoryName)->firstOrFail();

            // Guardar el producto en la base de datos
            Product::create([
                'name' => $data[$productNameColumnIndex],
                'description' => $data[$productNameColumnIndex + 1], // Asumimos que la columna 'Descripción' está inmediatamente después de 'Nombre del producto'
                'part_number' => $partNumber,
                'part_number_supplier' => $data[$productNameColumnIndex + 2], // Asumimos que la columna 'Número de parte de fabricante' está inmediatamente después de 'Descripción'
                'location' => $data[$productNameColumnIndex + 3], // Asumimos que la columna 'Ubicación en almacén' está inmediatamente después de 'Número de parte de fabricante'
                'features' => $features,
                'subcategory_id' => $subcategory->id,
            ]);
        }
    }
}

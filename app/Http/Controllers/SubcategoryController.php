<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SubcategoryController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'required|string|max:10',
            'level' => 'required|numeric|min:1|max:7',
            'features' => 'nullable',
            'category_id' => 'required',
            'subcategory_id' => 'nullable',
        ]);

        Subcategory::create($request->all());
    }

    public function show(Subcategory $subcategory)
    {
        //
    }

    public function edit(Subcategory $subcategory)
    {
        //
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        //
    }

    public function destroy(Subcategory $subcategory)
    {
        //
    }

    public function generateExcelTemplate($subcategoryId)
    {
        // Obtener la subcategoría seleccionada y sus relaciones
        $subcategory = Subcategory::with('category', 'category.subcategories')->findOrFail($subcategoryId);

        // Crear una nueva hoja de cálculo
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Variables de encabezados y valores
        $currentSubcategory = $subcategory;
        $headers = [];
        $values = [];
        $preFilledColumns = [];

        // Instrucciones en la fila 1
        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'Instrucciones: Llena los campos en blanco y duplica los campos prellenados para cada producto que agregues');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF808080'], // Fondo gris
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'], // Texto blanco
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Crear una pila para almacenar las subcategorías en el orden correcto
        $subcategoryStack = [];

        // Primero, recorre hasta llegar al nivel más alto (primer nivel de subcategoría)
        while ($currentSubcategory !== null) {
            array_unshift($subcategoryStack, $currentSubcategory);
            $currentSubcategory = Subcategory::find($currentSubcategory->prev_subcategory_id);
        }

        // Ahora que tenemos las subcategorías en la pila, generamos los encabezados
        $sufix = '';
        foreach ($subcategoryStack as $level => $subcat) {
            $sufix = ($sufix === '') ? $subcat->key : $sufix . '.' . $subcat->key;
            $headers[] = 'Subcategoría ' . $sufix;
            $values[] = $subcat->name;
            $preFilledColumns[] = 'Campo prellenado';
        }

        // Agregar la categoría principal
        array_unshift($headers, 'Categoría principal');
        array_unshift($values, $subcategory->category->name);
        array_unshift($preFilledColumns, 'Campo prellenado');

        // Añadir columnas fijas
        $headers = array_merge($headers, ['Número de parte de fabricante', 'Descripción', 'Ubicación en almacén']);
        $values = array_merge($values, ['', '', '']);
        $preFilledColumns = array_merge($preFilledColumns, ['', '', '']);

        // Añadir columnas de características (features) de la subcategoría seleccionada
        foreach ($subcategory->features as $feature) {
            $headers[] = $feature['name'];
            $headers[] = 'Unidad de medida';
            $values[] = '';
            $values[] = $feature['measure_unit'];
            $preFilledColumns[] = '';
            $preFilledColumns[] = 'Campo prellenado';
        }

        // Llenar las celdas del encabezado de la fila 2 (campos prellenados y obligatorios)
        foreach ($preFilledColumns as $col => $header) {
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '2';
            $sheet->setCellValue($cell, $header);
        }

        // Aplicar estilos a la fila 2 (fondo blanco y texto #808080)
        $row2Style = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFFFFFF'], // Fondo blanco
            ],
            'font' => [
                'color' => ['argb' => 'FF808080'], // Texto gris (#808080)
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
        $sheet->getStyle('A2:' . $sheet->getHighestColumn() . '2')->applyFromArray($row2Style);

        // Llenar las celdas del encabezado de la fila 3 (columnas finales)
        foreach ($headers as $col => $header) {
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '3';
            $sheet->setCellValue($cell, $header);
        }

        // Llenar las celdas de valores en la fila 4
        if (!request('withProducts')) {
            foreach ($values as $col => $value) {
                $cell = Coordinate::stringFromColumnIndex($col + 1) . '4';
                $sheet->setCellValue($cell, $value);
            }
        }

        // Aplicar estilos a las celdas del encabezado (fila 3)
        $headerStyle = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDDEBF7'], // Color de fondo
            ],
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF1676A2'], // Color de texto
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];

        $sheet->getStyle('A3:' . $sheet->getHighestColumn() . '3')->applyFromArray($headerStyle);

        // Ajustar el tamaño de las columnas
        $highestColumn = $sheet->getHighestColumn(); // Obtener la columna más alta
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn); // Convertir la letra de la columna a índice
        foreach (range(1, $highestColumnIndex) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        // agregar productos
        if (request('withProducts')) {
            // Obtener todos los productos de la subcategoría
            $products = Product::where('subcategory_id', $subcategoryId)->get();

            // Rellenar los datos de los productos en las filas siguientes
            $rowIndex = 4; // Empezar en la fila 4 después de los encabezados y valores prellenados
            foreach ($products as $product) {
                // Inicializar el array con los valores prellenados
                $productData = [...$values];

                // Reemplazar los valores correspondientes con los datos del producto
                // $productData[array_search('Nombre del producto', $headers)] = $product->name;
                $productData[array_search('Número de parte de fabricante', $headers)] = $product->part_number_supplier;
                $productData[array_search('Descripción', $headers)] = $product->description;
                $productData[array_search('Ubicación en almacén', $headers)] = $product->location;

                // Añadir valores de las características (features)
                foreach ($subcategory->features as $key => $feature) {
                    $featureIndex = array_search($feature['name'], $headers);
                    $unitIndex = $featureIndex + 1; // La unidad de medida siempre sigue a la característica en el orden de las columnas
                    $productData[$featureIndex] = $product->features[$key]['value'] ?? '';
                    $productData[$unitIndex] = $feature['measure_unit'];
                }

                // Llenar las celdas de la fila actual
                foreach ($productData as $col => $value) {
                    $cell = Coordinate::stringFromColumnIndex($col + 1) . $rowIndex;
                    $sheet->setCellValue($cell, $value);
                }

                $rowIndex++;
            }
        }

        // Descargar el archivo Excel en lugar de guardarlo en el servidor
        $writer = new Xlsx($spreadsheet);
        $filename = 'plantilla_productos_' . $subcategory->category->name . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        $writer->save('php://output');
    }

    public function getSubcategoryProducts(Subcategory $subcategory)
    {
        $products = $subcategory->products;
        return response()->json(['items' => $products]);
    }
}

<?php

namespace App\Http\Controllers;

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

    // public function generateExcelTemplate($subcategoryId)
    // {
    //     // Obtener la subcategoría seleccionada y sus relaciones
    //     $subcategory = Subcategory::with('category', 'category.subcategories')->findOrFail($subcategoryId);

    //     // Crear una nueva hoja de cálculo
    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();

    //     // Variables de encabezados y valores
    //     $headers = [];
    //     $values = [];

    //     // Obtener la cadena de subcategorías (niveles superiores)
    //     $currentSubcategory = $subcategory;
    //     while ($currentSubcategory->prev_subcategory_id !== null) {
    //         $parentSubcategory = Subcategory::find($currentSubcategory->prev_subcategory_id);
    //         array_unshift($headers, 'Subcategoría ' . $parentSubcategory->key);
    //         array_unshift($values, $parentSubcategory->name);
    //         $currentSubcategory = $parentSubcategory;
    //     }
    //     // Agregar la categoría principal
    //     array_unshift($headers, 'Categoría principal');
    //     array_unshift($values, $subcategory->category->name);

    //     // Añadir columnas fijas
    //     $headers = array_merge($headers, ['Nombre del producto', 'Descripción', 'Ubicación en almacén']);
    //     $values = array_merge($values, ['', '', '']);

    //     // Añadir columnas de características (features) de la subcategoría seleccionada
    //     foreach ($subcategory->features as $feature) {
    //         $headers[] = $feature['name'];
    //         $headers[] = 'Unidad de medida';
    //         $values[] = '';
    //         $values[] = $feature['measure_unit'];
    //     }

    //     // Llenar las celdas del encabezado (fila 1)
    //     foreach ($headers as $col => $header) {
    //         $cell = Coordinate::stringFromColumnIndex($col + 1) . '1';
    //         $sheet->setCellValue($cell, $header);
    //     }

    //     // Llenar las celdas de valores (fila 2)
    //     foreach ($values as $col => $value) {
    //         $cell = Coordinate::stringFromColumnIndex($col + 1) . '2';
    //         $sheet->setCellValue($cell, $value);
    //     }

    //     // Aplicar estilos a las celdas del encabezado
    //     $headerStyle = [
    //         'fill' => [
    //             'fillType' => Fill::FILL_SOLID,
    //             'startColor' => ['argb' => 'FFD9EAD3'], // Color de fondo
    //         ],
    //         'font' => [
    //             'bold' => true,
    //             'color' => ['argb' => 'FF000000'], // Color de texto
    //         ],
    //         'alignment' => [
    //             'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    //         ]
    //     ];

    //     $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

    //     // Descargar el archivo Excel en lugar de guardarlo en el servidor
    //     $writer = new Xlsx($spreadsheet);
    //     $filename = 'plantilla_productos_' . $subcategory->category->name . '.xlsx';

    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
    //     $writer->save('php://output');
    // }

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
        $headers = array_merge($headers, ['Nombre del producto', 'Descripción', 'Número de parte de fabricante', 'Ubicación en almacén']);
        $values = array_merge($values, ['', '', '', '']);
        $preFilledColumns = array_merge($preFilledColumns, ['Campo obligatorio', '', '', '']);

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
        foreach ($values as $col => $value) {
            $cell = Coordinate::stringFromColumnIndex($col + 1) . '4';
            $sheet->setCellValue($cell, $value);
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

        // Descargar el archivo Excel en lugar de guardarlo en el servidor
        $writer = new Xlsx($spreadsheet);
        $filename = 'plantilla_productos_' . $subcategory->category->name . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        $writer->save('php://output');
    }
}

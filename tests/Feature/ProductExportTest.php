<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $category;
    protected $bearingSubcategory;
    protected $steelBearingSubcategory;
    protected $retainerSubcategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);

        // Catálogo de prueba:
        // Rodamientos (ROD)
        //   └ Bolas (BOL) -> con la característica Diámetro (mm)
        //       └ Bolas de acero (BAC)
        // Sellos (SEL)
        //   └ Retenes (RET)
        $this->category = Category::create(['name' => 'Rodamientos', 'key' => 'ROD']);
        $this->bearingSubcategory = Subcategory::create([
            'name' => 'Bolas',
            'key' => 'BOL',
            'level' => 1,
            'category_id' => $this->category->id,
            'features' => [
                ['name' => 'Diámetro', 'measure_unit' => 'mm'],
            ],
        ]);
        $this->steelBearingSubcategory = Subcategory::create([
            'name' => 'Bolas de acero',
            'key' => 'BAC',
            'level' => 2,
            'category_id' => $this->category->id,
            'prev_subcategory_id' => $this->bearingSubcategory->id,
        ]);

        $sealsCategory = Category::create(['name' => 'Sellos', 'key' => 'SEL']);
        $this->retainerSubcategory = Subcategory::create([
            'name' => 'Retenes',
            'key' => 'RET',
            'level' => 1,
            'category_id' => $sealsCategory->id,
        ]);
    }

    public function test_export_options_requires_authentication()
    {
        $this->get(route('products.export-options'))->assertRedirect(route('login'));
    }

    public function test_export_options_returns_the_category_tree_with_product_counts()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');
        $this->createProduct($this->steelBearingSubcategory, 'ROD-BAC-001', 'E-5');
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        $response = $this->actingAs($this->user)->get(route('products.export-options'));

        $response->assertOk();
        $response->assertJsonPath('categories.0.label', 'Rodamientos');
        $response->assertJsonPath('categories.0.type', 'category');
        // El total de la categoría suma las subcategorías hijas (2 productos).
        $response->assertJsonPath('categories.0.products_count', 2);
        $response->assertJsonPath('categories.0.children.0.label', 'Bolas');
        $response->assertJsonPath('categories.0.children.0.node_key', 'subcategory-' . $this->bearingSubcategory->id);
        // El total de la subcategoría padre incluye a su subcategoría hija.
        $response->assertJsonPath('categories.0.children.0.products_count', 2);
        $response->assertJsonPath('categories.0.children.0.children.0.label', 'Bolas de acero');
        $response->assertJsonPath('categories.0.children.0.children.0.products_count', 1);
        $response->assertJsonPath('categories.1.label', 'Sellos');
        $response->assertJsonPath('categories.1.products_count', 1);
    }

    public function test_export_downloads_an_excel_file_with_the_whole_catalog()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        $response = $this->actingAs($this->user)->get(route('products.export'));

        $response->assertOk();
        $response->assertDownload();

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertCount(3, $rows, 'Debe haber un encabezado y una fila por producto.');
        $this->assertSame('Categoría', $rows[0][0]);
        $this->assertSame('Ruta de subcategorías', $rows[0][1]);
        $this->assertSame('Número de parte interno', $rows[0][3]);
        $this->assertEqualsCanonicalizing(
            ['ROD-BOL-001', 'SEL-RET-001'],
            $this->exportedPartNumbers($rows)
        );
    }

    public function test_export_filters_products_by_category_including_its_subcategories()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');
        $this->createProduct($this->steelBearingSubcategory, 'ROD-BAC-001', 'E-5');
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        $response = $this->actingAs($this->user)->get(route('products.export', [
            'category_ids' => [$this->category->id],
        ]));

        $response->assertOk();
        $response->assertDownload();

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertEqualsCanonicalizing(
            ['ROD-BOL-001', 'ROD-BAC-001'],
            $this->exportedPartNumbers($rows)
        );
    }

    public function test_export_filters_products_by_subcategory_including_its_children()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');
        $this->createProduct($this->steelBearingSubcategory, 'ROD-BAC-001', 'E-5');
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        $response = $this->actingAs($this->user)->get(route('products.export', [
            'subcategory_ids' => [$this->bearingSubcategory->id],
        ]));

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertEqualsCanonicalizing(
            ['ROD-BOL-001', 'ROD-BAC-001'],
            $this->exportedPartNumbers($rows)
        );
    }

    public function test_export_can_be_downloaded_without_features_or_cost_columns()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');

        $response = $this->actingAs($this->user)->get(route('products.export', [
            'subcategory_ids' => [$this->bearingSubcategory->id],
            'include_features' => 0,
            'include_costs' => 0,
        ]));

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertSame([
            'Categoría',
            'Ruta de subcategorías',
            'Nombre del producto',
            'Número de parte interno',
            'Número de parte de fabricante',
            'Descripción',
            'Ubicación en almacén',
        ], $rows[0]);
    }

    public function test_export_includes_the_subcategory_feature_columns()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');

        $response = $this->actingAs($this->user)->get(route('products.export', [
            'subcategory_ids' => [$this->bearingSubcategory->id],
        ]));

        $rows = $this->readExportedRows($response->streamedContent());

        // 7 columnas fijas + Moneda + Costo + la característica "Diámetro (mm)".
        $this->assertSame('Moneda', $rows[0][7]);
        $this->assertSame('Costo', $rows[0][8]);
        $this->assertSame('Diámetro (mm)', $rows[0][9]);
        $this->assertSame('30', (string) $rows[1][9]);
    }

    public function test_export_accepts_a_post_payload_with_the_tree_selection()
    {
        $this->createProduct($this->bearingSubcategory, 'ROD-BOL-001', '6203');
        $this->createProduct($this->steelBearingSubcategory, 'ROD-BAC-001', 'E-5');
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        // El modal envía la selección del árbol en el cuerpo: una categoría completa
        // puede arrastrar cientos de subcategorías y no caben en la query string.
        $response = $this->actingAs($this->user)->post(route('products.export'), [
            'category_ids' => [$this->category->id],
            'subcategory_ids' => [$this->retainerSubcategory->id],
            'include_features' => 1,
            'include_costs' => 1,
        ]);

        $response->assertOk();

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertEqualsCanonicalizing(
            ['ROD-BOL-001', 'ROD-BAC-001', 'SEL-RET-001'],
            $this->exportedPartNumbers($rows)
        );
    }

    public function test_export_accepts_a_large_selection_sent_as_json_body()
    {
        $this->createProduct($this->retainerSubcategory, 'SEL-RET-001', 'TC-25');

        // Muchos ids (reales e inexistentes): en el cuerpo no aplican ni el límite de
        // tamaño de la URL ni max_input_vars, así que la descarga no se corta.
        $response = $this->actingAs($this->user)->postJson(route('products.export'), [
            'subcategory_ids' => array_merge([$this->retainerSubcategory->id], range(900000, 900300)),
        ]);

        $response->assertOk();

        $rows = $this->readExportedRows($response->streamedContent());

        $this->assertSame(['SEL-RET-001'], $this->exportedPartNumbers($rows));
    }

    /**
     * Crea un producto de prueba dentro de una subcategoría.
     */
    private function createProduct(Subcategory $subcategory, string $partNumber, string $supplierNumber): Product
    {
        return Product::create([
            'name' => 'Producto ' . $supplierNumber,
            'description' => 'Descripción de prueba',
            'part_number' => $partNumber,
            'part_number_supplier' => $supplierNumber,
            'location' => 'A-01',
            'line_cost' => 125.5,
            'currency' => '$MXN',
            'features' => [
                ['name' => 'Diámetro', 'value' => '30', 'measure_unit' => 'mm'],
            ],
            'bread_crumbles' => [$subcategory->category->name, $subcategory->name],
            'subcategory_id' => $subcategory->id,
        ]);
    }

    /**
     * Lee el contenido binario del Excel descargado y lo regresa como matriz de filas.
     */
    private function readExportedRows(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'products_export_') . '.xlsx';
        file_put_contents($path, $contents);

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $spreadsheet->disconnectWorksheets();
        @unlink($path);

        return $rows;
    }

    /**
     * Números de parte internos presentes en el archivo exportado (sin el encabezado).
     */
    private function exportedPartNumbers(array $rows): array
    {
        $partNumbers = [];

        foreach (array_slice($rows, 1) as $row) {
            $partNumbers[] = (string) $row[3];
        }

        return $partNumbers;
    }
}

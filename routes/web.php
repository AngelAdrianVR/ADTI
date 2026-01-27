<?php

use App\Http\Controllers\BioTimeTransactionsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\JobPositionController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\MeasureUnitController;
use App\Http\Controllers\PayrollCommentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollUserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Página Principal (Landing)
Route::get('/', function () {
    $categories = Category::with('subcategories', 'media')->get();
    return Inertia::render('Welcome', [
        'categories' => $categories,
    ]);
})->name('welcome');

// Rutas Públicas de Productos
//ruta para mostrar las subcategorías de categoría
Route::get('/show-category/{category_id}', function ($category_id) {
    $category = Category::with(['media', 'subcategories.media'])->find($category_id);

    // return $category;
    return Inertia::render('LandingPage/ShowCategory', [
        'category' => $category
    ]);
})->name('public.show-category');


//ruta para mostrar las subcategorías de una subcategoría seleccionada
Route::get('/show-subcategory/{subcategory_id}', function ($subcategory_id) {
    $subcategory = Subcategory::with(['media', 'products', 'category.subcategories.media', 'category.media'])->find($subcategory_id);

    $total_products = Product::where('subcategory_id', $subcategory_id)->count();

    // return $subcategory;
    return Inertia::render('LandingPage/ShowSubcategory', [
        'subcategory' => $subcategory,
        'total_products' => $total_products // cantidad de productos que contiene esa subcategoría
    ]);
})->name('public.show-subcategory');


//ruta para mostrar producto encontrado desde barra buscadora de inicio
Route::get('/show-product/{product_id}', function ($product_id) {
    $product = Product::with(['media', 'subcategory' => ['category.subcategories']])->find($product_id);

    // return $product;
    return Inertia::render('LandingPage/ShowProduct', [
        'product' => $product
    ]);
})->name('public.show-product');


// --- RUTAS AUTENTICADAS ---
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Catálogos Generales (Resources)
    Route::resource('categories', CategoryController::class);
    Route::get('categories/fetch-subcategories/{category}', [CategoryController::class, 'fetchSubcategories'])->name('categories.fetch-subcategories');
    Route::post('categories/update-with-media/{category}', [CategoryController::class, 'updateWithMedia'])->name('categories.update-with-media');
    Route::post('categories/store-with-subcategories', [CategoryController::class, 'storeWithSubcategories'])->name('categories.store-with-subcategories');
    Route::post('categories/update-with-subcategories/{category}', [CategoryController::class, 'updateWithSubcategories'])->name('categories.update-with-subcategories');
    Route::get('categories-get-all', [CategoryController::class, 'getAll'])->name('categories.get-all');

    Route::resource('subcategories', SubcategoryController::class);
    Route::post('subcategories/update-with-media/{subcategory}', [SubcategoryController::class, 'updateWithMedia'])->name('subcategories.update-with-media');
    Route::get('subcategories-download-excel-template/{subcategory}', [SubcategoryController::class, 'generateExcelTemplate'])->name('subcategories.download-excel-template');
    Route::get('subcategories-get-products/{subcategory}', [SubcategoryController::class, 'getSubcategoryProducts'])->name('subcategories.get-products');

    Route::get('products-print-barcodes', [ProductController::class, 'printBarcodes'])->name('products.print-barcodes');
    Route::resource('products', ProductController::class);
    Route::post('products/update-with-media/{product}', [ProductController::class, 'updateWithMedia'])->name('products.update-with-media');
    Route::post('products/massive-delete', [ProductController::class, 'massiveDelete'])->name('products.massive-delete');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::post('products/get-consecutivo/{subcategory_id}', [ProductController::class, 'getConsecutivo'])->name('products.get-consecutivo');
    Route::delete('products/delete-file/{file_id}', [ProductController::class, 'deleteFile'])->name('products.delete-file');
    Route::get('/products/{id}/next', [ProductController::class, 'getNextProduct'])->name('products.next');
    Route::get('/products/{id}/previous', [ProductController::class, 'getPreviousProduct'])->name('products.previous');

    Route::resource('users', UserController::class);
    Route::get('users-get-next-attendance', [UserController::class, 'getNextAttendance'])->name('users.get-next-attendance');
    Route::get('users-get-pause-status', [UserController::class, 'getPauseStatus'])->name('users.get-pause-status');
    Route::get('users-set-pause', [UserController::class, 'setPause'])->name('users.set-pause');
    Route::post('users/update-with-media/{user}', [UserController::class, 'updateWithMedia'])->name('users.update-with-media');
    Route::post('users/massive-delete', [UserController::class, 'massiveDelete'])->name('users.massive-delete');
    Route::post('users/massive-delete-media', [UserController::class, 'massiveDeleteMedia'])->name('users.massive-delete-media');
    Route::post('users/{user}/store-media', [UserController::class, 'storeMedia'])->name('users.store-media');
    Route::post('users-set-attendance', [UserController::class, 'setAttendance'])->name('users.set-attendance');
    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::put('users/inactivate/{user}', [UserController::class, 'inactivate'])->name('users.inactivate');
    Route::put('users/media/{media}/update-name', [UserController::class, 'updateMediaName'])->name('users.update-media-name');
    Route::put('users/{user}/update-vacations', [UserController::class, 'updateVacations'])->name('users.update-vacations');
    Route::put('users/{user}/toggle-home-office', [UserController::class, 'toggleHomeOffice'])->name('users.toggle-home-office');
    Route::get('users/reactivatation/{user}', [UserController::class, 'reactivation'])->name('users.reactivation');
    Route::get('users/{user}/performance', [UserController::class, 'getPerformance'])->name('users.get-performance');

    Route::resource('departments', DepartmentController::class);
    Route::resource('features', FeatureController::class);
    Route::resource('job-positions', JobPositionController::class);

    // --- NÓMINAS (PAYROLLS) ---
    Route::resource('payrolls', PayrollController::class);
    Route::get('payrolls/{payroll}/pre-payroll', [PayrollController::class, 'prePayrollTemplate'])->name('payrolls.pre-payroll');

    Route::resource('payroll-comments', PayrollCommentController::class)->middleware('auth');

    // --- USUARIOS DE NÓMINA (PAYROLL USERS) ---
    // Agregamos rutas específicas para las acciones de los empleados en la nómina
    Route::post('payroll-users/set-attendance', [PayrollUserController::class, 'store'])->name('payroll-users.set-attendance'); // Asistencia manual (admin)
    Route::put('payroll-users/update-attendance', [PayrollUserController::class, 'update'])->name('payroll-users.update-attendance'); // Editar horas
    Route::put('payroll-users/set-incidence', [PayrollUserController::class, 'setIncidence'])->name('payroll-users.set-incidence');
    Route::put('payroll-users/remove-late', [PayrollUserController::class, 'removeLate'])->name('payroll-users.remove-late');

    Route::resource('holidays', HolidayController::class);
    Route::post('holidays/massive-delete', [HolidayController::class, 'massiveDelete'])->name('holidays.massive-delete');
    Route::resource('kiosks', KioskController::class);
    Route::resource('measure-units', MeasureUnitController::class);

    // --- CONFIGURACIONES (SETTINGS) ---
    Route::prefix('settings')->name('settings.')->controller(SettingController::class)->group(function () {
        Route::get('catalogos', 'index')->name('index');
        Route::get('permisos', 'permissions')->name('permissions');
        Route::get('general', 'general')->name('general');

        // Acciones para Roles y Permisos
        Route::name('role-permission.')->group(function () {
            Route::post('store-role', 'storeRole')->name('store-role');
            Route::put('update-role/{role_id}', 'updateRole')->name('update-role');
            Route::delete('delete-role/{role_id}', 'deleteRole')->name('delete-role');

            Route::post('store-permission', 'storePermission')->name('store-permission');
            Route::put('update-permission/{permission_id}', 'updatePermission')->name('update-permission');
            Route::delete('delete-permission/{permission_id}', 'deletePermission')->name('delete-permission');
        });
    });

    // --- PROYECTOS & TIME TRACKING ---
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/start', [ProjectController::class, 'startWork'])->name('projects.start');
    Route::post('projects/{project}/pause', [ProjectController::class, 'togglePause'])->name('projects.pause');
    Route::post('projects/{project}/stop', [ProjectController::class, 'stopWork'])->name('projects.stop');
    Route::post('projects/add-time-entry', [ProjectController::class, 'addTimeEntry'])->name('projects.add-time-entry');

    // marcar tarea como terminada/pendiente
    Route::put('tasks/{task}/toggle-status', [ProjectController::class, 'toggleTaskStatus'])->name('tasks.toggle-status');

    // --- CATÁLOGO DE TAREAS ---
    Route::post('default-tasks', [ProjectController::class, 'storeDefaultTask'])->name('default-tasks.store');
    Route::delete('default-tasks/{default_task}', [ProjectController::class, 'destroyDefaultTask'])->name('default-tasks.destroy');
});

Route::get('products-search', [ProductController::class, 'searchProduct'])->name('products.search');
Route::get('products-fetch-subcategory-products/{subcategory_id}', [ProductController::class, 'fetchSubcategoryProducts'])->name('products.fetch-subcategory-products');

// Comandos de utilidad (Artisan)
Route::get('/clear-all', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return 'cleared.';
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage link created.';
});

Route::get('/payrolls-close', function () {
    Artisan::call('payrolls:close');
    return 'Listo!';
});

// --- OTROS / API ---
Route::get('/api/process-transaction/{time}/{emp_code}', [PayrollUserController::class, 'processBioTimeTransaction']);
Route::get('/api/get-total-processed-count/', [BioTimeTransactionsController::class, 'getTotalProcessedCount']);
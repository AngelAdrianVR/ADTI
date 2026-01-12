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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página Principal (Landing)
Route::get('/', function () {
    $categories = Category::with('subcategories', 'media')->get();
    return Inertia::render('Welcome', [
        'categories' => $categories,
    ]);
})->name('welcome');

// Ver categoría pública
Route::get('/show-category/{category_id}', function ($category_id) {
    $category = Category::with(['media', 'subcategories.media'])->find($category_id);
    return Inertia::render('LandingPage/ShowCategory', [
        'category' => $category,
        'products' => $category->products
    ]);
})->name('public.show-category');

Route::get('/show-subcategory/{subcategory_id}', function ($subcategory_id) {
    // Lógica para subcategoría (asumida basada en patrones anteriores)
    return Inertia::render('LandingPage/ShowSubcategory'); 
})->name('public.show-subcategory');

Route::get('/show-product/{product_id}', function ($product_id) {
    // Lógica para producto público (asumida)
    return Inertia::render('LandingPage/ShowProduct');
})->name('public.show-product');


// --- RUTAS AUTENTICADAS ---
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Catálogos Generales (Resources)
    Route::resource('categories', CategoryController::class);
    Route::get('categories-get-all', [CategoryController::class, 'getAll'])->name('categories.get-all');
    
    Route::resource('subcategories', SubcategoryController::class);
    
    Route::resource('products', ProductController::class);
    Route::get('products-search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/{id}/next', [ProductController::class, 'getNextProduct'])->name('products.next');
    Route::get('/products/{id}/previous', [ProductController::class, 'getPreviousProduct'])->name('products.previous');
    Route::delete('products/delete-file/{file_id}', [ProductController::class, 'deleteFile'])->name('products.delete-file');

    Route::resource('users', UserController::class);
    Route::get('users-get-next-attendance', [UserController::class, 'getNextAttendance'])->name('users.get-next-attendance');
    Route::post('users-set-attendance', [UserController::class, 'setAttendance'])->name('users.set-attendance');
    Route::get('users-get-pause-status', [UserController::class, 'getPauseStatus'])->name('users.get-pause-status');
    Route::get('users-set-pause', [UserController::class, 'setPause'])->name('users.set-pause');
    Route::put('users/{user}/update-vacations', [UserController::class, 'updateVacations'])->name('users.update-vacations');
    Route::put('users/{user}/toggle-home-office', [UserController::class, 'toggleHomeOffice'])->name('users.toggle-home-office');
    Route::put('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/store-media', [UserController::class, 'storeMedia'])->name('users.store-media');
    Route::put('users/media/{media}/update-name', [UserController::class, 'updateMediaName'])->name('users.update-media-name');
    Route::post('users/massive-delete-media', [UserController::class, 'massiveDeleteMedia'])->name('users.massive-delete-media');
    
    Route::resource('departments', DepartmentController::class);
    Route::resource('features', FeatureController::class);
    Route::resource('job-positions', JobPositionController::class);
    Route::resource('payrolls', PayrollController::class);
    Route::resource('holidays', HolidayController::class);
    Route::resource('kiosks', KioskController::class);
    Route::resource('measure-units', MeasureUnitController::class);

    // --- CONFIGURACIONES (SETTINGS) ---
    Route::prefix('settings')->name('settings.')->controller(SettingController::class)->group(function () {
        Route::get('catalogos', 'index')->name('index');       // Vista Categorías
        Route::get('permisos', 'permissions')->name('permissions'); // Vista Roles y Permisos (Unified)
        Route::get('general', 'general')->name('general');     // Vista General
        
        // Acciones para Roles y Permisos
        Route::name('role-permission.')->group(function() {
            Route::post('store-role', 'storeRole')->name('store-role');
            Route::put('update-role/{role}', 'updateRole')->name('update-role');
            Route::delete('delete-role/{role}', 'deleteRole')->name('delete-role');
            
            Route::post('store-permission', 'storePermission')->name('store-permission');
            Route::put('update-permission/{permission}', 'updatePermission')->name('update-permission');
            Route::delete('delete-permission/{permission}', 'deletePermission')->name('delete-permission');
        });
    });

    // --- PROYECTOS & TIME TRACKING ---
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/start', [ProjectController::class, 'startWork'])->name('projects.start');
    Route::post('projects/{project}/pause', [ProjectController::class, 'togglePause'])->name('projects.pause');
    Route::post('projects/{project}/stop', [ProjectController::class, 'stopWork'])->name('projects.stop');

    // --- OTROS / API ---
    Route::get('/api/process-transaction/{time}/{emp_code}', [PayrollUserController::class, 'processBioTimeTransaction']);
    Route::get('/api/get-total-processed-count/', [BioTimeTransactionsController::class, 'getTotalProcessedCount']);
});

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
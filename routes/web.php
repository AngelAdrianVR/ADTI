<?php

use App\Http\Controllers\BioTimeTransactionsController;
use App\Http\Controllers\CategoryController;
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
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UserController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

//muestra la página principal de la landing.
Route::get('/', function () {
    $categories = Category::with('subcategories', 'media')->get();

    return Inertia::render('Welcome', [
        'categories' => $categories,
    ]);
})->name('welcome');


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


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        $categories = Category::with(['subcategories:id,name,category_id' => ['products:id,name,subcategory_id']])->get();
        $total_products = Product::all()->count();

        // return $categories;
        return Inertia::render('Dashboard', [
            'categories' => $categories,
            'total_products' => $total_products,
        ]);
    })->name('dashboard');
});


//users routes----------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------
Route::resource('users', UserController::class)->middleware('auth')->middleware('auth');
Route::get('users/reactivatation/{user}', [UserController::class, 'reactivation'])->name('users.reactivatation')->middleware('auth');
Route::get('users-get-next-attendance', [UserController::class, 'getNextAttendance'])->middleware('auth')->name('users.get-next-attendance');
Route::get('users-get-pause-status', [UserController::class, 'getPauseStatus'])->middleware('auth')->name('users.get-pause-status');
Route::get('users-set-pause', [UserController::class, 'setPause'])->middleware('auth')->name('users.set-pause');
Route::post('users/update-with-media/{user}', [UserController::class, 'updateWithMedia'])->name('users.update-with-media')->middleware('auth');
Route::post('users/massive-delete', [UserController::class, 'massiveDelete'])->name('users.massive-delete');
Route::post('users/massive-delete-media', [UserController::class, 'massiveDeleteMedia'])->name('users.massive-delete-media');
Route::post('users/store-media/{user}', [UserController::class, 'storeMedia'])->name('users.store-media');
Route::post('users-set-attendance', [UserController::class, 'setAttendance'])->middleware('auth')->name('users.set-attendance');
Route::put('users/reset-password/{user}', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('auth');
Route::put('users/inactivate/{user}', [UserController::class, 'inactivate'])->name('users.inactivate');
Route::put('users/update-vacations/{user}', [UserController::class, 'updateVacations'])->name('users.update-vacations');
Route::put('users/update-media-name/{media}', [UserController::class, 'updateMediaName'])->name('users.update-media-name');
Route::put('users/toggle-home-office/{user}', [UserController::class, 'toggleHomeOffice'])->name('users.toggle-home-office');

//products routes----------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------
Route::resource('products', ProductController::class)->middleware('auth');
Route::post('products/update-with-media/{product}', [ProductController::class, 'updateWithMedia'])->name('products.update-with-media')->middleware('auth');
Route::post('products/massive-delete', [ProductController::class, 'massiveDelete'])->name('products.massive-delete');
Route::get('products-search', [ProductController::class, 'searchProduct'])->name('products.search');
Route::get('products-fetch-subcategory-products/{subcategory_id}', [ProductController::class, 'fetchSubcategoryProducts'])->name('products.fetch-subcategory-products');
Route::post('products/import', [ProductController::class, 'import'])->name('products.import')->middleware('auth');
Route::get('products-print-barcodes', [ProductController::class, 'printBarcodes'])->name('products.print-barcodes')->middleware('auth');
Route::post('products/get-consecutivo/{subcategory_id}', [ProductController::class, 'getConsecutivo'])->name('products.get-consecutivo')->middleware('auth');
Route::delete('products/delete-file/{file_id}', [ProductController::class, 'deleteFile'])->name('products.delete-file')->middleware('auth');
Route::get('/products/{id}/next', [ProductController::class, 'getNextProduct']);


//Category routes----------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------
Route::resource('categories', CategoryController::class)->middleware('auth');
Route::get('categories/fetch-subcategories/{category}', [CategoryController::class, 'fetchSubcategories'])->name('categories.fetch-subcategories')->middleware('auth');
Route::post('categories/update-with-media/{category}', [CategoryController::class, 'updateWithMedia'])->name('categories.update-with-media')->middleware('auth');
Route::post('categories/store-with-subcategories', [CategoryController::class, 'storeWithSubcategories'])->name('categories.store-with-subcategories')->middleware('auth');
Route::post('categories/update-with-subcategories/{category}', [CategoryController::class, 'updateWithSubcategories'])->name('categories.update-with-subcategories')->middleware('auth');
Route::get('categories-get-all', [CategoryController::class, 'getAll'])->name('categories.get-all')->middleware('auth');


//Subcategory routes----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------------------------
Route::resource('subcategories', SubcategoryController::class)->middleware('auth');
Route::post('subcategories/update-with-media/{subcategory}', [SubcategoryController::class, 'updateWithMedia'])->name('subcategories.update-with-media')->middleware('auth');
Route::get('subcategories-download-excel-template/{subcategory}', [SubcategoryController::class, 'generateExcelTemplate'])->name('subcategories.download-excel-template')->middleware('auth');
Route::get('subcategories-get-products/{subcategory}', [SubcategoryController::class, 'getSubcategoryProducts'])->name('subcategories.get-products')->middleware('auth');


//measure unit routes----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('measure_units', MeasureUnitController::class)->middleware('auth');


//payrolls routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('payrolls', PayrollController::class)->middleware('auth');
Route::get('payrolls/{payroll}/pre-payroll', [PayrollController::class, 'prePayrollTemplate'])->name('payrolls.pre-payroll')->middleware('auth');


//payroll user routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::put('payroll-user/set-incidence', [PayrollUserController::class, 'setIncidence'])->name('payroll-users.set-incidence')->middleware('auth');
Route::post('payroll-user/set-attendance', [PayrollUserController::class, 'setAttendance'])->name('payroll-users.set-attendance')->middleware('auth');
Route::post('payroll-user/remove-late', [PayrollUserController::class, 'removeLate'])->name('payroll-users.remove-late')->middleware('auth');


//comentarios de nomina routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('payroll-comments', PayrollCommentController::class)->middleware('auth');


//Holiday routes---------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('holidays', HolidayController::class)->middleware('auth');
Route::post('holidays/massive-delete', [HolidayController::class, 'massiveDelete'])->name('holidays.massive-delete');


//features routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('features', FeatureController::class)->middleware('auth');


//departaments routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('departments', DepartmentController::class)->middleware('auth');


//job posotions routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('job-positions', JobPositionController::class)->middleware('auth');


//settings routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('settings', SettingController::class)->middleware('auth');
Route::put('role-permission/{role}/edit-role', [SettingController::class, 'updateRole'])->middleware('auth')->name('settings.role-permission.update-role');
Route::post('role-permission/store-role', [SettingController::class, 'storeRole'])->middleware('auth')->name('settings.role-permission.store-role');
Route::delete('role-permission/{role}/destroy-role', [SettingController::class, 'deleteRole'])->middleware('auth')->name('settings.role-permission.delete-role');
Route::put('role-permission/{permission}/edit-permission', [SettingController::class, 'updatePermission'])->middleware('auth')->name('settings.role-permission.update-permission');
Route::post('role-permission/store-permission', [SettingController::class, 'storePermission'])->middleware('auth')->name('settings.role-permission.store-permission');
Route::delete('role-permission/{permission}/destroy-permission', [SettingController::class, 'deletePermission'])->middleware('auth')->name('settings.role-permission.delete-permission');


//kisko routes--------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::get('kiosk', [KioskController::class, 'index'])->name('kiosk.index')->middleware('auth');

// artisan
Route::get('/clear-all', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return 'cleared.';
});


Route::get('/api/process-transaction/{time}/{emp_code}', [PayrollUserController::class, 'processBioTimeTransaction']);
Route::get('/api/get-todays-transactions/', [BioTimeTransactionsController::class, 'getTodaysTransactions']);
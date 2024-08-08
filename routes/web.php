<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\MeasureUnitController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});


//users routes----------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------
Route::resource('users', UserController::class)->middleware('auth')->middleware('auth');
Route::post('users/update-with-media/{user}', [UserController::class, 'updateWithMedia'])->name('users.update-with-media')->middleware('auth');
Route::put('users/reset-password/{user}', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('auth');
Route::post('users/massive-delete', [UserController::class, 'massiveDelete'])->name('users.massive-delete');


//products routes----------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------
Route::resource('products', ProductController::class)->middleware('auth');
Route::post('products/update-with-media/{product}', [ProductController::class, 'updateWithMedia'])->name('products.update-with-media')->middleware('auth');
Route::post('products/massive-delete', [ProductController::class, 'massiveDelete'])->name('products.massive-delete');
Route::get('products-search', [ProductController::class, 'searchProduct'])->name('products.search')->middleware('auth');


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


//measure unit routes----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('measure_units', MeasureUnitController::class)->middleware('auth');


//features routes----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('features', FeatureController::class)->middleware('auth');


//settings routes----------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------
Route::resource('settings', SettingController::class)->middleware('auth');
Route::put('role-permission/{role}/edit-role', [SettingController::class, 'updateRole'])->middleware('auth')->name('settings.role-permission.update-role');
Route::post('role-permission/store-role', [SettingController::class, 'storeRole'])->middleware('auth')->name('settings.role-permission.store-role');
Route::delete('role-permission/{role}/destroy-role', [SettingController::class, 'deleteRole'])->middleware('auth')->name('settings.role-permission.delete-role');
Route::put('role-permission/{permission}/edit-permission', [SettingController::class, 'updatePermission'])->middleware('auth')->name('settings.role-permission.update-permission');
Route::post('role-permission/store-permission', [SettingController::class, 'storePermission'])->middleware('auth')->name('settings.role-permission.store-permission');
Route::delete('role-permission/{permission}/destroy-permission', [SettingController::class, 'deletePermission'])->middleware('auth')->name('settings.role-permission.delete-permission');

<?php

use App\Http\Controllers\BioTimeBackfillController;
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
use App\Http\Controllers\PayrollExtraHoursController;
use App\Http\Controllers\PayrollUserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacationRequestController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

    Route::get('my-payrolls', [UserController::class, 'myPayrolls'])->name('my-payrolls');

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
    Route::post('users/inactivate/{user}', [UserController::class, 'inactivate'])->name('users.inactivate');
    Route::put('users/media/{media}/update-name', [UserController::class, 'updateMediaName'])->name('users.update-media-name');
    Route::put('users/{user}/update-vacations', [UserController::class, 'updateVacations'])->name('users.update-vacations');
    Route::put('users/{user}/toggle-home-office', [UserController::class, 'toggleHomeOffice'])->name('users.toggle-home-office');
    Route::get('users/reactivatation/{user}', [UserController::class, 'reactivation'])->name('users.reactivation');
    Route::get('users/{user}/performance', [UserController::class, 'getPerformance'])->name('users.get-performance');

    // Rutas de Ajustes Manuales de Vacaciones
    Route::post('users/{user}/vacation-adjustments', [UserController::class, 'storeVacationAdjustment'])->name('users.vacation-adjustments.store');
    Route::delete('users/{user}/vacation-adjustments/{adjustment}', [UserController::class, 'destroyVacationAdjustment'])->name('users.vacation-adjustments.destroy');

    Route::resource('departments', DepartmentController::class);
    Route::post('departments/{department}/reassign-and-delete', [DepartmentController::class, 'reassignAndDelete'])->name('departments.reassign-and-delete');
    Route::resource('features', FeatureController::class);
    Route::resource('job-positions', JobPositionController::class);

    // --- NÓMINAS (PAYROLLS) ---
    // Rutas personalizadas DEBEN ir antes del resource para evitar conflicto
    Route::get('payrolls/receipts-by-range', [PayrollController::class, 'receiptsByRange'])->name('payrolls.receipts-by-range');
    // Lista ligera de catorcenas por año y datos completos por catorcena (panel de tiempo extra)
    Route::get('payrolls/catorcenas', [PayrollController::class, 'catorcenas'])->name('payrolls.catorcenas');
    // Registros de tiempo extra pendiente por rango de fechas (a través de catorcenas)
    Route::get('payrolls/extra-time-by-range', [PayrollController::class, 'extraTimeByRange'])->name('payrolls.extra-time-by-range');
    Route::get('payrolls/{payroll}/extra-time-data', [PayrollController::class, 'extraTimeData'])->name('payrolls.extra-time-data');
    Route::get('payrolls/{payroll}/pre-payroll', [PayrollController::class, 'prePayrollTemplate'])->name('payrolls.pre-payroll');
    Route::get('payrolls/{payroll}/receipts', [PayrollController::class, 'receiptsTemplate'])->name('payrolls.receipts');
    Route::get('payrolls/{payroll}/extra-hours-config', [PayrollExtraHoursController::class, 'config'])->name('payrolls.extra-hours-config');
    Route::post('payrolls/{payroll}/extra-hours-costs', [PayrollExtraHoursController::class, 'saveCosts'])->name('payrolls.extra-hours-costs.save');
    Route::post('payrolls/{payroll}/extra-hours-groups', [PayrollExtraHoursController::class, 'saveApprovalGroups'])->name('payrolls.extra-hours-groups.save');
    Route::post('payrolls/{payroll}/extra-hours-copy', [PayrollExtraHoursController::class, 'copyFromPrevious'])->name('payrolls.extra-hours-copy');
    Route::post('payrolls/{payroll}/extra-hours-copy-next', [PayrollExtraHoursController::class, 'copyFromNext'])->name('payrolls.extra-hours-copy-next');
    // Decidir (aprobar/rechazar) en un nivel
    Route::post('payrolls/{payroll}/extra-hours-decide', [PayrollExtraHoursController::class, 'decide'])->name('payrolls.extra-hours-decide');
    Route::post('payrolls/{payroll}/extra-hours-decide-bulk', [PayrollExtraHoursController::class, 'decideBulk'])->name('payrolls.extra-hours-decide-bulk');
    // Revertir decisión
    Route::delete('payrolls/extra-hours-revert', [PayrollExtraHoursController::class, 'revertDecision'])->name('payrolls.extra-hours-revert');

    // Reporte de personal que trabaja fuera de las instalaciones de ADTI (debe ir ANTES del resource)
    Route::get('payrolls/external-work-report', [PayrollController::class, 'externalWorkReport'])->name('payrolls.external-work-report');

    Route::resource('payrolls', PayrollController::class)->only(['index', 'show']);

    Route::resource('payroll-comments', PayrollCommentController::class)->middleware('auth');

    // --- USUARIOS DE NÓMINA (PAYROLL USERS) ---
    Route::post('payroll-users/set-attendance', [PayrollUserController::class, 'store'])->name('payroll-users.set-attendance');
    Route::put('payroll-users/update-attendance', [PayrollUserController::class, 'update'])->name('payroll-users.update-attendance');
    Route::put('payroll-users/set-incidence', [PayrollUserController::class, 'setIncidence'])->name('payroll-users.set-incidence');
    Route::put('payroll-users/remove-late', [PayrollUserController::class, 'removeLate'])->name('payroll-users.remove-late');

    // --- RUTAS DE TIEMPO EXTRA ---
    Route::put('payroll-users/approve-extra-time', [PayrollUserController::class, 'approveExtraTime'])->name('payroll-users.approve-extra-time');
    Route::put('payroll-users/revert-extra-time', [PayrollUserController::class, 'revertExtraTime'])->name('payroll-users.revert-extra-time');
    Route::put('payroll-users/reject-extra-time', [PayrollUserController::class, 'rejectExtraTime'])->name('payroll-users.reject-extra-time');
    Route::get('payroll-users/recalculate-extra-time', [PayrollUserController::class, 'recalculateExtraTime'])->name('payroll-users.recalculate-extra-time');
    Route::put('payroll-users/clear-extra-time', [PayrollUserController::class, 'clearExtraTime'])->name('payroll-users.clear-extra-time');
    Route::put('payroll-users/set-project', [PayrollUserController::class, 'setProject'])->name('payroll-users.set-project');
    // Vinculación de MÚLTIPLES proyectos por día (interno/externo, departamento, tiempo extra por proyecto)
    Route::put('payroll-users/set-projects', [PayrollUserController::class, 'setProjects'])->name('payroll-users.set-projects');

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
    // Métricas globales (debe ir ANTES del resource para no chocar con projects/{project})
    Route::get('projects/extra-time/global-metrics', [ProjectController::class, 'globalExtraTimeMetrics'])->name('projects.extra-time.global-metrics');
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/start', [ProjectController::class, 'startWork'])->name('projects.start');
    Route::post('projects/{project}/pause', [ProjectController::class, 'togglePause'])->name('projects.pause');
    Route::post('projects/{project}/stop', [ProjectController::class, 'stopWork'])->name('projects.stop');
    Route::post('projects/add-time-entry', [ProjectController::class, 'addTimeEntry'])->name('projects.add-time-entry');
    Route::put('projects/{project}/status', [ProjectController::class, 'updateStatus'])->name('projects.update-status');
    // Métricas de costos y horas extra por proyecto
    Route::get('projects/{project}/extra-time-metrics', [ProjectController::class, 'extraTimeMetrics'])->name('projects.extra-time-metrics');

    // marcar tarea como terminada/pendiente
    Route::put('tasks/{task}/toggle-status', [ProjectController::class, 'toggleTaskStatus'])->name('tasks.toggle-status');

    // --- CATÁLOGO DE TAREAS ---
    Route::post('default-tasks', [ProjectController::class, 'storeDefaultTask'])->name('default-tasks.store');
    Route::delete('default-tasks/{default_task}', [ProjectController::class, 'destroyDefaultTask'])->name('default-tasks.destroy');
});

// Rutas de Solicitudes de Vacaciones
Route::get('vacation-requests', [VacationRequestController::class, 'index'])->name('vacation-requests.index');
Route::post('vacation-requests', [VacationRequestController::class, 'store'])->name('vacation-requests.store');
Route::put('vacation-requests/{vacationRequest}/cancel', [VacationRequestController::class, 'cancel'])->name('vacation-requests.cancel');
Route::put('vacation-requests/{vacationRequest}/approve', [VacationRequestController::class, 'approve'])->name('vacation-requests.approve');
Route::put('vacation-requests/{vacationRequest}/reject', [VacationRequestController::class, 'reject'])->name('vacation-requests.reject');
Route::get('vacation-requests/pending-count', [VacationRequestController::class, 'getPendingCount'])->name('vacation-requests.pending-count');

Route::get('products-search', [ProductController::class, 'searchProduct'])->name('products.search');
Route::get('products-fetch-subcategory-products/{subcategory_id}', [ProductController::class, 'fetchSubcategoryProducts'])->name('products.fetch-subcategory-products');

// Comandos de utilidad (Artisan)
// SEGURIDAD: estos comandos estaban declarados fuera del grupo autenticado (solo
// middleware `web`), asi que cualquiera con la URL podia dispararlos; entre ellos
// `extra-hours:fix-approved-decisions`, que degradaba aprobaciones ya cerradas.
// Ahora exigen sesion + permiso, y el comando destructivo fue sustituido por la
// reconciliacion NO destructiva (dry-run por defecto).
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
    $requireIncidencePermission = function () {
        if (!auth()->user()?->can('Ver incidencias')) {
            abort(403, 'No autorizado.');
        }
    };

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

    Route::get('/payrolls-close', function () use ($requireIncidencePermission) {
        $requireIncidencePermission();
        Artisan::call('payrolls:close');
        return 'Catorcena cerrada. La jerarquia de autorizacion de tiempo extra se arrastro a la nueva.';
    });

    // Auditoria (solo lectura) del estado de autorizacion del tiempo extra.
    Route::get('/extra-hours-audit', function () use ($requireIncidencePermission) {
        $requireIncidencePermission();
        Artisan::call('extra-hours:audit-states');
        return response(Artisan::output(), 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    });

    // Reconciliacion: sin ?apply=1 solo informa (dry-run).
    Route::get('/extra-hours-reconcile', function (\Illuminate\Http\Request $request) use ($requireIncidencePermission) {
        $requireIncidencePermission();
        Artisan::call('extra-hours:reconcile', ['--apply' => $request->boolean('apply')]);
        return response(Artisan::output(), 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    });
});

// Backfill: migra las vinculaciones existentes (payroll_user.project_id) hacia la
// tabla pivote payroll_user_project (cuando la tabla se crea a mano en producción vía SQL).
// Lógica equivalente al backfill de la migración 2026_09_02_000001_create_payroll_user_project_table.
Route::get('/backfill-payroll-user-project', function () {
    if (!auth()->user()?->can('Ver incidencias')) {
        abort(403, 'No autorizado.');
    }

    // Mapa de departamentos (nombre -> id) para resolver el departamento del empleado.
    $deptMap = DB::table('departments')->pluck('id', 'name');

    // Filas "legacy": registros de payroll_user que aún tienen un solo proyecto vinculado.
    $legacy = DB::table('payroll_user as pu')
        ->join('users as u', 'u.id', '=', 'pu.user_id')
        ->select([
            'pu.id as payroll_user_id',
            'pu.project_id',
            'pu.approved_extra_hours',
            'pu.approved_extra_minutes',
            'pu.extra_hours',
            'pu.extra_minutes',
            'u.org_props',
        ])
        ->whereNotNull('pu.project_id')
        ->get();

    [$inserted, $skipped] = DB::transaction(function () use ($legacy, $deptMap) {
        $inserted = 0;
        $skipped = 0;
        $now = now();

        foreach ($legacy as $row) {
            // Idempotente: si la vinculación ya existe en la pivote, se omite.
            $alreadyLinked = DB::table('payroll_user_project')
                ->where('payroll_user_id', $row->payroll_user_id)
                ->where('project_id', $row->project_id)
                ->exists();

            if ($alreadyLinked) {
                $skipped++;
                continue;
            }

            $orgProps = json_decode($row->org_props ?? '{}', true);
            $departmentName = $orgProps['department'] ?? null;

            DB::table('payroll_user_project')->insert([
                'payroll_user_id' => $row->payroll_user_id,
                'project_id' => $row->project_id,
                'department_id' => $departmentName ? ($deptMap[$departmentName] ?? null) : null,
                'work_type' => 'internal',
                // Tiempo extra: el aprobado, o el solicitado si aún no se aprueba.
                'extra_hours' => $row->approved_extra_hours ?? $row->extra_hours,
                'extra_minutes' => $row->approved_extra_minutes ?? $row->extra_minutes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $inserted++;
        }

        return [$inserted, $skipped];
    });

    return "Backfill payroll_user_project completado. Insertados: {$inserted}, omitidos (ya existentes): {$skipped}.";
});

// --- BACKFILL DE CHECADAS BIOTIME (recuperación de días sin registro) ---
// Reporte (dry-run):  /backfill-biotime?emp=63,64,65&from=2026-09-01&to=2026-09-21
// Aplicar cambios:    el mismo enlace + &apply=1&confirm=SI
// Protegido con BACKFILL_KEY del .env o con una sesión abierta en el ERP.
Route::get('/backfill-biotime', [BioTimeBackfillController::class, 'run']);

// --- OTROS / API ---
Route::get('/api/process-transaction/{time}/{emp_code}', [PayrollUserController::class, 'processBioTimeTransaction']);
Route::get('/api/get-total-processed-count/', [BioTimeTransactionsController::class, 'getTotalProcessedCount']);
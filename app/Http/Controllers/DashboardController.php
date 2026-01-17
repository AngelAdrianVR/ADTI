<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Project;
use App\Models\TimeEntry; // Asegúrate de importar el modelo TimeEntry
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        // --- Lógica existente de Productos/Categorías ---
        $categories = Category::with(['subcategories:id,name,category_id' => ['products:id,name,subcategory_id']])->get();
        $total_products = Product::count();

        // --- Nueva Lógica de Proyectos ---
        
        $active_projects_count = Project::where('status', 'active')->count();

        // CORRECCIÓN 1: Calcular horas totales. 
        // No podemos sumar una columna que no existe. Sumamos la duración desde la tabla 'time_entries'.
        // Asumiendo que TimeEntry está en App\Models\TimeEntry. 
        // Si quieres solo de proyectos activos, la consulta sería un poco diferente, pero esto da el total global.
        $total_seconds = TimeEntry::sum('total_duration_seconds');
        $total_hours_consumed = round($total_seconds / 3600, 1);
        
        // Obtener los 5 proyectos activos más recientes
        $active_projects = Project::where('status', 'active')
            ->latest('start_date')
            ->take(5)
            ->get()
            // CORRECCIÓN 2: Adjuntar el accessor 'consumed_hours' para que Vue lo vea
            ->append('consumed_hours');

        return Inertia::render('Dashboard', [
            'categories' => $categories,
            'total_products' => $total_products,
            'active_projects_count' => $active_projects_count,
            'total_hours_consumed' => $total_hours_consumed,
            'active_projects' => $active_projects,
        ]);
    }
}
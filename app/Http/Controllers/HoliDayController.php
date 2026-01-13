<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Carbon\Carbon;

class HolidayController extends Controller
{
    public function index()
    {   
        $holidays = Holiday::all();

        return inertia('Holiday/Index', compact('holidays'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'day' => $request->is_custom_date ? 'nullable' : 'required|numeric|min:1',
            'month' => 'required',
            'ordinal' => $request->is_custom_date ? 'required' : 'nullable|string|max:20',
            'week_day' => $request->is_custom_date ? 'required' : 'nullable|string|max:20',
            'is_custom_date' => 'boolean',
            'is_active' => 'boolean',
        ]);

        // Calcular la fecha exacta para el año en curso
        $date = $this->calculateDate(
            $request->is_custom_date,
            now()->year,
            $request->month,
            $request->day,
            $request->ordinal,
            $request->week_day
        );

        Holiday::create([
            'name' => $request->name,
            'date' => $date, // Ahora siempre se guardará una fecha válida (YYYY-MM-DD)
            'ordinal' => $request->ordinal,
            'week_day' => $request->week_day,
            'month' => $request->month,
            'is_custom_date' => $request->is_custom_date,
            'is_active' => $request->is_active,
        ]);

        // return back(); // Inertia lo maneja automáticamente
    }

    public function show(Holiday $holiday)
    {
        //
    }

    public function edit(Holiday $holiday)
    {
        //
    }

    public function update(Request $request, Holiday $holiday)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'day' => $request->is_custom_date ? 'nullable' : 'required|numeric|min:1',
            'month' => 'required',
            'ordinal' => $request->is_custom_date ? 'required' : 'nullable|string|max:20',
            'week_day' => $request->is_custom_date ? 'required' : 'nullable|string|max:20',
            'is_custom_date' => 'boolean',
        ]);

        // Recalcular la fecha exacta para el año en curso
        $date = $this->calculateDate(
            $request->is_custom_date,
            now()->year,
            $request->month,
            $request->day,
            $request->ordinal,
            $request->week_day
        );

        $holiday->update([
            'name' => $request->name,
            'date' => $date, // Actualizamos la fecha calculada
            'ordinal' => $request->ordinal,
            'week_day' => $request->week_day,
            'month' => $request->month,
            'is_custom_date' => $request->is_custom_date,
            'is_active' => $request->is_active,
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->items_ids as $id) {
            $item = Holiday::find($id);
            $item?->delete();
        }
    }

    /**
     * Calcula la fecha exacta basada en reglas fijas o dinámicas.
     */
    private function calculateDate($is_custom, $year, $month, $day = null, $ordinal = null, $week_day = null)
    {
        // Caso 1: Fecha Fija (ej. 25 de Diciembre)
        if (!$is_custom) {
            return Carbon::createFromDate($year, $month, $day)->toDateString();
        }

        // Caso 2: Fecha Dinámica (ej. Tercer Lunes de Noviembre)
        $date = Carbon::create($year, $month, 1); // Empezamos el día 1 del mes

        if ((int)$ordinal === 5) { // 5 representa "Último" en tu catálogo
            $date->endOfMonth();
            // Si el último día del mes no es el día de la semana deseado, retrocedemos
            if ($date->dayOfWeek !== (int)$week_day) {
                $date->previous((int)$week_day);
            }
        } else {
            // Para "Primero", "Segundo", "Tercero", "Cuarto"
            $date->startOfMonth();
            
            // Si el día 1 no es el día de la semana deseado, avanzamos al siguiente
            if ($date->dayOfWeek !== (int)$week_day) {
                $date->next((int)$week_day);
            }
            
            // Sumamos semanas según el ordinal (1=0 semanas, 2=1 semana, etc.)
            $date->addWeeks((int)$ordinal - 1);
        }

        return $date->toDateString();
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use Illuminate\Http\Request;

class BioTimeTransactionsController extends Controller
{
    /**
     * Obtiene el conteo total de las transacciones procesadas.
     * Si se envían fechas, cuenta solo las de esa ventana de tiempo.
     */
    public function getTotalProcessedCount(Request $request)
    {
        $query = BioTimeTransactions::query();

        // Si el script de Python envía un rango de fechas (Ventana Deslizante)
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Suma la columna 'quantity' de los registros filtrados
        $total_count = $query->sum('quantity');
        
        return response()->json(['transactions' => $total_count]);
    }
}
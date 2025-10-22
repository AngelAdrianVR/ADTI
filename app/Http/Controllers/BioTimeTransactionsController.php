<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use Illuminate\Http\Request;

class BioTimeTransactionsController extends Controller
{
    /**
     * Obtiene el conteo total de TODAS las transacciones procesadas.
     * El script de Python usa esto como "cursor" para saber cuántos
     * registros de BioTime ya ha procesado.
     */
    public function getTotalProcessedCount()
    {
        // Suma la columna 'quantity' de todos los registros en la tabla
        $total_count = BioTimeTransactions::sum('quantity');
        
        // Devuelve el conteo total. El script de Python espera la clave 'transactions'.
        return response()->json(['transactions' => $total_count]);
    }
}
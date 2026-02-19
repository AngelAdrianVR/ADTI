<?php

namespace App\Http\Controllers;

use App\Models\PayrollComment;
use Illuminate\Http\Request;

class PayrollCommentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'comments' => 'required|string|max:1200',
            'user_id' => 'required|exists:users,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'date' => 'nullable|date',
        ]);

        // Usamos updateOrCreate para evitar duplicados en la misma fecha/usuario/nómina
        PayrollComment::updateOrCreate(
            [
                'payroll_id' => $request->payroll_id,
                'user_id' => $request->user_id,
                'date' => $request->date, // Puede ser null para comentarios generales
            ],
            [
                'comments' => $request->comments
            ]
        );
    }

    // El método update original ya no es estrictamente necesario si usas store con updateOrCreate,
    // pero lo dejamos por compatibilidad si se llama directamente.
    public function update(Request $request, PayrollComment $payrollComment)
    {
        $request->validate([
            'comments' => 'required|string|max:1200'
        ]);

        $payrollComment->update([
            'comments' => $request->comments,
        ]);
    }

    public function destroy(PayrollComment $payrollComment)
    {
        $payrollComment->delete();
    }
}
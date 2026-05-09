<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VacationRequestController extends Controller
{
    // Crear una nueva solicitud (Empleado)
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_requested' => 'required|integer|min:1',
            'employee_notes' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        // 1. Validar Antelación (Mínimo 15 días)
        $startDate = Carbon::parse($request->start_date);
        if (now()->diffInDays($startDate) < 15 && $startDate->isAfter(now())) {
            return back()->withErrors(['start_date' => 'Las solicitudes deben realizarse con al menos 15 días de anticipación.']);
        }

        // 2. Validar Saldo Disponible
        $currentBalance = $user->org_props['vacations'] ?? 0;

        // También debemos restar los días que ya tiene "Pendientes" o "Aprobados" a futuro
        $lockedDays = VacationRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pendiente', 'Aprobada'])
            ->where('start_date', '>=', now()->toDateString())
            ->sum('days_requested');

        $realAvailable = $currentBalance - $lockedDays;

        if ($request->days_requested > $realAvailable) {
            return back()->withErrors(['days_requested' => 'No tienes saldo suficiente. Saldo real disponible: ' . floor($realAvailable) . ' días.']);
        }

        // 3. Crear la Solicitud
        VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'days_requested' => $request->days_requested,
            'employee_notes' => $request->employee_notes,
            'status' => 'Pendiente',
        ]);

        return back()->with('success', 'Solicitud de vacaciones enviada correctamente.');
    }

    // Cancelar una solicitud propia (Empleado)
    public function cancel(VacationRequest $vacationRequest)
    {
        // Solo puede cancelar si es suya y está Pendiente
        if ($vacationRequest->user_id !== auth()->id() || $vacationRequest->status !== 'Pendiente') {
            abort(403, 'No puedes cancelar esta solicitud.');
        }

        $vacationRequest->update(['status' => 'Cancelada']);

        return back()->with('success', 'Solicitud cancelada.');
    }

    // Aprobar solicitud (Administrador/RH)
    public function approve(Request $request, VacationRequest $vacationRequest)
    {
        $request->validate([
            'reviewer_notes' => 'nullable|string|max:500',
        ]);

        $vacationRequest->update([
            'status' => 'Aprobada',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'reviewer_notes' => $request->reviewer_notes,
        ]);

        // Nota: Aquí se podría integrar la creación automática en payroll_user
        // si se desea que los días se inserten de inmediato en las incidencias.

        return back()->with('success', 'Solicitud aprobada.');
    }

    // Rechazar solicitud (Administrador/RH)
    public function reject(Request $request, VacationRequest $vacationRequest)
    {
        $request->validate([
            'reviewer_notes' => 'required|string|max:500', // Obligatorio justificar el rechazo
        ]);

        $vacationRequest->update([
            'status' => 'Rechazada',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'reviewer_notes' => $request->reviewer_notes,
        ]);

        return back()->with('success', 'Solicitud rechazada.');
    }
}

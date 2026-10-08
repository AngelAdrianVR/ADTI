<?php

/**
 * Prueba de simulación: Registro de asistencia en fines de semana
 * 
 * Simula el flujo de BioTime para un empleado que trabaja en sábado
 * y verifica que la salida NO se registre como entrada.
 * 
 * Ejecutar: php tests/Manual/WeekendAttendanceSimulation.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PayrollUser;
use App\Models\User;
use App\Models\Payroll;
use Carbon\Carbon;

echo "═══════════════════════════════════════════════════════\n";
echo "  SIMULACIÓN: Registro de asistencia en fin de semana\n";
echo "═══════════════════════════════════════════════════════\n\n";

// ─── Preparación ───────────────────────────────────────
// Buscar un empleado activo con código, turno y nómina activa
$employee = User::where('is_active', true)
    ->whereNotNull('code')
    ->has('payrolls')
    ->first();

if (!$employee) {
    echo "❌ ERROR: No se encontró un empleado activo con código y nómina.\n";
    echo "   Asegúrate de tener al menos un empleado activo con código asignado.\n";
    exit(1);
}

$activePayroll = Payroll::firstWhere('is_active', true);
if (!$activePayroll) {
    echo "❌ ERROR: No hay una nómina activa.\n";
    exit(1);
}

echo "👤 Empleado:       {$employee->name} (Código: {$employee->code})\n";
echo "📋 Nómina activa:  #{$activePayroll->id}\n";
echo "🕐 Turno:          " . ($employee->org_props['work_shift'] ?? 'Turno 3 (09:00 - 18:00)') . "\n\n";

// ─── Simular sábado y domingo ──────────────────────────
$testDates = [
    Carbon::now()->next(Carbon::SATURDAY)->toDateString(),
    Carbon::now()->next(Carbon::SUNDAY)->toDateString(),
];

foreach ($testDates as $testDate) {
    $dayName = Carbon::parse($testDate)->isoFormat('dddd');
    
    echo "─────────────────────────────────────────────────────\n";
    echo "  📅 Simulando: $dayName ($testDate)\n";
    echo "─────────────────────────────────────────────────────\n\n";

    // Limpiar registros previos de este día para la simulación
    PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->delete();

    $controller = new \App\Http\Controllers\PayrollUserController();

    // ─── ESCENARIO A: Entrada + Salida (sin comida) ────
    echo "  ╔══════════════════════════════════════════════╗\n";
    echo "  ║  ESCENARIO A: Entrada + Salida sin comida    ║\n";
    echo "  ╚══════════════════════════════════════════════╝\n\n";

    $entryTime = "$testDate 07:00:00";
    echo "  🔵 [A1] Entrada: $entryTime\n";
    $controller->processBioTimeTransaction($entryTime, $employee->code);
    $record = PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->first();
    $checkInOk = $record && $record->check_in === '07:00';
    echo $checkInOk
        ? "     ✅ check_in = {$record->check_in}\n"
        : "     ❌ ERROR: check_in = " . ($record->check_in ?? 'NULL') . "\n";

    $exitTime = "$testDate 14:30:00";
    echo "\n  🔴 [A2] Salida: $exitTime\n";
    $controller->processBioTimeTransaction($exitTime, $employee->code);
    $record->refresh();

    $checkOutOk = $record->check_out === '14:30';
    $breakOk = is_null($record->break_start) || !is_null($record->break_end);
    $allOkA = $checkInOk && $checkOutOk && $breakOk;
    echo $allOkA
        ? "     ✅ check_out = {$record->check_out}, extra = {$record->extra_hours}h {$record->extra_minutes}m\n"
        : "     ❌ check_out = {$record->check_out}, break_start = " . ($record->break_start ?? 'NULL') . "\n";

    // ─── ESCENARIO B: Entrada + Comida + Regreso + Salida ────
    echo "\n  ╔══════════════════════════════════════════════╗\n";
    echo "  ║  ESCENARIO B: Con pausa para comer          ║\n";
    echo "  ╚══════════════════════════════════════════════╝\n\n";

    // Limpiar para escenario B
    PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->delete();

    $entryTime = "$testDate 07:00:00";
    echo "  🔵 [B1] Entrada: $entryTime\n";
    $controller->processBioTimeTransaction($entryTime, $employee->code);
    $record = PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->first();
    echo "     ✅ check_in = {$record->check_in}\n";

    $lunchStart = "$testDate 12:00:00";
    echo "\n  🟡 [B2] Salida a comer: $lunchStart\n";
    $controller->processBioTimeTransaction($lunchStart, $employee->code);
    $record->refresh();
    $lunchOut = $record->check_out === '12:00';
    echo $lunchOut
        ? "     ✅ check_out = {$record->check_out}\n"
        : "     ⚠️ check_out = {$record->check_out} (esperado 12:00)\n";

    $lunchReturn = "$testDate 12:45:00";
    echo "\n  🟢 [B3] Regreso de comer: $lunchReturn\n";
    $controller->processBioTimeTransaction($lunchReturn, $employee->code);
    $record->refresh();
    $reopened = is_null($record->check_out);
    echo $reopened
        ? "     ✅ Turno reabierto (check_out = NULL)\n"
        : "     ❌ check_out = {$record->check_out} (DEBERÍA SER NULL)\n";

    $finalExit = "$testDate 16:00:00";
    echo "\n  🔴 [B4] Salida final: $finalExit\n";
    $controller->processBioTimeTransaction($finalExit, $employee->code);
    $record->refresh();
    $finalOutOk = $record->check_out === '16:00';
    echo $finalOutOk
        ? "     ✅ check_out = {$record->check_out}, extra = {$record->extra_hours}h {$record->extra_minutes}m\n"
        : "     ❌ check_out = {$record->check_out} (esperado 16:00)\n";

    $allOkB = $lunchOut && $reopened && $finalOutOk;

    // ─── RESUMEN FINAL ─────────────────────────────────
    echo "\n  ┌─────────────────────────────────────────────┐\n";
    echo "  │  RESUMEN: $dayName                              \n";
    echo "  ├─────────────────────────────────────────────┤\n";
    echo "  │  Escenario A (sin comida):  " . ($allOkA ? '✅ CORRECTO' : '❌ FALLÓ') . "       \n";
    echo "  │  Escenario B (con comida):  " . ($allOkB ? '✅ CORRECTO' : '❌ FALLÓ') . "       \n";
    echo "  └─────────────────────────────────────────────┘\n\n";

    if ($allOkA && $allOkB) {
        echo "  🎉 $dayName: Ambos escenarios correctos.\n\n";
    } else {
        echo "  ❌ $dayName: Hay escenarios fallidos.\n\n";
        if (!$lunchOut) echo "     - La salida a comer no se registró correctamente\n";
        if (!$reopened) echo "     - El regreso de comer NO reabrió el turno (BUG)\n";
        if (!$finalOutOk) echo "     - La salida final no se registró correctamente\n";
    }

    // Limpiar después de la prueba
    PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->delete();
}

echo "═══════════════════════════════════════════════════════\n";
echo "  SIMULACIÓN COMPLETADA\n";
echo "═══════════════════════════════════════════════════════\n";

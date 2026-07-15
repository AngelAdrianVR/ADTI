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

    // ─── PASO 1: Entrada a las 07:00 ──────────────────
    $entryTime = "$testDate 07:00:00";
    echo "  🔵 [PASO 1] Entrada: $entryTime\n";
    
    $controller = new \App\Http\Controllers\PayrollUserController();
    $controller->processBioTimeTransaction($entryTime, $employee->code);

    $record = PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->first();

    $checkInOk = $record && $record->check_in === '07:00';
    echo $checkInOk
        ? "     ✅ check_in registrado: {$record->check_in}\n"
        : "     ❌ ERROR: check_in = " . ($record->check_in ?? 'NULL') . " (esperado: 07:00)\n";

    // ─── PASO 2: Salida real a las 14:30 (SIN punch intermedio) ──
    // Caso más común: empleado entra y sale sin eventos intermedios
    $exitTime = "$testDate 14:30:00";
    echo "\n  🔴 [PASO 2] Salida real: $exitTime (sin punches intermedios)\n";
    $controller->processBioTimeTransaction($exitTime, $employee->code);
    $record->refresh();

    // ─── VERIFICACIONES ────────────────────────────────
    echo "\n  ┌─────────────────────────────────────────────┐\n";
    echo "  │  RESULTADOS                                 │\n";
    echo "  ├─────────────────────────────────────────────┤\n";

    // Verificación 1: check_in debe seguir siendo 07:00
    $checkInPreserved = $record->check_in === '07:00';
    echo "  │  check_in:     {$record->check_in}" . 
         ($checkInPreserved ? '' : ' ❌ (ESPERADO: 07:00)') . "\n";

    // Verificación 2: check_out debe ser 14:30 (NO null)
    $checkOutCorrect = $record->check_out === '14:30';
    echo "  │  check_out:    {$record->check_out}" . 
         ($checkOutCorrect ? '' : ' ❌ (ESPERADO: 14:30)') . "\n";

    // Verificación 3: break debe estar cerrado o no existir
    $breakOk = is_null($record->break_start) || 
               (!is_null($record->break_start) && !is_null($record->break_end));
    echo "  │  break_start:  " . ($record->break_start ?? 'NULL') . "\n";
    echo "  │  break_end:    " . ($record->break_end ?? 'NULL') . 
         ($breakOk ? '' : ' ❌ (PAUSA COLGADA!)') . "\n";

    // Verificación 4: Horas extra calculadas
    $hasExtraTime = ($record->extra_hours > 0 || $record->extra_minutes > 0);
    echo "  │  extra:        {$record->extra_hours}h {$record->extra_minutes}m" . 
         ($hasExtraTime ? '' : ' ⚠️ (NO CALCULADO)') . "\n";

    // Verificación 5: ¿La salida se registró como entrada?
    $salidaEsEntrada = is_null($record->check_out);
    echo "  │  ¿Salida = Entrada?: " . ($salidaEsEntrada ? '❌ SÍ (ERROR!)' : '✅ NO') . "\n";

    echo "  └─────────────────────────────────────────────┘\n\n";

    // ─── Resumen del día ────────────────────────────────
    $allOk = $checkInPreserved && $checkOutCorrect && $breakOk && !$salidaEsEntrada;
    
    if ($allOk) {
        echo "\n  🎉 RESULTADO: $dayName - ¡CORRECTO!\n";
        echo "     ✅ Entrada y salida registradas correctamente\n";
        echo "     ✅ check_in = {$record->check_in}, check_out = {$record->check_out}\n";
        echo "     ✅ Sin pausas colgadas\n";
    } else {
        echo "\n  ❌ RESULTADO: $dayName - ¡FALLÓ!\n";
        
        if (!$checkInPreserved) {
            echo "     ❌ check_in fue modificado o perdido\n";
        }
        if ($salidaEsEntrada) {
            echo "     ❌ LA SALIDA SE REGISTRÓ COMO ENTRADA (check_out = NULL)\n";
        }
        if (!$checkOutCorrect && !$salidaEsEntrada) {
            echo "     ❌ check_out = {$record->check_out} (esperado: 15:01)\n";
        }
        if (!$breakOk) {
            echo "     ❌ Pausa de comida colgada (break_start sin break_end)\n";
        }
        echo "\n";
    }

    // Limpiar después de la prueba
    PayrollUser::where('user_id', $employee->id)
        ->whereDate('date', $testDate)
        ->delete();
}

echo "═══════════════════════════════════════════════════════\n";
echo "  SIMULACIÓN COMPLETADA\n";
echo "═══════════════════════════════════════════════════════\n";

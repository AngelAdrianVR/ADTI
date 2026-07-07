<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        Department::create($validated);
    }

    public function show(Department $department)
    {
        //
    }

    public function edit(Department $department)
    {
        //
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $department->update($validated);
    }

    public function destroy(Department $department)
    {
        $taskCount = $department->tasks()->count();

        if ($taskCount > 0) {
            // No eliminar aún: devolver datos para mostrar el modal de reasignación
            return back()->with('reassignData', [
                'departmentId'   => $department->id,
                'departmentName' => $department->name,
                'taskCount'      => $taskCount,
                'otherDepartments' => Department::where('id', '!=', $department->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->toArray(),
            ]);
        }

        $department->delete();

        return back()->with('success', 'Departamento eliminado correctamente.');
    }

    public function reassignAndDelete(Request $request, Department $department)
    {
        $validated = $request->validate([
            'new_department_id' => 'required|exists:departments,id',
        ]);

        if ($validated['new_department_id'] == $department->id) {
            return back()->with('error', 'No puedes reasignar las tareas al mismo departamento que deseas eliminar.');
        }

        DB::transaction(function () use ($department, $validated) {
            // Reasignar todas las tareas al nuevo departamento
            $department->tasks()->update([
                'department_id' => $validated['new_department_id'],
            ]);

            // Eliminar el departamento
            $department->delete();
        });

        $newDepartment = Department::find($validated['new_department_id']);

        return back()->with('success', "Departamento eliminado. Las tareas fueron reasignadas a \"{$newDepartment->name}\".");
    }
}

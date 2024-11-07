<?php

namespace App\Http\Controllers;

use App\Models\HoliDay;
use Illuminate\Http\Request;

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
            'day' => $request->is_custom_date ? 'nullable' : 'required' . '|numeric|min:1',
            'month' => 'required',
            'ordinal' => $request->is_custom_date ? 'required' : 'nullable' . '|string|max:20',
            'week_day' => $request->is_custom_date ? 'required' : 'nullable' . '|string|max:20',
            'is_custom_date' => 'boolean',
            'is_active' => 'boolean',
        ]);

        Holiday::create([
            'name' => $request->name,
            'date' => $request->is_custom_date ? null :"2025-$request->month-$request->day",
            'ordinal' => $request->ordinal,
            'week_day' => $request->week_day,
            'month' => $request->month,
            'is_custom_date' => $request->is_custom_date,
            'is_active' => $request->is_active,
        ]);

        return to_route('holidays.index');
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
            'day' => $request->is_custom_date ? 'nullable' : 'required' . '|numeric|min:1',
            'month' => 'required',
            'ordinal' => $request->is_custom_date ? 'required' : 'nullable' . '|string|max:20',
            'week_day' => $request->is_custom_date ? 'required' : 'nullable' . '|string|max:20',
            'is_custom_date' => 'boolean',
        ]);

        $holiday->update([
            'name' => $request->name,
            'date' => $request->is_custom_date ? null :"2025-$request->month-$request->day",
            'ordinal' => $request->ordinal,
            'week_day' => $request->week_day,
            'month' => $request->month,
            'is_custom_date' => $request->is_custom_date,
            'is_active' => $request->is_active,
        ]);

        return to_route('holidays.index');
    }

    public function destroy(Holiday $holiday)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->holidays as $holiday) {
            $holiday = Holiday::find($holiday['id']);
            $holiday?->delete();
        }

        return response()->json(['message' => 'Dia(s) eliminado(s)']);
    }
}

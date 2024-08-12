<?php

namespace App\Http\Controllers;

use App\Models\MeasureUnit;
use Illuminate\Http\Request;

class MeasureUnitController extends Controller
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
        $request->validate([
            'name' => 'required|string|max:100|unique:measure_units',
            'abreviation' => 'required|string|max:10',
        ]);

        MeasureUnit::create($request->all());
    }

    public function show(MeasureUnit $measureUnit)
    {
        //
    }

    public function edit(MeasureUnit $measureUnit)
    {
        //
    }

    public function update(Request $request, MeasureUnit $measureUnit)
    {
        //
    }

    public function destroy(MeasureUnit $measureUnit)
    {
        //
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\JobPosition;
use Illuminate\Http\Request;

class JobPositionController extends Controller
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

        JobPosition::create($validated);
    }

    public function show(JobPosition $jobPosition)
    {
        //
    }

    public function edit(JobPosition $jobPosition)
    {
        //
    }

    public function update(Request $request, JobPosition $jobPosition)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $jobPosition->update($validated);
    }

    public function destroy(JobPosition $jobPosition)
    {
        $jobPosition->delete();
    }
}

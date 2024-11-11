<?php

namespace App\Http\Controllers;

use App\Models\PayrollComment;
use Illuminate\Http\Request;

class PayrollCommentController extends Controller
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
            'comments' => 'required|string|max:1200'
        ]);

        PayrollComment::create($request->all());
    }

    public function show(PayrollComment $payrollComment)
    {
        //
    }

    public function edit(PayrollComment $payrollComment)
    {
        //
    }

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

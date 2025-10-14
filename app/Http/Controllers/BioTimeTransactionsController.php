<?php

namespace App\Http\Controllers;

use App\Models\BioTimeTransactions;
use Illuminate\Http\Request;

class BioTimeTransactionsController extends Controller
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
        //
    }

    public function show(BioTimeTransactions $bioTimeTransactions)
    {
        //
    }

    public function edit(BioTimeTransactions $bioTimeTransactions)
    {
        //
    }

    public function update(Request $request, BioTimeTransactions $bioTimeTransactions)
    {
        //
    }

    public function destroy(BioTimeTransactions $bioTimeTransactions)
    {
        //
    }

    public function getTodaysTransactions()
    {
        $todaysTransactions = BioTimeTransactions::firstOrCreate(
            ['date' => today()->toDateString()],
        );

        return response()->json(['transactions' => $todaysTransactions->quantity ?? 0]);
    }
}

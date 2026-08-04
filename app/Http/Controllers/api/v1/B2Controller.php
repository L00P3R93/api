<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\B2C;
use Illuminate\Http\Request;

class B2Controller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'data' => B2C::query()->latest()->first()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(B2C $b2C)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(B2C $b2C)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, B2C $b2C)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(B2C $b2C)
    {
        //
    }
}

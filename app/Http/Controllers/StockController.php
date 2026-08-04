<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        $stocks = Stock::with('user')->get();
        $remShares = Stock::getRemainingShares();
        return view('stocks.list', compact('stocks', 'remShares'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        $users = User::where('role_id', [1,2,3])->get();
        return view('stocks.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStockRequest $request) {
        try {
            $remainingShares = Stock::getRemainingShares();
            if($request->amount > $remainingShares) return back()->withInput()->with('error', 'Not enough shares');
            Stock::create($request->validated());
            return to_route('shares')->with('success', 'Stock created successfully');
        } catch (\Exception $e) {
            Log::error('Create Stock Error: ', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $stock = Stock::find($encryptedIdentifier);
        if(!$stock) return back()->with('error', 'Stock not found');
        return view('stocks.view', compact('stock'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($encryptedIdentifier) {
        $users = User::where('role_id', [1,2,3])->get();
        $stock = Stock::find($encryptedIdentifier);
        if(!$stock) return back()->with('error', 'Stock not found');
        return view('stocks.edit', compact('stock', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStockRequest $request, $encryptedIdentifier) {
        try {
            $stock = Stock::find($encryptedIdentifier);
            if(!$stock) return back()->with('error', 'Stock not found');
            $remainingShares = Stock::getRemainingShares();
            if($request->amount > $remainingShares and $request->amount != $stock->amount) return back()->withInput()->with('error', 'Not enough shares');
            $stock->update($request->validated());
            return to_route('shares')->with('success', 'Stock updated successfully');
        } catch (\Exception $e) {
            Log::error('Update Stock Error: ', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        try {
            $stock = Stock::find($encryptedIdentifier);
            if(!$stock) return back()->with('error', 'Stock not found');
            $stock->delete();
            return to_route('shares')->with('success', 'Stock deleted successfully');
        } catch (\Exception $e) {
            Log::error('Delete Stock Error: ', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}

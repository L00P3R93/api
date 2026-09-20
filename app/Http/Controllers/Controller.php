<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Identify the API key behind a request, for audit trails.
     */
    protected function actorFor(Request $request): ?string
    {
        $key = $request->header('X-API-KEY');
        $id = $key ? ApiKey::where('key', $key)->value('id') : null;

        return $id ? "api_key:{$id}" : null;
    }
}

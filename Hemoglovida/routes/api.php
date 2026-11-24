<?php

use App\Http\Controllers\Api\V1\AgendamentoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::apiResource('agendamentos', AgendamentoController::class);
});

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionCategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResources([
        'wallets' => WalletController::class,
        'transaction-types' => TransactionTypeController::class,
        'transaction-categories' => TransactionCategoryController::class,
    ]);

    Route::apiResource('transactions', TransactionController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::apiResource('transfers', TransferController::class)
        ->only(['index', 'store', 'show', 'destroy']);
});

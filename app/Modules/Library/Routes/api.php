<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Library\Controllers\AuthController;
use App\Modules\Library\Controllers\BorrowTransactionController;
use App\Modules\Library\Controllers\FinesController;

use App\Modules\Library\Controllers\BooksController;

Route::prefix('library')->group(function () {

    // Student login & register
    Route::post('/login', [AuthController::class, 'login']);
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        //Book routes
        Route::get('/books', [BooksController::class, 'index']);
        Route::get('/books/{id}', [BooksController::class, 'show']);
        Route::post('/books', [BooksController::class, 'store']);
        Route::put('/books/{id}', [BooksController::class, 'update']);
        Route::delete('/books/{id}', [BooksController::class, 'destroy']);

        //BorrowTransaction routes
        Route::post('/borrow', [BorrowTransactionController::class, 'borrow']);
        Route::post('/return/{transactionId}', [BorrowTransactionController::class, 'return']);
        Route::get('/transactions/{memberId}', [BorrowTransactionController::class, 'memberTransactions']);

        //Fine routes
        Route::post('/fines', [FinesController::class, 'create']);
        // Pay a fine (cashier passes amount)
        Route::post('/fines/pay/{fineId}', [FinesController::class, 'pay']);
        Route::get('/fines/member/{memberId}', [FinesController::class, 'memberFines']);
        Route::get('/fines/unpaid', [FinesController::class, 'unpaidFines']);
    });
});

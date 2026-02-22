<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Library\Controllers\AuthController;
use App\Modules\Library\Controllers\BorrowTransactionController;
use App\Modules\Library\Controllers\FinesController;
use App\Modules\Library\Controllers\BooksController;
use App\Modules\Library\Controllers\LibraryStaffController;

Route::prefix('library')->group(function () {

    // Auth (Student login)
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes (requires token)
    Route::middleware('auth:sanctum')->group(function () {

        // Book routes
        Route::get('/books', [BooksController::class, 'index']);
        Route::get('/books/{id}', [BooksController::class, 'show']);
        Route::post('/books', [BooksController::class, 'store']);
        Route::put('/books/{id}', [BooksController::class, 'update']);
        Route::delete('/books/{id}', [BooksController::class, 'destroy']);

        // Borrow & Return routes
        Route::post('/borrow', [BorrowTransactionController::class, 'borrow']);
        Route::post('/return/{transactionId}', [BorrowTransactionController::class, 'returnBook']);
        Route::get('/transactions/{memberId}', [BorrowTransactionController::class, 'memberTransactions']);

        // Fines routes
        Route::get('/fines/student/{studentId}', [FinesController::class, 'studentFines']);
        Route::get('/fines/choice', [FinesController::class, 'finesChoice']); 
        Route::post('/fines/pay/{fineId}', [FinesController::class, 'payFine']);
        Route::get('/fines/member/{memberId}', [FinesController::class, 'memberFines']);
        Route::get('/fines/unpaid', [FinesController::class, 'unpaidFines']);

        // Library Staff routes (optional)
        Route::get('/staff', [LibraryStaffController::class, 'index']); 
        Route::get('/staff/{id}', [LibraryStaffController::class, 'show']);
        Route::post('/staff', [LibraryStaffController::class, 'store']); 
        Route::put('/staff/{id}', [LibraryStaffController::class, 'update']);
        Route::delete('/staff/{id}', [LibraryStaffController::class, 'destroy']);
    });

    //Staff login 
    Route::post('/staff/login', [LibraryStaffController::class, 'login']);
});
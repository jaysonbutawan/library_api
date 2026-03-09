<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Library\Controllers\AuthController;
use App\Modules\Library\Controllers\BorrowTransactionController;
use App\Modules\Library\Controllers\FinesController;
use App\Modules\Library\Controllers\BooksController;
use App\Modules\Library\Controllers\LibraryStaffController;
use App\Modules\Library\Controllers\ClearanceController;
use App\Modules\Library\Controllers\StaffAuthController;

Route::prefix('library')->group(function () {

    Route::post('/login', [AuthController::class, 'login'])->name('student.login');
    Route::post('/staff/login', [StaffAuthController::class, 'login'])->name('staff.login');

    Route::middleware('auth:sanctum','api')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile'])->name('student.profile');
        Route::post('/logout', [AuthController::class, 'logout'])->name('student.logout');

        Route::prefix('books')->name('books.')->group(function () {
            Route::get('/{id?}', [BooksController::class, 'index'])->name('index');
            Route::post('/', [BooksController::class, 'store'])->name('store');
            Route::put('/{id}', [BooksController::class, 'update'])->name('update');
            Route::delete('/{id}', [BooksController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::post('/borrow', [BorrowTransactionController::class, 'borrow'])->name('borrow');
            Route::post('/{transactionId}', [BorrowTransactionController::class, 'returnBook'])->name('returnBook');
            Route::get('/{memberId}', [BorrowTransactionController::class, 'getBorrowTransactionsByMemberId'])->name('getBorrowTransactionsByMemberId');
        });

        Route::prefix('fines')->name('fines.')->group(function () {
            Route::get('/choice', [FinesController::class, 'finesChoice'])->name('finesChoice');
            Route::post('/pay/{fineId}', [FinesController::class, 'payFine'])->name('payFine');
            Route::get('/{memberId}', [FinesController::class, 'memberFines'])->name('memberFines');
            Route::get('/unpaid', [FinesController::class, 'unpaidFines'])->name('unpaidFines');
        });

        Route::get('/clearance/{memberId}',[ClearanceController::class, 'check'])->name('clearance.check');

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/{id?}', [LibraryStaffController::class, 'show'])->name('show');
            Route::post('/', [LibraryStaffController::class, 'store'])->name('store');
            Route::put('/{staff}', [LibraryStaffController::class, 'update'])->name('update');
            Route::delete('/{staff}', [LibraryStaffController::class, 'destroy'])->name('destroy');
            Route::post('/logout', [StaffAuthController::class, 'logout'])->name('logout');
        });

    });
});

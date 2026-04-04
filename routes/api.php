<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BorrowTransactionController;
use App\Http\Controllers\FinesController;
use App\Http\Controllers\BooksController;
use App\Http\Controllers\ClearanceController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\AuthController;

Route::prefix('library')->group(function () {

    Route::get('/test', function () {
        return response()->json(['message' => 'Library API is working!']);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/login',  [AuthController::class, 'login']);
        Route::post('/test-login', [AuthController::class, 'testLogin']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });


        Route::get('/profile', [AuthController::class, 'profile'])->name('student.profile');
        Route::post('/logout', [AuthController::class, 'logout'])->name('student.logout');

        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/{id?}', [CategoryController::class, 'index'])->name('index');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::put('/{id}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('books')->name('books.')->group(function () {
            Route::get('/{id?}', [BooksController::class, 'index'])->name('index');
            Route::post('/', [BooksController::class, 'store'])->name('store');
            Route::patch('/{id}', [BooksController::class, 'update'])->name('update');
            Route::delete('/{id}', [BooksController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('borrow')->name('borrow.')->group(function () {
            Route::post('/requests', [BorrowTransactionController::class, 'requestBook'])
                ->name('requestBook');

            Route::get('/requests', [BorrowTransactionController::class, 'getUserRequests'])
                ->name('getAllRequests');

            Route::get('/users/{userId}/requests', [BorrowTransactionController::class, 'getUserRequests'])
                ->name('getUserRequests');

            Route::delete('/requests/{requestId}', [BorrowTransactionController::class, 'cancelRequest'])
                ->name('cancelRequest');

            Route::patch('/requests/{requestId}/approve', [BorrowTransactionController::class, 'approveRequest'])
                ->name('approveRequest');
            Route::patch('/requests/{requestId}/expire', [BorrowTransactionController::class, 'expireRequest'])
                ->name('expireRequest');

            Route::post('/complete/{requestId}', [BorrowTransactionController::class, 'completeBorrow'])
                ->name('completeBorrow');

            Route::post('/transactions/{transactionId}/return', [BorrowTransactionController::class, 'returnBook'])
                ->name('returnBook');

            Route::get('/users/{userId}/transactions', [BorrowTransactionController::class, 'getUserTransactions'])
                ->name('getUserTransactions');

            Route::get('/transactions', [BorrowTransactionController::class, 'getBorrowTransactions'])
                ->name('getBorrowTransactions');

            Route::post('/transactions/{transactionId}/pay-fine', [BorrowTransactionController::class, 'payFine'])
                ->name('payFine');

            Route::get('/books/{bookId}/queue', [BorrowTransactionController::class, 'getBookQueue'])
                ->name('getBookQueue');
        });

        Route::prefix('students')->name('students.')->group(function () {
            Route::get('/{id?}', [\App\Http\Controllers\StudentController::class, 'index'])->name('index');
            Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
            Route::get('/profile/stats', [AuthController::class, 'profileWithStats'])->name('profileWithStats');
            Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('updateProfile');

            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAllDevices'])->name('logoutAllDevices');

            Route::get('/eligibility', [AuthController::class, 'checkEligibility'])->name('eligibility');
        });

        Route::prefix('fines')->name('fines.')->group(function () {
            Route::get('/choice', [FinesController::class, 'finesChoice'])->name('finesChoice');
            Route::post('/pay/{fineId}', [FinesController::class, 'payFine'])->name('payFine');
            Route::get('/{userId}', [FinesController::class, 'memberFines'])->name('memberFines');
            Route::get('/unpaid', [FinesController::class, 'unpaidFines'])->name('unpaidFines');
        });

        Route::get('/clearance/{userId}', [ClearanceController::class, 'check'])->name('clearance.check');
});

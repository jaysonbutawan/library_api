<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BorrowTransactionController;
use App\Http\Controllers\FinesController;
use App\Http\Controllers\BooksController;
use App\Http\Controllers\LibraryStaffController;
use App\Http\Controllers\ClearanceController;
use App\Http\Controllers\StaffAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\BorrowRequestController;
use App\Http\Controllers\AuthController;

Route::prefix('library')->group(function () {

    Route::get('/test', function () {
        return response()->json(['message' => 'Library API is working!']);
    });


    Route::post('/login', [AuthController::class, 'login'])->name('student.login');
    Route::post('/staff/login', [StaffAuthController::class, 'login'])->name('staff.login');


    Route::middleware('auth:sanctum')->group(function () {
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
            Route::put('/{id}', [BooksController::class, 'update'])->name('update');
            Route::delete('/{id}', [BooksController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('borrow')->name('borrow.')->group(function () {

        // ========== REQUEST MANAGEMENT (Student Actions) ==========

            /**
             * Step 1: Student requests a book
             * POST /api/library/borrow/requests
             */
            Route::post('/requests', [BorrowTransactionController::class, 'requestBook'])
                ->name('requestBook');

            Route::get('/requests', [BorrowTransactionController::class, 'getUserRequests'])
                ->name('getAllRequests');

            /**
             * Get all requests for a user
             * GET /api/library/borrow/users/{userId}/requests
             */
            Route::get('/users/{userId}/requests', [BorrowTransactionController::class, 'getUserRequests'])
                ->name('getUserRequests');

            /**
             * Cancel a borrow request
             * DELETE /api/library/borrow/requests/{requestId}
             */
            Route::delete('/requests/{requestId}', [BorrowTransactionController::class, 'cancelRequest'])
                ->name('cancelRequest');

        // ========== APPROVAL MANAGEMENT (Librarian Actions) ==========

            /**
             * Step 2: Librarian approves a request
             * PATCH /api/library/borrow/requests/{requestId}/approve
             */
            Route::patch('/requests/{requestId}/approve', [BorrowTransactionController::class, 'approveRequest'])
                ->name('approveRequest');

            /**
             * Expire a request manually (after 7 days)
             * PATCH /api/library/borrow/requests/{requestId}/expire
             */
            Route::patch('/requests/{requestId}/expire', [BorrowTransactionController::class, 'expireRequest'])
                ->name('expireRequest');

        // ========== PICKUP MANAGEMENT (Librarian Actions) ==========

         //this route lang akong na test
            Route::post('/complete/{requestId}', [BorrowTransactionController::class, 'completeBorrow'])
                ->name('completeBorrow');

        // ========== RETURN MANAGEMENT (Student Actions) ==========

            /**
             * Student returns a borrowed book
             * POST /api/library/borrow/transactions/{transactionId}/return
             */
            Route::post('/transactions/{transactionId}/return', [BorrowTransactionController::class, 'returnBook'])
                ->name('returnBook');
            /**
             * Get borrow transactions for a specific user
             * GET /api/library/borrow/users/{userId}/transactions
             */
            Route::get('/users/{userId}/transactions', [BorrowTransactionController::class, 'getUserTransactions'])
                ->name('getUserTransactions');

            /**
             * Get all borrow transactions (with optional user filter)
             * GET /api/library/borrow/transactions
             * GET /api/library/borrow/transactions?user_id=5
             */
            Route::get('/transactions', [BorrowTransactionController::class, 'getBorrowTransactions'])
                ->name('getBorrowTransactions');

        // ========== FINE MANAGEMENT (Student/Admin Actions) ==========

            /**
             * Pay fine for overdue book
             * POST /api/library/borrow/transactions/{transactionId}/pay-fine
             */
            Route::post('/transactions/{transactionId}/pay-fine', [BorrowTransactionController::class, 'payFine'])
                ->name('payFine');

        // ========== QUEUE MANAGEMENT (Librarian/Admin View) ==========

            /**
             * Get queue status for a specific book
             * GET /api/library/borrow/books/{bookId}/queue
             */
            Route::get('/books/{bookId}/queue', [BorrowTransactionController::class, 'getBookQueue'])
                ->name('getBookQueue');
        });

        Route::prefix('students')->name('students.')->group(function () {
            Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
            Route::get('/profile/stats', [AuthController::class, 'profileWithStats'])->name('profileWithStats');
            Route::patch('/profile', [AuthController::class, 'updateProfile'])->name('updateProfile');

            // Authentication endpoints
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAllDevices'])->name('logoutAllDevices');

            // Eligibility check
            Route::get('/eligibility', [AuthController::class, 'checkEligibility'])->name('eligibility');
        });

        Route::prefix('fines')->name('fines.')->group(function () {
            Route::get('/choice', [FinesController::class, 'finesChoice'])->name('finesChoice');
            Route::post('/pay/{fineId}', [FinesController::class, 'payFine'])->name('payFine');
            Route::get('/{userId}', [FinesController::class, 'memberFines'])->name('memberFines');
            Route::get('/unpaid', [FinesController::class, 'unpaidFines'])->name('unpaidFines');
        });

        Route::get('/clearance/{userId}', [ClearanceController::class, 'check'])->name('clearance.check');

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/{id?}', [LibraryStaffController::class, 'show'])->name('show');
            Route::post('/', [LibraryStaffController::class, 'store'])->name('store');
            Route::put('/{staff}', [LibraryStaffController::class, 'update'])->name('update');
            Route::delete('/{staff}', [LibraryStaffController::class, 'destroy'])->name('destroy');
            Route::post('/logout', [StaffAuthController::class, 'logout'])->name('logout');
        });
    });
});

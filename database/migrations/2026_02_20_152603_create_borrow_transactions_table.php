<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrow_transactions', function (Blueprint $table) {
            $table->id('transaction_id');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('book_id');
            $table->unsignedBigInteger('request_id')->nullable(); // Link to the request that triggered this

            // Important: track which physical copy was borrowed (if you have serial numbers)
            $table->string('copy_number')->nullable();

            // Dates
            $table->date('borrow_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();

            // Status: borrowed -> returned -> overdue
            $table->enum('status', ['borrowed', 'returned', 'overdue'])
                ->default('borrowed')
                ->index();

            // For tracking overdue penalties
            $table->integer('days_overdue')->nullable();
            $table->decimal('fine_amount', 8, 2)->nullable();
            $table->boolean('fine_paid')->default(false);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            // Constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('book_id')
                ->references('book_id')
                ->on('books')
                ->onDelete('cascade');

            $table->foreign('request_id')
                ->references('request_id')
                ->on('borrow_requests')
                ->onDelete('set null');

            // Useful indexes
            $table->index(['user_id', 'status']);
            $table->index(['book_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_transactions');
    }
};

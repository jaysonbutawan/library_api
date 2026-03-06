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

            $table->date('borrow_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();

            $table->enum('status', ['borrowed', 'returned', 'overdue'])
                ->default('borrowed');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('book_id')
                ->references('book_id')
                ->on('books')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_transactions');
    }
};

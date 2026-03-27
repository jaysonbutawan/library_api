<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('borrow_requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('book_id');

            // Status: pending (waiting) -> approved (ready to pick up) -> cancelled (rejected/expired)
            $table->enum('status', ['pending', 'approved', 'cancelled', 'expired'])
                ->default('pending')
                ->index();

            // Queue position - auto-incremented per book to track order
            $table->integer('queue_position')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // 7 days from approval, then auto-cancel

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

            // Composite unique: one pending request per user per book
            $table->unique(['user_id', 'book_id', 'status'], 'unique_pending_request');

            // Index for finding next in queue
            $table->index(['book_id', 'status', 'queue_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_requests');
    }
};

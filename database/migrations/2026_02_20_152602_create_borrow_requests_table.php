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

            $table->string('status', 20)->default('pending');

            $table->integer('queue_position')->nullable();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();

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

            $table->index(['book_id', 'status', 'queue_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_requests');
    }
};

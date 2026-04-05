<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id('book_id');
            $table->string('isbn', 20)->unique()->nullable();
            $table->string('title', 255);
            $table->string('author')->nullable();
            $table->year('publication_year')->nullable();
            $table->string('status', 50)->default('available');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('category_id')
                  ->references('category_id')
                  ->on('categories')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

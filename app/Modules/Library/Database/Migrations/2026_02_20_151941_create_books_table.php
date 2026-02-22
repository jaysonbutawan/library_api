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
            $table->string('status', 50)->default('available');
            $table->string('category', 100)->nullable();
            $table->integer('total_copies', 255)->default(1);
            $table->integer('available_copies')->default(1);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
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
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('category', 100)->nullable();
            $table->integer('total_copies')->default(1);
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
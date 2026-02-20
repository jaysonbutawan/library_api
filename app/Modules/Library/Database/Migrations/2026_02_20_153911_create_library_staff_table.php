<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_staff', function (Blueprint $table) {

            $table->id('staff_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password_hash');

            $table->enum('role', ['librarian', 'assistant'])
                ->default('assistant');

            $table->enum('status', ['active', 'inactive'])
                ->default('active');
            $table->timestamp('last_login')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_staff');
    }
};

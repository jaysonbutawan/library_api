<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_members', function (Blueprint $table) {

            $table->id('library_member_id');

            $table->unsignedBigInteger('student_id')->unique();

            $table->string('full_name')->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();

            $table->enum('membership_status', ['active', 'blocked'])
                ->default('active');

            $table->timestamp('registered_at')->useCurrent();

            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_members');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {

            $table->id('fine_id'); 
          
            $table->foreignId('transaction_id')
                  ->constrained('borrow_transactions', 'transaction_id')
                  ->cascadeOnDelete();

            $table->decimal('amount', 10, 2);

            $table->enum('paid_status', ['paid', 'unpaid'])
                  ->default('unpaid');

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};

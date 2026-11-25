<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 100);
            $table->enum('type', ['INCOME', 'EXPENSE']);
            $table->string('icon', 100)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name', 'type']);
        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['INCOME', 'EXPENSE']);
            $table->text('description')->nullable();
            $table->dateTime('transaction_date');
            $table->timestamps();
            $table->index(['user_id', 'transaction_date']);
        });
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_limit', 15, 2);
            $table->enum('period', ['MONTHLY', 'QUARTERLY']);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->unique(['user_id', 'category_id', 'period', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
    }
};

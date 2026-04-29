<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add action column to transaction_types
        Schema::table('transaction_types', function (Blueprint $table) {
            $table->string('action')->default('neutral')->after('name');
        });

        // Change transaction_categories.transaction_type_id: cascade → restrict
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropForeign(['transaction_type_id']);
            $table->foreign('transaction_type_id')
                ->references('id')
                ->on('transaction_types')
                ->restrictOnDelete();
        });

        // Change transactions.transaction_category_id: cascade → restrict
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['transaction_category_id']);
            $table->foreign('transaction_category_id')
                ->references('id')
                ->on('transaction_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['transaction_category_id']);
            $table->foreign('transaction_category_id')
                ->references('id')
                ->on('transaction_categories')
                ->cascadeOnDelete();
        });

        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropForeign(['transaction_type_id']);
            $table->foreign('transaction_type_id')
                ->references('id')
                ->on('transaction_types')
                ->cascadeOnDelete();
        });

        Schema::table('transaction_types', function (Blueprint $table) {
            $table->dropColumn('action');
        });
    }
};

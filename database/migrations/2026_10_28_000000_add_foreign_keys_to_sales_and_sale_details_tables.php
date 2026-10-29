<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->change();
            $table->unsignedBigInteger('product_id')->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->unsignedInteger('sale_id')->change();
            $table->unsignedInteger('product_id')->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->change();
        });
    }
};

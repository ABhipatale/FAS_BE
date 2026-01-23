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
        Schema::table('users', function (Blueprint $table) {
            $table->string('address')->nullable()->after('email');
            $table->string('phone')->nullable()->after('address');
            $table->string('sex')->nullable()->after('phone');
            $table->integer('age')->nullable()->after('sex');
            $table->date('dob')->nullable()->after('age');
            $table->string('position')->nullable()->after('dob');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'sex', 'age', 'dob', 'position']);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logos are stored as base64 data URIs so they can be served straight from
     * the JSON API (sidebar, company list, PDF header) with no file storage,
     * symlink or CORS setup. A 255-char string column cannot hold that.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->longText('logo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo')->nullable()->change();
        });
    }
};

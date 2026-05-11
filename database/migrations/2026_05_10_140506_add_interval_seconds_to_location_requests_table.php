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
        Schema::table('location_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('interval_seconds')->default(5)->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('location_requests', function (Blueprint $table) {
            $table->dropColumn('interval_seconds');
        });
    }
};

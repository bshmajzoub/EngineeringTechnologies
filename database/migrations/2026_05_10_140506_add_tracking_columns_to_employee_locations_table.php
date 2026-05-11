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
        Schema::table('employee_locations', function (Blueprint $table) {
            $table->float('speed')->nullable()->after('accuracy');
            $table->float('heading')->nullable()->after('speed');
            $table->timestamp('recorded_at')->nullable()->after('heading');

            $table->index(['user_id', 'location_request_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_locations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'location_request_id', 'recorded_at']);
            $table->dropColumn(['speed', 'heading', 'recorded_at']);
        });
    }
};

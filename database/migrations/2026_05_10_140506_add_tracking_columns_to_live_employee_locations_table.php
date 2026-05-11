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
        Schema::table('live_employee_locations', function (Blueprint $table) {
            $table->foreignId('tracking_session_id')
                ->nullable()
                ->after('user_id')
                ->constrained('location_requests')
                ->nullOnDelete();
            $table->float('speed')->nullable()->after('accuracy');
            $table->float('heading')->nullable()->after('speed');
            $table->timestamp('recorded_at')->nullable()->after('heading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_employee_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracking_session_id');
            $table->dropColumn(['speed', 'heading', 'recorded_at']);
        });
    }
};

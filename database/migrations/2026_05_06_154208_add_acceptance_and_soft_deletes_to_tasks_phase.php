<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE task_assignments MODIFY status ENUM('pending', 'active', 'completed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending'");

        Schema::table('task_assignments', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('notes');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->index(['status', 'accepted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropIndex(['status', 'accepted_at']);
            $table->dropColumn(['accepted_at', 'rejected_at', 'rejection_reason']);
        });

        DB::statement("ALTER TABLE task_assignments MODIFY status ENUM('pending', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

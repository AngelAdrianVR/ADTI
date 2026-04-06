<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->integer('approved_extra_hours')->nullable()->after('extra_minutes');
            $table->integer('approved_extra_minutes')->nullable()->after('approved_extra_hours');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_extra_minutes');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_user', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'approved_extra_hours',
                'approved_extra_minutes',
                'approved_by',
                'approved_at'
            ]);
        });
    }
};
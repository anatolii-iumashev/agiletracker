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
        Schema::table('items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('due_date');
            $table->date('end_date')->nullable()->after('start_date');
            $table->date('etd_date')->nullable()->after('end_date');
            $table->date('eta_date')->nullable()->after('etd_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'etd_date', 'eta_date']);
        });
    }
};

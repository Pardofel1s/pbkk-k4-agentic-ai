<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_test_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scan_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('retest_of_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->string('status', 10);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};

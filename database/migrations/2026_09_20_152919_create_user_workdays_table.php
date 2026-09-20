<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_workdays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('work_date');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->enum('status', [
                'OPEN',
                'CLOSED_SELLER',
                'CLOSED_SUPERVISOR',
                'CLOSED_TIMEOUT'
            ])->default('OPEN');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('close_reason')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date'], 'uq_user_work_date');
            $table->index(['work_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_workdays');
    }
};
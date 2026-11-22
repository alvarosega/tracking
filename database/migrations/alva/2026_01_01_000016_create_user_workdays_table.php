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
        Schema::connection('mysql')->create('user_workdays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('work_date');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->enum('status', ['OPEN','CLOSED_SELLER','CLOSED_SUPERVISOR','CLOSED_TIMEOUT'])->default('OPEN');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->string('close_reason', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'work_date'], 'uq_user_work_date');
            $table->index(['work_date', 'status'], 'idx_workday_lookup');

            $table->foreign('closed_by', 'fk_workdays_supervisor')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id', 'fk_workdays_user')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('user_workdays');
    }
};

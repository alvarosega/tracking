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
        Schema::connection('mysql')->create('supervisor_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('module_key', 50);
            $table->string('submodule_key', 50)->default('');
            $table->boolean('is_allowed')->default(false);
            $table->boolean('can_write')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'module_key', 'submodule_key'], 'uniq_user_mod_submod');
            $table->index(['user_id', 'module_key'], 'idx_smp_user_module');
            $table->index('expires_at', 'idx_smp_expires_at');

            $table->foreign('assigned_by', 'fk_smp_assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id', 'fk_smp_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('supervisor_module_permissions');
    }
};

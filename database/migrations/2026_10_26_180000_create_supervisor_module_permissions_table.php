<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('module_key', 50);
            $table->string('submodule_key', 50)->default('');
            $table->boolean('is_allowed')->default(false);
            $table->boolean('can_write')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'module_key', 'submodule_key'], 'uniq_user_mod_submod');
            $table->index(['user_id', 'module_key']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_module_permissions');
    }
};


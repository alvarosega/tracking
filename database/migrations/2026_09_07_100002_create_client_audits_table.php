<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('audit_status', 30); // VALIDATED, NOT_FOUND, CLOSED_PERMANENT, DUPLICATE
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->float('accuracy')->default(0.0);
            $table->text('comments')->nullable();
            $table->json('photo_paths'); // Rutas relativas del storage
            $table->timestamp('audited_at');
            $table->timestamps();

            $table->foreign('client_id')
                ->references('id_cliente')
                ->on('reference_clients')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_audits');
    }
};
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
        Schema::connection('mysql')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 255);
            $table->string('connection', 255);
            $table->string('queue', 255);
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->unique('uuid', 'failed_jobs_uuid_unique');
            $table->index(['connection', 'queue', 'failed_at'], 'failed_jobs_connection_queue_failed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('failed_jobs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('etl_runs')) {
            Schema::create('etl_runs', function (Blueprint $table) {
                $table->id();
                $table->string('tabla', 120)->index();
                $table->string('status', 40)->default('running')->index();
                $table->date('fecha_ini')->nullable()->index();
                $table->date('fecha_fin')->nullable();
                $table->boolean('dry_run')->default(false);
                $table->unsignedInteger('chunk_size')->default(1000);
                $table->unsignedBigInteger('rows_expected')->nullable();
                $table->unsignedBigInteger('rows_migrated')->default(0);
                $table->unsignedBigInteger('rows_failed')->default(0);
                $table->unsignedBigInteger('rows_skipped')->default(0);
                $table->unsignedBigInteger('last_offset')->default(0);
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('etl_run_items')) {
            Schema::create('etl_run_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etl_run_id')->constrained('etl_runs')->cascadeOnDelete();
                $table->unsignedInteger('batch_num')->default(0);
                $table->string('status', 40)->default('pending')->index();
                $table->unsignedBigInteger('rows_processed')->default(0);
                $table->unsignedBigInteger('rows_inserted')->default(0);
                $table->unsignedBigInteger('rows_skipped')->default(0);
                $table->text('error')->nullable();
                $table->timestamps();

                $table->index(['etl_run_id', 'batch_num']);
            });
        }

        if (!Schema::hasTable('etl_conflictos')) {
            Schema::create('etl_conflictos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etl_run_id')->nullable()->constrained('etl_runs')->nullOnDelete();
                $table->string('tabla', 120)->index();
                $table->string('legacy_id', 180)->nullable();
                $table->string('motivo', 255);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index(['etl_run_id', 'tabla']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('etl_conflictos');
        Schema::dropIfExists('etl_run_items');
        Schema::dropIfExists('etl_runs');
    }
};

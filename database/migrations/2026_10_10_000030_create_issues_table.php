<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 40);
            $table->string('type', 20); // error | log | console
            $table->string('level', 20)->default('error');
            $table->string('platform', 20)->default('php');
            $table->string('title', 500);
            $table->string('culprit', 500)->nullable();
            $table->string('status', 20)->default('open'); // open | resolved | ignored
            $table->boolean('is_regressed')->default(false);
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->useCurrent();
            $table->unsignedBigInteger('events_count')->default(0);
            $table->char('last_event_id', 36)->nullable();
            $table->json('environments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ignored_until')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
            $table->index(['project_id', 'status', 'last_seen']);
            $table->index(['project_id', 'type', 'last_seen']);
            $table->index(['project_id', 'level']);
        });

        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('comment'); // comment | status | assign
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('issues');
    }
};

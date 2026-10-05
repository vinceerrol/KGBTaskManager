<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('creation_method', 12)->default('fields');
            $table->text('source_brief')->nullable();
            $table->uuid('creation_key')->nullable();
            $table->string('creation_payload_hash', 64)->nullable();
            $table->unique(['created_by', 'creation_key'], 'tasks_actor_creation_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('tasks_actor_creation_key_unique');
            $table->dropColumn(['creation_method', 'source_brief', 'creation_key', 'creation_payload_hash']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->string('model_id')->index();
            $table->string('from_state');
            $table->string('to_state');
            $table->string('actor_type')->nullable()->index();
            $table->string('actor_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_transitions');
    }
};

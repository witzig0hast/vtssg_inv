<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $t) {
            $t->unsignedInteger('max_load_kg')->nullable(); // Fahrzeug-Zuladung
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('ical_token', 64)->nullable()->unique();
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_by')->nullable();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->string('event', 60);
            $t->json('payload');
            $t->unsignedSmallInteger('status')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['signed_at', 'signed_by']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('ical_token'));
        Schema::table('locations', fn (Blueprint $t) => $t->dropColumn('max_load_kg'));
    }
};

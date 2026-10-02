<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('venue')->nullable();
            // draft, planned, confirmed, loaded, running, returned, closed, cancelled
            $table->string('status', 20)->default('draft');
            $table->dateTime('setup_at')->nullable();   // Aufbau / Beladung
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('teardown_at')->nullable(); // Rückgabe im Lager
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['setup_at', 'teardown_at']);
        });

        Schema::create('project_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_model_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete(); // konkretes Gerät
            $table->string('group', 80)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('packed_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            // Zumiete
            $table->boolean('is_external')->default(false);
            $table->string('external_name')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('external_cost_cents')->nullable();
            $table->unsignedInteger('price_cents')->nullable(); // Mietpreis pro Tag (Override)
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['asset_model_id', 'project_id']);
        });

        Schema::create('kit_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('kit_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_model_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('group', 80)->nullable();
        });

        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_type', 20); // user, project, location, customer
            $table->unsignedBigInteger('assignee_id');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->dateTime('checked_out_at');
            $table->dateTime('expected_return_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_out', 20)->default('ok'); // ok, worn, damaged
            $table->string('condition_in', 20)->nullable();
            $table->string('note_out')->nullable();
            $table->string('note_in')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'returned_at']);
            $table->index(['assignee_type', 'assignee_id']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_type_id')->constrained();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('inspector_name')->nullable(); // externer Prüfer / Sachkundiger
            $table->date('performed_at');
            $table->date('next_due')->nullable();
            $table->string('result', 15)->default('passed'); // passed, conditional, failed
            $table->string('protocol_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'inspection_type_id']);
        });

        Schema::create('maintenance_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal'); // low, normal, high, critical
            $table->string('status', 15)->default('open'); // open, in_progress, waiting_parts, done, cancelled
            $table->boolean('blocks_asset')->default(true);
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 15)->default('open'); // open, closed
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stocktake_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_quantity')->default(1);
            $table->unsignedInteger('found_quantity')->default(0);
            $table->dateTime('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['stocktake_id', 'asset_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->nullableMorphs('auditable');
            $table->string('summary')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at');
            $table->index('event');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'stocktake_lines', 'stocktakes', 'ticket_comments', 'maintenance_tickets', 'inspections', 'checkouts', 'kit_template_items', 'kit_templates', 'project_items', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

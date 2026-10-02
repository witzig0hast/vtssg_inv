<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_model_id')->constrained();
            $table->foreignId('status_label_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete(); // Stammlagerort
            $table->foreignId('parent_id')->nullable()->constrained('assets')->nullOnDelete(); // Case/Kit
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial')->nullable()->index();
            $table->string('name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_cost_cents')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('warranty_until')->nullable();
            $table->decimal('operating_hours', 10, 1)->default(0);
            $table->boolean('is_container')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason')->nullable();
            $table->date('next_inspection_due')->nullable()->index(); // denormalisiert
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('consumables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 20)->default('Stk');
            $table->integer('quantity')->default(0);
            $table->unsignedInteger('min_quantity')->default(0);
            $table->unsignedInteger('unit_cost_cents')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('consumable_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->integer('delta');
            $table->string('reason', 30); // purchase, usage, correction, loss
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('sha256', 64)->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->decimal('hours', 8, 1);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['usage_logs', 'attachments', 'consumable_movements', 'consumables', 'assets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('warehouse'); // warehouse, shelf, vehicle, venue, workshop, other
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 20)->default('asset'); // asset | consumable
            $table->string('color', 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('status_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // deployable = ausgebbar, pending, undeployable (defekt/Werkstatt), archived (ausgemustert/verloren)
            $table->string('kind', 20)->default('deployable');
            $table->string('color', 7)->default('#64748b');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('inspection_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('default_interval_months')->default(12);
            $table->boolean('blocks_checkout')->default(true); // überfällig => keine Ausgabe
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('model_number')->nullable();
            $table->string('tracking', 10)->default('serial'); // serial = Einzelgerät, quantity = Mengenartikel
            $table->string('unit', 20)->default('Stk');
            $table->unsignedInteger('rental_price_cents')->default(0); // pro Einsatztag
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->unsignedInteger('power_w')->nullable();
            $table->unsignedSmallInteger('depreciation_months')->nullable();
            $table->unsignedInteger('service_hours_limit')->nullable(); // z. B. Lampenlebensdauer
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_model_inspection_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->unique(['asset_model_id', 'inspection_type_id'], 'amit_unique');
        });
    }

    public function down(): void
    {
        foreach (['asset_model_inspection_type', 'asset_models', 'inspection_types', 'status_labels', 'customers', 'suppliers', 'manufacturers', 'categories', 'locations', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

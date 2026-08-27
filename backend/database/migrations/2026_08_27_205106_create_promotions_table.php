<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Database level rules that keep an invalid promotion from ever being
     * persisted, regardless of the code path that writes it.
     *
     * @var array<string, string>
     */
    private array $checks = [
        'promotions_target_exclusive_check' => '(product_id IS NOT NULL AND category_id IS NULL) OR (product_id IS NULL AND category_id IS NOT NULL)',
        'promotions_date_range_check' => 'end_date > start_date',
        'promotions_discount_value_positive_check' => 'discount_value > 0',
        'promotions_percentage_range_check' => "discount_type <> 'PERCENTAGE' OR (discount_value >= 1 AND discount_value <= 100)",
        'promotions_discount_type_check' => "discount_type IN ('PERCENTAGE', 'FIXED_AMOUNT')",
        'promotions_status_check' => "status IN ('SCHEDULED', 'ACTIVE', 'FINISHED')",
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('SCHEDULED');
            $table->timestamps();

            // PostgreSQL does not index foreign keys automatically, and the
            // status summary and date validity queries filter by these columns.
            $table->index('product_id');
            $table->index('category_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        foreach ($this->checks as $name => $expression) {
            DB::statement("ALTER TABLE promotions ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropping the table also drops its check constraints.
        Schema::dropIfExists('promotions');
    }
};

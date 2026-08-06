<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->uuid('plan_request_id')->nullable()->unique()->index();
            $table->uuid('order_id')->nullable()->unique()->index();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('template_key')->default('australia_tax_invoice');
            $table->string('status', 20)->default('issued')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('currency', 10)->default('AUD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('issue_date')->nullable()->index();
            $table->timestamp('due_date')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_abn', 11)->nullable();
            $table->string('supplier_email')->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_abn', 11)->nullable();
            $table->string('recipient_email')->nullable();
            $table->text('recipient_address')->nullable();
            $table->json('line_items')->nullable();
            $table->longText('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('plan_request_id')->references('id')->on('plan_requests')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

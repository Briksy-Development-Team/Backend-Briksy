<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\PlanRequest;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly DynamicIdGeneratorService $idGenerator
    ) {
    }

    public function createForPlanRequest(PlanRequest $planRequest, Order $order, SubscriptionPlan $plan, ?string $recipientAbn = null): Invoice
    {
        return DB::transaction(function () use ($planRequest, $order, $plan, $recipientAbn): Invoice {
            $supplier = $this->supplierDetails();
            $grossAmount = (float) $order->total_amount;
            $taxAmount = round($grossAmount / 11, 2);
            $subtotal = round($grossAmount - $taxAmount, 2);

            $invoice = Invoice::query()->firstOrNew([
                'plan_request_id' => $planRequest->id,
            ]);

            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number = $this->idGenerator->generate('invoices');
            }

            $invoice->fill([
                'order_id' => $order->id,
                'organization_id' => $order->organization_id,
                'user_id' => $order->user_id,
                'template_key' => 'australia_tax_invoice',
                'status' => 'issued',
                'payment_status' => 'unpaid',
                'currency' => $order->currency ?? 'AUD',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $grossAmount,
                'issue_date' => $invoice->issue_date ?? now(),
                'due_date' => $invoice->due_date ?? now()->addDays(14),
                'supplier_name' => $supplier['name'],
                'supplier_abn' => $supplier['abn'],
                'supplier_email' => $supplier['email'],
                'supplier_address' => $supplier['address'],
                'recipient_name' => $planRequest->company_name ?: $planRequest->organization?->name ?: $order->organization?->name,
                'recipient_abn' => $recipientAbn,
                'recipient_email' => $planRequest->contact_email ?: $order->organization?->contact_email,
                'recipient_address' => $planRequest->organization?->address ?? $order->organization?->address,
                'line_items' => [
                    [
                        'description' => $plan->name,
                        'quantity' => 1,
                        'unit_price' => round($grossAmount, 2),
                        'line_total' => round($grossAmount, 2),
                        'tax_amount' => $taxAmount,
                        'tax_inclusive' => true,
                    ],
                ],
                'notes' => sprintf(
                    'Tax invoice generated automatically after approval of plan request %s.',
                    $planRequest->request_code
                ),
            ]);

            $invoice->plan_request_id = $planRequest->id;
            $invoice->save();

            return $invoice;
        });
    }

    public function supplierDetails(): array
    {
        $settings = PlatformSetting::query()
            ->whereIn('key', [
                'invoice_supplier_name',
                'invoice_supplier_abn',
                'invoice_supplier_email',
                'invoice_supplier_address',
            ])
            ->get()
            ->keyBy('key');

        return [
            'name' => $settings->get('invoice_supplier_name')?->value ?: config('app.name', 'Briksy'),
            'abn' => $settings->get('invoice_supplier_abn')?->value ?: null,
            'email' => $settings->get('invoice_supplier_email')?->value ?: config('mail.from.address'),
            'address' => $settings->get('invoice_supplier_address')?->value ?: 'Australia',
        ];
    }
}

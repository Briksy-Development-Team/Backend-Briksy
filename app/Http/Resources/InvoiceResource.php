<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'plan_request_id' => $this->plan_request_id,
            'order_id' => $this->order_id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'template_key' => $this->template_key,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'tax_amount' => (string) $this->tax_amount,
            'total_amount' => (string) $this->total_amount,
            'issue_date' => $this->issue_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'supplier_name' => $this->supplier_name,
            'supplier_abn' => $this->supplier_abn,
            'supplier_email' => $this->supplier_email,
            'supplier_address' => $this->supplier_address,
            'recipient_name' => $this->recipient_name,
            'recipient_abn' => $this->recipient_abn,
            'recipient_email' => $this->recipient_email,
            'recipient_address' => $this->recipient_address,
            'line_items' => $this->line_items,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 32px;
            background: #f8fafc;
        }
        .sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 32px;
        }
        .header, .summary, .footer {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }
        .title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .muted {
            color: #6b7280;
            font-size: 13px;
        }
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            background: #f9fafb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 10px;
            text-align: left;
            vertical-align: top;
        }
        th {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.04em;
        }
        .totals {
            width: 320px;
            margin-left: auto;
            margin-top: 24px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .totals .row.total {
            font-weight: 700;
            font-size: 18px;
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: #111827;
            color: #fbbf24;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div>
                <div class="badge">TAX INVOICE</div>
                <h1 class="title">{{ $invoice->supplier_name ?? config('app.name') }}</h1>
                <div class="muted">{{ $invoice->supplier_address }}</div>
                @if($invoice->supplier_abn)
                    <div class="muted">ABN: {{ $invoice->supplier_abn }}</div>
                @endif
                @if($invoice->supplier_email)
                    <div class="muted">Email: {{ $invoice->supplier_email }}</div>
                @endif
            </div>
            <div class="card" style="min-width: 280px;">
                <div><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Issue date:</strong> {{ optional($invoice->issue_date)->format('d M Y') }}</div>
                <div><strong>Due date:</strong> {{ optional($invoice->due_date)->format('d M Y') ?? 'On receipt' }}</div>
                <div><strong>Status:</strong> {{ strtoupper($invoice->status) }}</div>
                <div><strong>Payment:</strong> {{ strtoupper($invoice->payment_status) }}</div>
            </div>
        </div>

        <div class="summary" style="margin-top: 28px;">
            <div class="card" style="flex: 1;">
                <div class="muted" style="text-transform: uppercase; font-size: 12px; font-weight: 700;">Bill To</div>
                <div style="font-weight: 700; font-size: 16px; margin-top: 6px;">{{ $invoice->recipient_name ?? 'Customer' }}</div>
                @if($invoice->recipient_address)
                    <div class="muted" style="margin-top: 6px;">{{ $invoice->recipient_address }}</div>
                @endif
                @if($invoice->recipient_abn)
                    <div class="muted">ABN: {{ $invoice->recipient_abn }}</div>
                @endif
                @if($invoice->recipient_email)
                    <div class="muted">Email: {{ $invoice->recipient_email }}</div>
                @endif
            </div>
            <div class="card" style="width: 280px;">
                <div class="muted" style="text-transform: uppercase; font-size: 12px; font-weight: 700;">Invoice Details</div>
                <div style="margin-top: 8px;"><strong>Currency:</strong> {{ $invoice->currency }}</div>
                <div><strong>Template:</strong> Australia Tax Invoice</div>
                <div><strong>Reference:</strong> {{ $invoice->order?->display_number ?? $invoice->order_id ?? '—' }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 46%;">Description</th>
                    <th style="width: 12%;">Qty</th>
                    <th style="width: 14%;">Unit Price</th>
                    <th style="width: 14%;">GST</th>
                    <th style="width: 14%;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($invoice->line_items ?? []) as $item)
                    <tr>
                        <td>
                            <div style="font-weight: 700;">{{ $item['description'] ?? 'Item' }}</div>
                            @if(!empty($item['tax_inclusive']))
                                <div class="muted">GST inclusive</div>
                            @endif
                        </td>
                        <td>{{ $item['quantity'] ?? 1 }}</td>
                        <td>{{ number_format((float) ($item['unit_price'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($item['tax_amount'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($item['line_total'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span>Subtotal</span>
                <span>{{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency }}</span>
            </div>
            <div class="row">
                <span>GST</span>
                <span>{{ number_format((float) $invoice->tax_amount, 2) }} {{ $invoice->currency }}</span>
            </div>
            <div class="row total">
                <span>Total</span>
                <span>{{ number_format((float) $invoice->total_amount, 2) }} {{ $invoice->currency }}</span>
            </div>
        </div>

        @if($invoice->notes)
            <div class="card" style="margin-top: 24px;">
                <div class="muted" style="text-transform: uppercase; font-size: 12px; font-weight: 700;">Notes</div>
                <div style="margin-top: 8px;">{{ $invoice->notes }}</div>
            </div>
        @endif

        <div class="footer" style="margin-top: 28px;">
            <div class="muted">This is a tax invoice for Australian GST purposes.</div>
            <div class="muted">Generated by Briksy</div>
        </div>
    </div>
</body>
</html>

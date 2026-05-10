<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['invoice_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; margin: 28px; }
        .row { width: 100%; display: table; table-layout: fixed; }
        .col { display: table-cell; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .title { font-size: 24px; font-weight: 700; margin: 0 0 8px; }
        .subtitle { font-size: 14px; margin: 0 0 16px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; text-align: left; }
        th.right, td.right { text-align: right; }
        .totals { margin-top: 12px; width: 280px; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }
        .totals tr.total td { font-weight: 700; }
        .footer { margin-top: 24px; font-size: 12px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="row">
        <div class="col">
            <p class="title">{{ $template->company_name }}</p>
            @if($template->header_text)
                <p class="subtitle">{{ $template->header_text }}</p>
            @endif
            <p class="muted">
                {{ $template->company_address ?? '-' }}<br>
                {{ $template->company_phone ?? '-' }} | {{ $template->company_email ?? '-' }}
            </p>
        </div>
        <div class="col right">
            <p class="title">INVOICE</p>
            <p><strong>{{ $invoice['invoice_number'] }}</strong></p>
            <p class="muted">{{ $invoice['invoice_date'] }}</p>
        </div>
    </div>

    <div class="card">
        <strong>Bill To</strong><br>
        {{ $invoice['athlete_name'] }}<br>
        <span class="muted">{{ $invoice['athlete_email'] }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Status</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice['payment_type'] }}</td>
                <td>{{ $invoice['status'] }}</td>
                <td class="right">Rp {{ number_format($invoice['total_amount'], 2, '.', ',') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Paid</td>
            <td class="right">Rp {{ number_format($invoice['paid_amount'], 2, '.', ',') }}</td>
        </tr>
        <tr class="total">
            <td>Remaining</td>
            <td class="right">Rp {{ number_format($invoice['remaining_amount'], 2, '.', ',') }}</td>
        </tr>
    </table>

    @if(!empty($invoice['notes']) || !empty($template->payment_notes))
        <div class="card">
            <strong>Notes</strong>
            @if(!empty($invoice['notes']))
                <p>{{ $invoice['notes'] }}</p>
            @endif
            @if(!empty($template->payment_notes))
                <p class="muted">{{ $template->payment_notes }}</p>
            @endif
        </div>
    @endif

    @if($template->footer_text)
        <p class="footer">{{ $template->footer_text }}</p>
    @endif
</body>
</html>

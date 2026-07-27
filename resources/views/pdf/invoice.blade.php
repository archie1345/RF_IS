<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['invoice_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; margin: 28px; }
        .row { width: 100%; display: table; table-layout: fixed; }
        .col { display: table-cell; vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .title { font-size: 22px; font-weight: 700; margin: 0 0 8px; }
        .subtitle { font-size: 14px; margin: 0 0 16px; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
        .paid { display: inline-block; border: 2px solid #15803d; color: #15803d; padding: 5px 10px; font-size: 15px; font-weight: 700; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; text-align: left; }
        th.right, td.right { text-align: right; }
        .totals { margin-top: 12px; width: 330px; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }
        .totals tr.total td { border-top: 1px solid #d1d5db; padding-top: 8px; font-weight: 700; }
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
            <p class="title">{{ $invoice['document_title'] ?? 'INVOICE' }}</p>
            <p><strong>{{ $invoice['invoice_number'] }}</strong></p>
            <p class="muted">
                {{ !empty($invoice['is_payroll']) ? 'Dibayar' : 'Diterbitkan' }}: {{ $invoice['invoice_date'] }}<br>
                @if(empty($invoice['is_payroll']))
                    Jatuh tempo: {{ $invoice['due_date'] ?? '-' }}
                @endif
            </p>
            @if(!empty($invoice['is_payroll']) && ($invoice['status'] ?? '') === 'PAID')
                <span class="paid">SUDAH DIBAYAR</span>
            @endif
        </div>
    </div>

    <div class="card">
        <strong>{{ $invoice['recipient_label'] ?? 'Ditagihkan kepada' }}</strong><br>
        {{ $invoice['athlete_name'] }}<br>
        <span class="muted">{{ $invoice['athlete_email'] }}</span><br>
        <span class="muted">Metode pembayaran: {{ $invoice['collection_method'] ?? 'TRANSFER' }}</span>
        @if(!empty($invoice['is_payroll']) && !empty($invoice['payroll_period']))
            <br><span class="muted">Periode payroll: {{ $invoice['payroll_period'] }}</span>
        @endif
    </div>

    @if(!empty($invoice['is_payroll']))
        <table>
            <thead>
                <tr>
                    <th>Komponen payroll</th>
                    <th>Keterangan</th>
                    <th class="right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Honor dasar</td>
                    <td>
                        {{ $invoice['payroll_basis'] ?? '-' }}
                        @if(!empty($invoice['payroll_units']))
                            · {{ rtrim(rtrim(number_format((float) $invoice['payroll_units'], 2, ',', '.'), '0'), ',') }} unit
                        @endif
                        @if(!empty($invoice['payroll_rate']))
                            × Rp {{ number_format((float) $invoice['payroll_rate'], 0, ',', '.') }}
                        @endif
                    </td>
                    <td class="right">Rp {{ number_format((float) ($invoice['payroll_base_amount'] ?? 0), 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Bonus</td>
                    <td>Bonus tambahan pelatih</td>
                    <td class="right">Rp {{ number_format((float) ($invoice['payroll_bonus_amount'] ?? 0), 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th>Status</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $invoice['payment_type'] }}</td>
                    <td>{{ $invoice['status'] }}</td>
                    <td class="right">Rp {{ number_format($invoice['total_amount'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="totals">
        @if(!empty($invoice['is_payroll']))
            <tr>
                <td>Total payroll</td>
                <td class="right">Rp {{ number_format($invoice['total_amount'], 0, ',', '.') }}</td>
            </tr>
        @endif
        <tr>
            <td>Sudah dibayar</td>
            <td class="right">Rp {{ number_format($invoice['paid_amount'], 0, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Sisa pembayaran</td>
            <td class="right">Rp {{ number_format($invoice['remaining_amount'], 0, ',', '.') }}</td>
        </tr>
    </table>

    @if(!empty($invoice['notes']) || (!empty($template->payment_notes) && empty($invoice['is_payroll'])))
        <div class="card">
            <strong>Catatan</strong>
            @if(!empty($invoice['notes']))
                <p>{{ $invoice['notes'] }}</p>
            @endif
            @if(!empty($template->payment_notes) && empty($invoice['is_payroll']))
                <p class="muted">{{ $template->payment_notes }}</p>
            @endif
        </div>
    @endif

    <p class="footer">
        @if(!empty($invoice['is_payroll']))
            Dokumen ini merupakan bukti bahwa payroll pada nomor slip di atas telah dibayarkan dan dicatat di ledger RF IS.
        @elseif($template->footer_text)
            {{ $template->footer_text }}
        @endif
    </p>
</body>
</html>

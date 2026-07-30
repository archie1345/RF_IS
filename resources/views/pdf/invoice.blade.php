@php
    $isPayroll = !empty($invoice['is_payroll']);
    $isPaid = (float) ($invoice['remaining_amount'] ?? 0) <= 0
        || in_array(strtoupper((string) ($invoice['status'] ?? '')), ['PAID', 'COMPLETED'], true);
    $logoSource = $template->logoImageDataUri() ?: $template->logo_url;
    $documentLabel = $isPayroll ? 'SLIP PAYROLL' : 'INVOICE';
    $statusLabel = $isPayroll
        ? ($isPaid ? 'SUDAH DIBAYAR' : 'BELUM DIBAYAR')
        : ($isPaid ? 'LUNAS' : 'BELUM LUNAS');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['invoice_number'] }}</title>
    <style>
        @page { margin: 22px 28px 30px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 11px;
            line-height: 1.45;
            background: #ffffff;
        }
        .accent { height: 7px; background: #111827; margin-bottom: 22px; }
        .layout { width: 100%; border-collapse: collapse; }
        .layout td { vertical-align: top; }
        .brand-cell { width: 58%; }
        .document-cell { width: 42%; text-align: right; }
        .brand-table { border-collapse: collapse; }
        .brand-table td { vertical-align: middle; }
        .logo-wrap {
            width: 74px;
            height: 74px;
            border: 1px solid #d9deea;
            background: #ffffff;
            text-align: center;
            vertical-align: middle;
            padding: 6px;
        }
        .logo { max-width: 62px; max-height: 62px; }
        .brand-copy { padding-left: 14px; }
        .company-name { margin: 0 0 3px; font-size: 19px; font-weight: 700; color: #111827; }
        .company-tagline { margin: 0 0 7px; font-size: 10px; color: #4b5563; }
        .contact-line { margin: 1px 0; color: #667085; font-size: 9.5px; }
        .document-title { margin: 0; font-size: 25px; font-weight: 700; letter-spacing: 1.5px; color: #111827; }
        .document-number { margin-top: 4px; font-size: 12px; font-weight: 700; color: #344054; }
        .status {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            border: 1px solid {{ $isPaid ? '#15803d' : '#b45309' }};
            color: {{ $isPaid ? '#15803d' : '#b45309' }};
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .8px;
        }
        .meta-table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
            border: 1px solid #e3e7ef;
        }
        .meta-table td { padding: 10px 12px; border-right: 1px solid #e3e7ef; }
        .meta-table td:last-child { border-right: 0; }
        .meta-label { display: block; margin-bottom: 3px; color: #667085; font-size: 8px; text-transform: uppercase; letter-spacing: .7px; }
        .meta-value { font-size: 10.5px; font-weight: 700; color: #1f2937; }
        .section { margin-top: 18px; page-break-inside: avoid; }
        .section-title {
            margin: 0 0 7px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #475467;
        }
        .recipient-card {
            width: 100%;
            border-collapse: collapse;
            background: #f7f8fb;
            border-left: 4px solid #111827;
        }
        .recipient-card td { padding: 12px 14px; }
        .recipient-name { margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #111827; }
        .recipient-detail { margin: 2px 0; color: #667085; }
        .items {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e3e7ef;
        }
        .items th {
            padding: 9px 10px;
            background: #111827;
            color: #ffffff;
            font-size: 8.5px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .items td { padding: 11px 10px; border-bottom: 1px solid #e9ecf2; vertical-align: top; }
        .items tbody tr:last-child td { border-bottom: 0; }
        .right { text-align: right !important; }
        .muted { color: #667085; }
        .description-main { font-weight: 700; color: #1f2937; }
        .description-sub { margin-top: 3px; font-size: 9px; color: #667085; }
        .summary-wrap { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .summary-spacer { width: 54%; }
        .summary-cell { width: 46%; }
        .summary {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dce1ea;
        }
        .summary td { padding: 7px 10px; border-bottom: 1px solid #e9ecf2; }
        .summary tr:last-child td { border-bottom: 0; }
        .summary .grand td {
            padding-top: 10px;
            padding-bottom: 10px;
            background: #f4f6f9;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }
        .notes {
            padding: 12px 14px;
            border: 1px solid #e3e7ef;
            background: #fbfcfe;
            color: #475467;
        }
        .notes p { margin: 4px 0; }
        .closing-table { width: 100%; margin-top: 24px; border-collapse: collapse; }
        .closing-table td { vertical-align: bottom; }
        .verification { width: 62%; color: #667085; font-size: 9px; }
        .issuer { width: 38%; text-align: center; }
        .signature-space { height: 42px; }
        .issuer-line { border-top: 1px solid #aeb6c5; padding-top: 5px; color: #344054; font-weight: 700; }
        .footer {
            margin-top: 22px;
            padding-top: 9px;
            border-top: 1px solid #dce1ea;
            color: #7a8496;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="accent"></div>

    <table class="layout">
        <tr>
            <td class="brand-cell">
                <table class="brand-table">
                    <tr>
                        @if($logoSource)
                            <td class="logo-wrap"><img class="logo" src="{{ $logoSource }}" alt="Logo"></td>
                        @endif
                        <td class="brand-copy">
                            <p class="company-name">{{ $template->company_name ?: 'RF IS' }}</p>
                            @if($template->header_text)
                                <p class="company-tagline">{{ $template->header_text }}</p>
                            @endif
                            @if($template->company_address)
                                <p class="contact-line">{{ $template->company_address }}</p>
                            @endif
                            @if($template->company_phone || $template->company_email)
                                <p class="contact-line">
                                    {{ $template->company_phone ?: '-' }}
                                    @if($template->company_phone && $template->company_email) &nbsp;•&nbsp; @endif
                                    {{ $template->company_email ?: '' }}
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="document-cell">
                <p class="document-title">{{ $documentLabel }}</p>
                <div class="document-number">{{ $invoice['invoice_number'] }}</div>
                <span class="status">{{ $statusLabel }}</span>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">Tanggal {{ $isPayroll ? 'pembayaran' : 'terbit' }}</span>
                <span class="meta-value">{{ $invoice['invoice_date'] }}</span>
            </td>
            @if(!$isPayroll)
                <td>
                    <span class="meta-label">Jatuh tempo</span>
                    <span class="meta-value">{{ $invoice['due_date'] ?? '-' }}</span>
                </td>
            @endif
            @if($isPayroll && !empty($invoice['payroll_period']))
                <td>
                    <span class="meta-label">Periode payroll</span>
                    <span class="meta-value">{{ $invoice['payroll_period'] }}</span>
                </td>
            @endif
            <td>
                <span class="meta-label">Metode</span>
                <span class="meta-value">{{ $invoice['collection_method'] ?? 'TRANSFER' }}</span>
            </td>
            <td>
                <span class="meta-label">Status</span>
                <span class="meta-value">{{ $statusLabel }}</span>
            </td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">{{ $invoice['recipient_label'] ?? ($isPayroll ? 'Dibayarkan kepada' : 'Ditagihkan kepada') }}</p>
        <table class="recipient-card">
            <tr>
                <td>
                    <p class="recipient-name">{{ $invoice['athlete_name'] }}</p>
                    <p class="recipient-detail">{{ $invoice['athlete_email'] }}</p>
                    @if($isPayroll && !empty($invoice['payroll_period']))
                        <p class="recipient-detail">Payroll periode {{ $invoice['payroll_period'] }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Rincian {{ $isPayroll ? 'pembayaran payroll' : 'tagihan' }}</p>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 44%">Deskripsi</th>
                    <th style="width: 31%">Keterangan</th>
                    <th class="right" style="width: 25%">Nominal</th>
                </tr>
            </thead>
            <tbody>
                @if($isPayroll)
                    <tr>
                        <td>
                            <div class="description-main">Honor dasar pelatih</div>
                            <div class="description-sub">{{ $invoice['payroll_basis'] ?? '-' }}</div>
                        </td>
                        <td>
                            @if(!empty($invoice['payroll_units']))
                                {{ rtrim(rtrim(number_format((float) $invoice['payroll_units'], 2, ',', '.'), '0'), ',') }} unit
                            @else
                                -
                            @endif
                            @if(!empty($invoice['payroll_rate']))
                                × Rp {{ number_format((float) $invoice['payroll_rate'], 0, ',', '.') }}
                            @endif
                        </td>
                        <td class="right">Rp {{ number_format((float) ($invoice['payroll_base_amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td><div class="description-main">Bonus pelatih</div></td>
                        <td class="muted">Bonus tambahan pada periode ini</td>
                        <td class="right">Rp {{ number_format((float) ($invoice['payroll_bonus_amount'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @else
                    <tr>
                        <td>
                            <div class="description-main">{{ $invoice['payment_type'] }}</div>
                            @if(!empty($invoice['notes']))
                                <div class="description-sub">{{ $invoice['notes'] }}</div>
                            @endif
                        </td>
                        <td>{{ $statusLabel }}</td>
                        <td class="right">Rp {{ number_format((float) $invoice['total_amount'], 0, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <table class="summary-wrap">
        <tr>
            <td class="summary-spacer"></td>
            <td class="summary-cell">
                <table class="summary">
                    <tr>
                        <td>{{ $isPayroll ? 'Total payroll' : 'Total tagihan' }}</td>
                        <td class="right">Rp {{ number_format((float) $invoice['total_amount'], 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Sudah dibayar</td>
                        <td class="right">Rp {{ number_format((float) $invoice['paid_amount'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="grand">
                        <td>{{ $isPayroll ? 'Sisa pembayaran' : 'Sisa tagihan' }}</td>
                        <td class="right">Rp {{ number_format((float) $invoice['remaining_amount'], 0, ',', '.') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if((!$isPayroll && !empty($template->payment_notes)) || !empty($invoice['notes']))
        <div class="section">
            <p class="section-title">Catatan dan instruksi</p>
            <div class="notes">
                @if(!empty($invoice['notes']) && $isPayroll)
                    <p>{{ $invoice['notes'] }}</p>
                @endif
                @if(!$isPayroll && !empty($template->payment_notes))
                    <p>{{ $template->payment_notes }}</p>
                @endif
            </div>
        </div>
    @endif

    <table class="closing-table">
        <tr>
            <td class="verification">
                @if($isPayroll)
                    Dokumen ini merupakan bukti pembayaran payroll yang tercatat pada sistem RF IS. Nomor dokumen dapat digunakan untuk penelusuran transaksi dan audit internal.
                @else
                    Simpan dokumen ini sebagai referensi pembayaran. Gunakan nomor invoice saat menghubungi admin atau mengirim bukti pembayaran.
                @endif
            </td>
            <td class="issuer">
                <div class="signature-space"></div>
                <div class="issuer-line">{{ $template->company_name ?: 'RF IS' }}</div>
                <div class="muted">Dokumen diterbitkan secara elektronik</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        @if($template->footer_text)
            {{ $template->footer_text }}<br>
        @endif
        {{ $invoice['invoice_number'] }} &nbsp;•&nbsp; Dibuat oleh sistem RF IS
    </div>
</body>
</html>

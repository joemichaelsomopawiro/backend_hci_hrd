<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Cuti</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #111;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .row {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
            flex-wrap: wrap;
        }

        .col {
            width: 48%;
            box-sizing: border-box;
            padding-bottom: 8px;
        }

        .sig {
            height: 72px;
            margin: 6px 0 2px;
        }

        .u {
            text-decoration: underline;
        }

        .header-table {
            width: 100%;
            border-bottom: 2px solid #222;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-logo {
            width: 20%;
            text-align: left;
            vertical-align: middle;
        }

        .header-title {
            width: 60%;
            text-align: center;
            font-size: 12pt;
            line-height: 1.3;
            vertical-align: middle;
        }

        .header-empty {
            width: 20%;
            vertical-align: middle;
        }

        .header-logo img {
            height: 60px;
        }

        .section {
            margin-top: 12px;
        }

        .spacer {
            height: 6px;
        }

        .footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            font-size: 10pt;
            text-align: center;
            color: #333;
        }

        .label {
            display: inline-block;
            min-width: 120px;
        }

        /* Pastikan tinggi area sebelum tanda tangan sama agar posisi tanda tangan sejajar kiri-kanan */
        .pre {
            height: 36px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        /* Tabel khusus tanda tangan - lebih stabil untuk DOMPDF */
        .sig-table {
            width: 100%;
            margin-top: 56px;
            border-collapse: collapse;
        }

        .sig-table td {
            width: 50%;
            vertical-align: top;
            padding-top: 0;
            padding-bottom: 0;
        }

        .sig-table td.left {
            text-align: left;
            padding-right: 14px;
        }

        .sig-table td.right {
            text-align: right;
            padding-left: 14px;
        }
    </style>
</head>

<body>
    <table class="header-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="header-logo">
                @if(!empty($company_logo_data_uri))
                    <img src="{{ $company_logo_data_uri }}" alt="Logo" />
                @endif
            </td>
            <td class="header-title">
                <div style="font-weight:700;">{{ $company_name }}</div>
                @if(!empty($company_address_lines))
                    @foreach($company_address_lines as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                @endif
            </td>
            <td class="header-empty"></td>
        </tr>
    </table>

    <div class="right">{{ $city }}, {{ $letter_date }}</div>
    <h3 class="center u" style="margin: 4px 0 12px;">SURAT PERMOHONAN CUTI TAHUNAN</h3>

    <p>Yang bertandatangan di bawah ini:</p>
    <ol style="margin:0; padding-left:18px;">
        <li>Nama: {{ $employee_name }}</li>
        <li>Jabatan: {{ $employee_position }}</li>
    </ol>

    <p>
        Dengan ini mengajukan permintaan cuti {{ $leave_type === 'annual' ? 'tahunan' : $leave_type }} untuk tahun
        {{ $year }}
        selama {{ $total_days }} hari kerja, terhitung mulai tanggal {{ $date_range_text }}.
    </p>
    <p>Selama menjalankan cuti saya berada di {{ $leave_location ?? '-' }} dan nomor telepon yang bisa dihubungi adalah
        {{ $emergency_contact ?? ($contact_phone ?? '-') }}.</p>

    <table class="sig-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="left">
                <div>Mengetahui,</div>
                <div>{{ $approver_position }}</div>
                <div class="spacer"></div>
                @if(!empty($approver_signature_data_uri))
                    <img class="sig" src="{{ $approver_signature_data_uri }}" alt="Tanda Tangan Atasan" />
                @else
                    <div class="sig"></div>
                @endif
                <div style="margin-top: 0;">{{ $approver_name }}</div>
            </td>
            <td class="right">
                <div>Hormat saya,</div>
                <div class="spacer"></div>
                @if(!empty($employee_signature_data_uri))
                    <img class="sig" src="{{ $employee_signature_data_uri }}" alt="Tanda Tangan Pegawai" />
                @else
                    <div class="sig"></div>
                @endif
                <div style="margin-top: 0;">{{ $employee_name }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">© {{ date('Y') }} {{ $company_name }}</div>
</body>

</html>
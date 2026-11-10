<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>E-Slip {{ $companyName }}</title></head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #1e293b;">
    <p>Dear {{ $name }},</p>
    <p>Berikut E-Slip Gaji Bulan {{ $period }}.</p>
    @if ($passwordHint)
        <p>{{ $passwordHint }}</p>
    @endif
    <p>Terima kasih,<br>{{ $companyName }}</p>
</body>
</html>

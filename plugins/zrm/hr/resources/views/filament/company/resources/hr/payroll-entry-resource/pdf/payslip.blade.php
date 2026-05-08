<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip {{ $record->entry_number }}</title>
</head>
<body style="margin: 24px; font-family: Arial, sans-serif; color: #111827;">
    @include('erpsaas-hr::filament.company.resources.hr.payroll-entry-resource.partials.payslip-content', [
        'record' => $record,
        'payslip' => $payslip,
    ])
</body>
</html>

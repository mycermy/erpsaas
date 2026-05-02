@php
    $employeeName = trim((string) (($record->employee?->contact?->first_name ?? '') . ' ' . ($record->employee?->contact?->last_name ?? '')));
    $employeeNumber = $record->employee?->employee_number ?? '-';
    $structureName = $record->salaryStructure?->name ?? '-';
    $jobTitle = $record->employee?->job_title ?? null;
    $department = $record->employee?->department ?? null;

    $earningTypes = ['base_salary', 'addition', 'overtime', 'bonus', 'commission', 'reimbursement', 'other'];
    $earnings = collect($payslip['lines'])->filter(fn ($l) => in_array($l['type'], $earningTypes))->values();
    $deductions = collect($payslip['lines'])->filter(fn ($l) => $l['type'] === 'deduction')->values();
    $employerCosts = collect($payslip['lines'])->filter(fn ($l) => $l['type'] === 'employer_cost')->values();

    $maxRows = max($earnings->count(), $deductions->count(), 1);

    $fromDate = \Carbon\Carbon::parse($record->from_date)->format('d M Y');
    $toDate = \Carbon\Carbon::parse($record->to_date)->format('d M Y');
@endphp

<div style="font-family: Arial, sans-serif; color: #111827; font-size: 12px;">

    {{-- Header --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 0;">
        <tr>
            <td style="background: #1e3a5f; padding: 12px 16px; vertical-align: middle;">
                <div style="color: #ffffff; font-size: 18px; font-weight: bold; letter-spacing: 1px;">PAYSLIP</div>
                <div style="color: #93c5fd; font-size: 11px; margin-top: 2px;">{{ \Carbon\Carbon::parse($record->from_date)->format('F Y') }}</div>
            </td>
            <td style="background: #1e3a5f; padding: 12px 16px; text-align: right; vertical-align: middle;">
                <div style="color: #ffffff; font-size: 11px;">{{ $record->entry_number }}</div>
                <div style="color: #93c5fd; font-size: 10px; margin-top: 2px;">Generated: {{ now()->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- Employee Info --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 12px;">
        <tr>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 20%; background: #f1f5f9; font-weight: bold;">Employee</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 30%;">{{ $employeeName !== '' ? $employeeName : '-' }}</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 20%; background: #f1f5f9; font-weight: bold;">Employee No.</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 30%;">{{ $employeeNumber }}</td>
        </tr>
        @if ($jobTitle || $department)
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #e5e7eb; background: #f1f5f9; font-weight: bold;">Job Title</td>
                <td style="padding: 6px 10px; border: 1px solid #e5e7eb;">{{ $jobTitle ?? '-' }}</td>
                <td style="padding: 6px 10px; border: 1px solid #e5e7eb; background: #f1f5f9; font-weight: bold;">Department</td>
                <td style="padding: 6px 10px; border: 1px solid #e5e7eb;">{{ $department ?? '-' }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; background: #f1f5f9; font-weight: bold;">Salary Structure</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb;">{{ $structureName }}</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb; background: #f1f5f9; font-weight: bold;">Pay Period</td>
            <td style="padding: 6px 10px; border: 1px solid #e5e7eb;">{{ $fromDate }} – {{ $toDate }}</td>
        </tr>
    </table>

    {{-- Earnings & Deductions Side by Side --}}
    <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 0;">
        <thead>
            <tr>
                <th colspan="2" style="padding: 7px 10px; background: #166534; color: #ffffff; text-align: left; border: 1px solid #15803d; width: 50%;">EARNINGS</th>
                <th colspan="2" style="padding: 7px 10px; background: #991b1b; color: #ffffff; text-align: left; border: 1px solid #b91c1c; width: 50%;">DEDUCTIONS</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                    <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 35%;">
                        {{ $earnings[$i]['name'] ?? '' }}
                    </td>
                    <td style="padding: 6px 10px; border: 1px solid #e5e7eb; text-align: right; width: 15%;">
                        @if (isset($earnings[$i]))
                            RM {{ number_format($earnings[$i]['amount'], 2) }}
                        @endif
                    </td>
                    <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 35%;">
                        {{ $deductions[$i]['name'] ?? '' }}
                    </td>
                    <td style="padding: 6px 10px; border: 1px solid #e5e7eb; text-align: right; width: 15%;">
                        @if (isset($deductions[$i]))
                            RM {{ number_format($deductions[$i]['amount'], 2) }}
                        @endif
                    </td>
                </tr>
            @endfor
            {{-- Subtotals row --}}
            <tr>
                <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #f0fdf4; font-weight: bold;">Gross Earnings</td>
                <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #f0fdf4; text-align: right; font-weight: bold;">RM {{ number_format($payslip['gross'], 2) }}</td>
                <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #fef2f2; font-weight: bold;">Total Deductions</td>
                <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #fef2f2; text-align: right; font-weight: bold;">RM {{ number_format($payslip['deductions'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($employerCosts->isNotEmpty())
        {{-- Employer Cost (informational) --}}
        <table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 12px;">
            <thead>
                <tr>
                    <th colspan="2" style="padding: 7px 10px; background: #1e40af; color: #ffffff; text-align: left; border: 1px solid #1d4ed8;">EMPLOYER CONTRIBUTIONS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employerCosts as $cost)
                    <tr>
                        <td style="padding: 6px 10px; border: 1px solid #e5e7eb; width: 70%;">{{ $cost['name'] }}</td>
                        <td style="padding: 6px 10px; border: 1px solid #e5e7eb; text-align: right; width: 30%;">RM {{ number_format($cost['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #eff6ff; font-weight: bold;">Total Employer Cost</td>
                    <td style="padding: 7px 10px; border: 1px solid #d1d5db; background: #eff6ff; text-align: right; font-weight: bold;">RM {{ number_format($payslip['employer_cost'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Net Salary Footer --}}
    <table style="width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px;">
        <tr>
            <td style="padding: 11px 16px; background: #1e3a5f; color: #ffffff; font-weight: bold; letter-spacing: 0.5px;">NET SALARY PAYABLE</td>
            <td style="padding: 11px 16px; background: #1e3a5f; color: #ffffff; text-align: right; font-weight: bold; font-size: 14px;">RM {{ number_format($payslip['net'], 2) }}</td>
        </tr>
    </table>

</div>

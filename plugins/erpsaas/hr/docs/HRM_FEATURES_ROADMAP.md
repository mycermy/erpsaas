# HRM Features Roadmap

---

## Phase 1: Payroll System ✅ COMPLETE

**Status:** Production-ready as of 2026-05-04  
**Tests:** 33/33 passing (186 assertions)  
**Documentation:** See [PAYROLL.md](PAYROLL.md)

### Completed Features

- ✅ Bill-based payroll processing with journal entries
- ✅ Reusable salary structures (3 templates: Standard, Senior, Management)
- ✅ Employee advances tracking with recovery mechanism
- ✅ Dedicated payroll liabilities account (Account 165 - Payroll Statutory Payable)
- ✅ Payroll dashboard integration (expenses, payables, widgets)
- ✅ Realistic demo seeding (~70% paid bills, 3 employees, 9 payroll entries)

**Key Data:**
- 3 Employees, 3 Salary Structures, 9 Payroll Bills
- 9 Balanced Journal Entries, 4 Employee Advances
- Payroll Statutory Payable, Employee Advances Receivable accounts

See [PAYROLL.md](PAYROLL.md) for complete technical documentation.

---

## Phase 2: Leave Management (PROPOSED)

**Status:** Design phase  
**Priority:** High - Essential for startups  
**Effort:** 3-4 weeks  
**Impact:** Enables leave compliance, payslip visibility

### Overview

A comprehensive leave management system supporting multiple leave types, balance tracking, requests with approval workflows, and leave balance display on payslips.

### Core Features

#### 1. Leave Types & Configuration

```php
// LeaveType model with configurable rules
class LeaveType {
    string: name              // e.g., "Annual Leave", "Sick Leave"
    string: code              // e.g., "AL", "SL"
    int: annual_entitlement   // Days per year (e.g., 20, 12)
    bool: paid                // Whether leave is paid or unpaid
    bool: requires_approval   // Auto-approved or manual review
    bool: carries_forward     // Can unused days roll over?
    int: max_carryover        // Max days that can roll over
    date: effective_from      // When this type starts
    date: effective_to        // When this type ends (for archived types)
}

// Configured leave types for Malaysian startup:
- Annual Leave (AL): 20 days/year, paid, approval required
- Sick Leave (SL): 10 days/year, paid, auto-approved (with medical cert)
- Compassionate Leave (CL): 3 days/occurrence, paid, approval required
- Unpaid Leave (UL): unlimited, unpaid, approval required
- Maternity Leave (ML): 60 days, paid, auto-approved
- Paternity Leave (PL): 3-7 days, paid, varies by policy
```

#### 2. Leave Balance Tracking

```php
// LeaveBalance model - employee leave entitlement per year
class LeaveBalance {
    int: employee_id
    int: leave_type_id
    int: year                 // 2026
    int: opening_balance      // From previous year carryover
    int: entitlement          // This year's allocation
    int: used                 // Days used this year
    int: pending              // Days in pending requests
    int: available            // entitlement + opening - used - pending
    date: as_of               // Snapshot date
}

// Example:
// Employee: Nur Aisyah
// Leave Type: Annual Leave (2026)
// Opening: 5 days (carried over from 2025)
// Entitlement: 20 days (2026 allocation)
// Used: 8 days
// Pending: 3 days (submitted, awaiting approval)
// Available: 5 + 20 - 8 - 3 = 14 days
```

#### 3. Leave Request Workflow

```php
// LeaveRequest model with approval flow
class LeaveRequest {
    int: employee_id
    int: leave_type_id
    date: start_date
    date: end_date
    int: days_requested       // Calculated from start/end (excluding weekends/holidays)
    string: reason            // Optional reason for leave
    string: status            // pending, approved, rejected, cancelled
    
    datetime: requested_at
    datetime: approved_at
    int: approved_by_user_id  // Manager who approved
    text: approval_notes
    
    // For document upload
    string: attachment_path   // Path to medical cert, police report, etc.
}

// Approval Workflow:
1. Employee submits leave request
2. System calculates days (excluding weekends & public holidays)
3. Manager receives notification
4. Manager reviews & approves/rejects with notes
5. System updates leave balance on approval
6. Employee receives confirmation email
7. Payroll system reads approved leaves for payslip
```

#### 4. Public Holidays & Exclusions

```php
// PublicHoliday model
class PublicHoliday {
    int: company_id
    date: holiday_date
    string: name              // e.g., "Awal Ramadan"
    string: country           // e.g., "MY"
    bool: is_weekend_adjacent // Adjacent to weekend?
}

// Weekend configuration
config('hr.weekends') => ['Saturday', 'Sunday']

// Leave calculation: Exclude weekends AND public holidays
// 2026-03-01 (Sunday) to 2026-03-05 (Thursday)
// = Wed, Thu only = 2 days (exclude weekend + public holiday if any)
```

#### 5. Leave Balance on Payslip

```php
// PayslipItem - new line item for leave balance display
class PayslipItem {
    int: payroll_entry_id
    string: label              // e.g., "Leave Balance"
    string: value              // e.g., "14 days"
    int: display_order         // Where on slip
    bool: is_total             // Is this a subtotal row?
}

// Example payslip section:
/*
=== LEAVE INFORMATION ===
Annual Leave Balance:     14.0 days
  - Entitlement:        20.0 days
  - Used This Year:      6.0 days
  - Pending Requests:    0.0 days

Sick Leave Balance:       9.5 days
Compassionate Leave:      3.0 days (reset annually)
*/
```

#### 6. Leave Accrual (Advanced - Priority 2)

```php
// Accrual for pro-rated leaves or monthly allocation
class LeaveAccrual {
    int: employee_id
    int: leave_type_id
    int: year
    
    // Monthly accrual
    int: monthly_accrual      // e.g., 1.67 days/month for 20 days/year
    datetime: last_accrual_date
    
    // Calculation:
    // For 20 days annual = 20/12 = 1.67 days/month
    // Accrual runs on 1st of each month
    // May 1 → +1.67 days to June 1 availability
}
```

### Database Schema

```php
Schema::create('leave_types', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');        // "Annual Leave"
    $table->string('code', 10);    // "AL"
    $table->integer('annual_entitlement');  // 20
    $table->boolean('paid')->default(true);
    $table->boolean('requires_approval')->default(true);
    $table->boolean('carries_forward')->default(false);
    $table->integer('max_carryover')->default(0);
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->timestamps();
    $table->unique(['company_id', 'code']);
});

Schema::create('leave_balances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
    $table->year('year');
    $table->integer('opening_balance')->default(0);  // Carryover
    $table->integer('entitlement');                  // This year's allocation
    $table->integer('used')->default(0);             // Days taken
    $table->integer('pending')->default(0);          // Pending approval
    $table->timestamps();
    $table->unique(['employee_id', 'leave_type_id', 'year']);
});

Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date');
    $table->integer('days_requested');
    $table->text('reason')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
    $table->timestamp('requested_at');
    $table->timestamp('approved_at')->nullable();
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->text('approval_notes')->nullable();
    $table->string('attachment_path')->nullable();
    $table->timestamps();
});

Schema::create('public_holidays', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->date('holiday_date');
    $table->string('name');
    $table->string('country', 10)->default('MY');
    $table->boolean('is_weekend_adjacent')->default(false);
    $table->timestamps();
    $table->unique(['company_id', 'holiday_date']);
});
```

### Models

```php
// LeaveType.php
class LeaveType extends Model {
    public function balances() { return $this->hasMany(LeaveBalance::class); }
    public function requests() { return $this->hasMany(LeaveRequest::class); }
}

// LeaveBalance.php
class LeaveBalance extends Model {
    protected $casts = ['year' => 'integer'];
    
    public function employee() { return $this->belongsTo(Employee::class); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
    
    public function getAvailableDaysAttribute(): int {
        return $this->entitlement + $this->opening_balance - $this->used - $this->pending;
    }
}

// LeaveRequest.php
class LeaveRequest extends Model {
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
    
    public function employee() { return $this->belongsTo(Employee::class); }
    public function leaveType() { return $this->belongsTo(LeaveType::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    
    public function approve(User $by, string $notes = ''): void {
        $this->status = 'approved';
        $this->approved_by = $by->id;
        $this->approval_notes = $notes;
        $this->approved_at = now();
        $this->save();
        
        // Update leave balance
        $balance = LeaveBalance::firstOrCreate(
            ['employee_id' => $this->employee_id, 'leave_type_id' => $this->leave_type_id, 'year' => $this->start_date->year],
            ['entitlement' => $this->leaveType->annual_entitlement]
        );
        $balance->used += $this->days_requested;
        $balance->pending -= $this->days_requested;
        $balance->save();
    }
}

// PublicHoliday.php
class PublicHoliday extends Model {
    protected $casts = ['holiday_date' => 'date'];
    
    public function company() { return $this->belongsTo(Company::class); }
}
```

### Calculation Logic

```php
// Helper service for leave calculations
class LeaveCalculationService {
    public function calculateLeaveDays(Carbon $startDate, Carbon $endDate): int {
        $days = 0;
        $current = $startDate->copy();
        
        while ($current <= $endDate) {
            // Skip weekends
            if (!$current->isWeekend()) {
                // Skip public holidays
                if (!PublicHoliday::whereDate('holiday_date', $current->toDateString())->exists()) {
                    $days++;
                }
            }
            $current->addDay();
        }
        
        return $days;
    }
    
    public function getAvailableBalance(Employee $employee, LeaveType $leaveType, int $year): int {
        $balance = LeaveBalance::firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'year' => $year],
            ['entitlement' => $leaveType->annual_entitlement]
        );
        
        return $balance->available;  // Via computed attribute
    }
}
```

### Filament UI

```php
// LeaveRequestResource.php - Simple request form
class LeaveRequestResource extends Resource {
    public static function form(Form $form): Form {
        return $form->schema([
            Select::make('leave_type_id')
                ->label('Leave Type')
                ->relationship('leaveType')
                ->required(),
            
            DatePicker::make('start_date')
                ->label('From')
                ->required(),
            
            DatePicker::make('end_date')
                ->label('To')
                ->required()
                ->afterStateUpdated(function ($state, Set $set) {
                    // Auto-calculate days_requested
                    if ($set('start_date')) {
                        $days = app(LeaveCalculationService::class)
                            ->calculateLeaveDays($set('start_date'), $state);
                        $set('days_requested', $days);
                    }
                }),
            
            TextInput::make('days_requested')
                ->label('Days Requested')
                ->disabled(),
            
            Textarea::make('reason')
                ->label('Reason (Optional)')
                ->rows(3),
            
            FileUpload::make('attachment_path')
                ->label('Supporting Document')
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(5120),  // 5MB
        ]);
    }
}

// LeaveApprovalResource.php - Manager approval view
class LeaveApprovalResource extends Resource {
    public static function table(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('employee.name'),
                TextColumn::make('leaveType.name')->label('Leave Type'),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date(),
                TextColumn::make('days_requested'),
                BadgeColumn::make('status')
                    ->colors(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger']),
            ])
            ->actions([
                Action::make('approve')
                    ->form([
                        Textarea::make('approval_notes')->label('Notes'),
                    ])
                    ->action(function (LeaveRequest $record, array $data) {
                        $record->approve(auth()->user(), $data['approval_notes']);
                    }),
                
                Action::make('reject')
                    ->form([
                        Textarea::make('approval_notes')->label('Reason for rejection'),
                    ])
                    ->action(function (LeaveRequest $record, array $data) {
                        $record->status = 'rejected';
                        $record->approval_notes = $data['approval_notes'];
                        $record->save();
                    }),
            ]);
    }
}
```

### Testing

```php
// LeaveRequestTest.php
it('calculates leave days excluding weekends and public holidays', function () {
    $startDate = Carbon::parse('2026-03-02');  // Monday
    $endDate = Carbon::parse('2026-03-06');    // Friday
    
    PublicHoliday::create([
        'company_id' => 1,
        'holiday_date' => '2026-03-04',  // Wednesday (public holiday)
        'name' => 'Test Holiday',
    ]);
    
    $days = app(LeaveCalculationService::class)->calculateLeaveDays($startDate, $endDate);
    
    // Mon, Tue, Thu, Fri = 4 days (exclude Wed which is public holiday)
    expect($days)->toBe(4);
});

it('updates leave balance when request is approved', function () {
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['annual_entitlement' => 20]);
    
    $balance = LeaveBalance::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'year' => 2026,
        'entitlement' => 20,
        'used' => 0,
    ]);
    
    $request = LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-06',
        'days_requested' => 5,
    ]);
    
    $request->approve(User::factory()->create());
    
    $balance->refresh();
    expect($balance->used)->toBe(5);
});
```

---

## Phase 3: Time Clock & Attendance (PROPOSED)

**Status:** Design phase  
**Priority:** High - Critical for tracking attendance  
**Effort:** 4-5 weeks  
**Impact:** Enables accurate time tracking, payroll compliance, overtime calculation

### Overview

A location-aware attendance system with QR code scanning, geo-fencing to prevent remote clock-ins, and attendance reconciliation for accurate payroll records.

### Core Features

#### 1. Attendance Scanning Setup

```php
// AttendanceLocation model - work premises/locations
class AttendanceLocation {
    string: name                   // e.g., "Kuala Lumpur HQ"
    float: latitude                // 3.1390
    float: longitude               // 101.6869
    float: geofence_radius         // meters (e.g., 500m)
    bool: qr_scanning_enabled      // Allow QR-based scanning?
    string: qr_code                // Unique QR code for location
    string: status                 // active, inactive
}

// AttendanceSchedule model - employee shift
class AttendanceSchedule {
    int: employee_id
    int: location_id
    date: effective_from
    date: effective_to
    string: shift                  // "morning", "afternoon", "flexible"
    time: check_in_time            // Expected time (e.g., 09:00)
    time: check_out_time           // Expected time (e.g., 17:00)
    int: work_hours                // 8, 9, etc.
}

// Example:
// Nur Aisyah
// Location: Kuala Lumpur HQ (geofence 500m, QR enabled)
// Schedule: Morning shift, 09:00-17:00 (8 hours)
// Effective: 2026-01-01 onwards
```

#### 2. Clock In/Out with QR

```php
// Attendance model - actual attendance record
class Attendance {
    int: employee_id
    int: location_id
    date: attendance_date
    
    datetime: check_in_time         // Actual check-in
    float: check_in_latitude
    float: check_in_longitude
    float: distance_from_geofence   // meters (0 = at location)
    bool: check_in_valid            // Within geofence?
    
    datetime: check_out_time        // Actual check-out
    float: check_out_latitude
    float: check_out_longitude
    bool: check_out_valid           // Within geofence?
    
    int: work_minutes               // Actual worked time
    string: status                  // present, absent, late, early_leave
}

// Example flow:
/*
1. Employee scans QR code from phone at office entrance
2. System captures GPS location
3. Checks: Is employee within geofence (500m)?
   - YES → Record check-in, mark as valid
   - NO → Reject, show error "You are not at work location"
4. At end of day, employee scans QR again
5. System records check-out, validates location
6. Calculates work_minutes and status (on-time, late, etc.)
*/
```

#### 3. Geofence Validation (Critical)

```php
// Geofence validation logic
class GeofenceService {
    public function isWithinGeofence(
        AttendanceLocation $location,
        float $latitude,
        float $longitude
    ): bool {
        $distance = $this->calculateDistance(
            $location->latitude,
            $location->longitude,
            $latitude,
            $longitude
        );
        
        return $distance <= $location->geofence_radius;
    }
    
    private function calculateDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2
    ): float {
        // Haversine formula for distance in meters
        $earthRadius = 6371000;  // meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;  // Distance in meters
    }
}

// Protection: If employee tries to clock from home
/*
Scenario: Employee at home (10km away) tries to scan QR
1. System receives: Employee ID, Location ID, GPS coords
2. Calculates distance to geofence: 10,000m
3. Geofence radius: 500m
4. 10,000m > 500m → NOT VALID
5. Response: "You are not at authorized work location. 
             Distance: 10.0 km"
6. Clock-in REJECTED, not recorded
7. Notification sent to manager

Result: No attendance record created. Employee must be
        physically present to clock in successfully.
*/
```

#### 4. Attendance UI - Restricted Menu

```php
// AttendanceResource - Only shows scan menu if valid conditions
class AttendanceResource extends Resource {
    public static function canAccessAny(): bool {
        // Only show if:
        // 1. Employee is assigned a schedule for today
        // 2. Employee has active attendance location
        // 3. Time is within working hours (with 30min early buffer)
        
        $user = auth()->user();
        $employee = $user->employee;
        
        if (!$employee) return false;
        
        $schedule = AttendanceSchedule::where('employee_id', $employee->id)
            ->where('effective_from', '<=', today())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', today());
            })
            ->first();
        
        if (!$schedule) return false;
        
        // Check time window (30min early, working hours)
        $now = now();
        $checkInStart = $schedule->check_in_time->subMinutes(30);
        $checkOutEnd = $schedule->check_out_time->addMinutes(30);
        
        return $now >= $checkInStart && $now <= $checkOutEnd;
    }
}

// Scan attendance page - restricted action
class AttendanceResource {
    public static function getPages(): array {
        return [
            'scan' => Pages\ScanAttendance::route('/scan'),
        ];
    }
}

// Pages/ScanAttendance.php - The actual scanning interface
class ScanAttendance extends Page {
    public ?string $scanResult = null;
    public string $qrCode = '';
    
    public function mount() {
        $employee = auth()->user()->employee;
        $today = today();
        
        // Get today's schedule
        $schedule = AttendanceSchedule::where('employee_id', $employee->id)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            })
            ->first();
        
        if (!$schedule) {
            redirect()->back()->with('error', 'No attendance schedule for today');
        }
        
        $this->location = $schedule->location;
        $this->schedule = $schedule;
    }
    
    public function scanQr() {
        // Receives QR code scanned from phone/device
        // Includes GPS location from device
        
        $employee = auth()->user()->employee;
        $location = $this->location;
        
        // Get device GPS location
        $latitude = request('latitude');   // From device GPS
        $longitude = request('longitude');
        
        // Validate geofence
        $geofence = app(GeofenceService::class);
        if (!$geofence->isWithinGeofence($location, $latitude, $longitude)) {
            $this->scanResult = 'error';
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'You are not at the authorized work location'
            ]);
            return;
        }
        
        // Check if already clocked in today
        $today = today();
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();
        
        if (!$attendance) {
            // First scan of day = CHECK-IN
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'location_id' => $location->id,
                'attendance_date' => $today,
                'check_in_time' => now(),
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'check_in_valid' => true,
                'distance_from_geofence' => 0,
                'status' => $this->calculateStatus($today, now(), $this->schedule),
            ]);
            
            $this->scanResult = 'checked_in';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Checked in at ' . now()->format('H:i')
            ]);
        } else if (!$attendance->check_out_time) {
            // Already clocked in = CHECK-OUT
            $attendance->update([
                'check_out_time' => now(),
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_valid' => true,
                'work_minutes' => now()->diffInMinutes($attendance->check_in_time),
            ]);
            
            $this->scanResult = 'checked_out';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Checked out at ' . now()->format('H:i')
            ]);
        } else {
            // Already checked out - show message
            $this->scanResult = 'already_out';
        }
    }
}
```

#### 5. Attendance Reconciliation

```php
// AttendanceReconciliation job - runs daily
class ReconcileAttendance {
    public function handle() {
        $yesterday = today()->subDay();
        
        // Get all employees with schedules for yesterday
        $schedules = AttendanceSchedule::whereDate('effective_from', '<=', $yesterday)
            ->where(function ($q) use ($yesterday) {
                $q->whereNull('effective_to')
                  ->orWhereDate('effective_to', '>=', $yesterday);
            })
            ->get();
        
        foreach ($schedules as $schedule) {
            $attendance = Attendance::where('employee_id', $schedule->employee_id)
                ->where('attendance_date', $yesterday)
                ->first();
            
            if (!$attendance) {
                // No clock-in = ABSENT
                Attendance::create([
                    'employee_id' => $schedule->employee_id,
                    'location_id' => $schedule->location_id,
                    'attendance_date' => $yesterday,
                    'status' => 'absent',
                ]);
            } else if (!$attendance->check_out_time) {
                // Clocked in but not out = Assume 8-hour day
                $attendance->update([
                    'check_out_time' => $attendance->check_in_time->addHours(8),
                    'work_minutes' => 480,
                    'status' => 'present',
                ]);
            } else {
                // Valid check-in and check-out
                $this->updateStatus($attendance, $schedule);
            }
        }
    }
    
    private function updateStatus(Attendance $attendance, AttendanceSchedule $schedule) {
        $checkInTime = $attendance->check_in_time;
        $expectedTime = Carbon::parse($schedule->check_in_time);
        
        if ($checkInTime->greaterThan($expectedTime->addMinutes(15))) {
            $attendance->status = 'late';
        } else if ($attendance->check_out_time->lessThan(
            Carbon::parse($schedule->check_out_time)->subMinutes(15)
        )) {
            $attendance->status = 'early_leave';
        } else {
            $attendance->status = 'present';
        }
        
        $attendance->save();
    }
}
```

#### 6. Overtime Calculation

```php
// OvertimeCalculation - for payroll integration
class OvertimeCalculationService {
    public function calculateOvertime(Employee $employee, int $month, int $year): int {
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->where('status', '!=', 'absent')
            ->get();
        
        $overtimeMinutes = 0;
        
        foreach ($attendances as $attendance) {
            $schedule = $attendance->schedule;
            $expectedMinutes = $schedule->work_hours * 60;
            $actualMinutes = $attendance->work_minutes;
            
            if ($actualMinutes > $expectedMinutes) {
                $overtimeMinutes += ($actualMinutes - $expectedMinutes);
            }
        }
        
        return ceil($overtimeMinutes / 60);  // Convert to hours, round up
    }
}

// Integration with PayrollEntry
class PayrollEntry {
    public function calculateOvertimeCharge(): decimal {
        $overtimeHours = app(OvertimeCalculationService::class)
            ->calculateOvertime(
                $this->employee,
                $this->from_date->month,
                $this->from_date->year
            );
        
        // Assuming overtime rate is 1.5x salary per hour
        $hourlyRate = ($this->employee->baseSalary() / 22 / 8);  // 22 working days, 8 hours
        
        return $overtimeHours * $hourlyRate * 1.5 * 100;  // In minor currency units
    }
}
```

### Database Schema

```php
Schema::create('attendance_locations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->integer('geofence_radius');  // meters
    $table->boolean('qr_scanning_enabled')->default(true);
    $table->string('qr_code')->unique();  // Generated UUID
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});

Schema::create('attendance_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('location_id')->constrained('attendance_locations')->cascadeOnDelete();
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->enum('shift', ['morning', 'afternoon', 'flexible', 'custom']);
    $table->time('check_in_time');
    $table->time('check_out_time');
    $table->integer('work_hours');  // 8, 9, etc.
    $table->timestamps();
});

Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('location_id')->constrained('attendance_locations')->cascadeOnDelete();
    $table->date('attendance_date');
    
    $table->timestamp('check_in_time')->nullable();
    $table->decimal('check_in_latitude', 10, 8)->nullable();
    $table->decimal('check_in_longitude', 11, 8)->nullable();
    $table->integer('distance_from_geofence')->nullable();  // meters
    $table->boolean('check_in_valid')->nullable();
    
    $table->timestamp('check_out_time')->nullable();
    $table->decimal('check_out_latitude', 10, 8)->nullable();
    $table->decimal('check_out_longitude', 11, 8)->nullable();
    $table->boolean('check_out_valid')->nullable();
    
    $table->integer('work_minutes')->nullable();
    $table->enum('status', ['present', 'absent', 'late', 'early_leave'])->default('present');
    
    $table->timestamps();
    $table->unique(['employee_id', 'attendance_date']);
});
```

### Testing

```php
// AttendanceTest.php
it('prevents clock-in from outside geofence', function () {
    $location = AttendanceLocation::factory()->create([
        'latitude' => 3.1390,
        'longitude' => 101.6869,
        'geofence_radius' => 500,  // 500 meters
    ]);
    
    // Employee at home (10km away)
    $homeLatitude = 3.2000;    // Different coordinates
    $homeLongitude = 101.5000;
    
    $geofence = app(GeofenceService::class);
    $valid = $geofence->isWithinGeofence($location, $homeLatitude, $homeLongitude);
    
    expect($valid)->toBeFalse();
});

it('records valid attendance when within geofence', function () {
    $employee = Employee::factory()->create();
    $location = AttendanceLocation::factory()->create();
    $schedule = AttendanceSchedule::factory()->create([
        'employee_id' => $employee->id,
        'location_id' => $location->id,
        'effective_from' => today(),
    ]);
    
    $this->actingAs($employee->user)
        ->postJson('/api/attendance/scan', [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ])
        ->assertSuccessful();
    
    expect(Attendance::where('employee_id', $employee->id)
        ->where('attendance_date', today())
        ->first())->not->toBeNull();
});

it('calculates late status when check-in is after scheduled time', function () {
    $employee = Employee::factory()->create();
    $location = AttendanceLocation::factory()->create();
    $schedule = AttendanceSchedule::factory()->create([
        'employee_id' => $employee->id,
        'check_in_time' => '09:00',
        'effective_from' => today(),
    ]);
    
    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'attendance_date' => today(),
        'check_in_time' => now()->setTime(09, 30),  // 30 minutes late
    ]);
    
    app(ReconcileAttendance::class)->handle();
    
    $attendance->refresh();
    expect($attendance->status)->toBe('late');
});
```

---

## Phase 4: Other Essential HRM Features (PROPOSED)

**Status:** Backlog  
**Priority:** Medium-High  
**Timeline:** Phases after leave & attendance  

### 4.1 Employee Directory & Organization Chart

**Features:**
- Employee contact directory (phone, email, address)
- Department and role hierarchy
- Reporting structure (who reports to whom)
- Team grouping and assignments
- Emergency contacts
- Skills & certifications tracking
- Organization chart visualization

**Impact:** Enables HR management, communication, org visibility

**Effort:** 2-3 weeks

---

### 4.2 Document Management

**Features:**
- Contract storage (employment agreements, offer letters)
- Policy documents
- NDA, Non-compete agreements
- Training certificates
- Medical/health records (confidential)
- Document signing workflow (e-signature)
- Document version control
- Audit trail of access

**Impact:** Compliance, legal protection, record keeping

**Effort:** 3-4 weeks

---

### 4.3 Simple Performance Appraisal System

**Features:**
- Annual performance evaluation forms
- Goal setting (OKRs, KPIs)
- 360-degree feedback (optional)
- Rating scales and scoring
- Appraisal history/tracking
- Department/team analytics
- Promotion recommendations

**Impact:** Career development, fairness, documentation

**Effort:** 3-4 weeks

---

### 4.4 Employee Onboarding Workflow

**Features:**
- Pre-boarding checklist (document collection, IT setup)
- Onboarding tasks (training, orientation, setup)
- Department-specific checklists
- Manager assignment
- Mentor/buddy assignment
- 30/60/90 day checkpoints
- Completion tracking
- Offboarding workflow (reverse process)

**Impact:** Smooth onboarding, reduced time-to-productivity

**Effort:** 2-3 weeks

---

### 4.5 Benefits Administration

**Features:**
- Health insurance enrollment
- Life insurance options
- Provident fund (EPF) settings
- Benefits enrollment period management
- Benefit cost calculator
- Claims tracking
- Benefits summary on payslip

**Impact:** Employee satisfaction, compliance

**Effort:** 2-3 weeks

---

### 4.6 Shift Management

**Features:**
- Shift templates (morning, afternoon, night, rotating)
- Shift assignment to employees
- Shift swapping requests
- Shift coverage tracking
- Shift-based pay differentials
- Shift alerts/notifications

**Impact:** For manufacturing, retail, hospitality operations

**Effort:** 2 weeks

---

### 4.7 Employee Self-Service Portal

**Features:**
- View/update personal information
- Leave request submission (UI wrapper)
- Download payslips
- View attendance records
- Update banking details
- View benefits & deductions
- Emergency contact updates
- Password management

**Impact:** Reduces HR workload, employee autonomy

**Effort:** 2-3 weeks

---

### 4.8 Reporting & Analytics

**Features:**
- Attendance reports (daily, weekly, monthly)
- Leave usage reports (by type, by employee)
- Payroll reports (gross, net, deductions)
- Department-level analytics
- Employee turnover analysis
- Head count trends
- Salary benchmarking (internal)
- Export to Excel/PDF

**Impact:** Data-driven HR decisions, compliance

**Effort:** 2-3 weeks

---

## Implementation Priority Matrix

```
Priority | Feature               | Effort | Impact | Startup Match
---------|----------------------|--------|--------|---------------
1        | Leave Management     | Medium | High   | ✅ Essential
2        | Time Clock/Attendance| Medium | High   | ✅ Essential
3        | Employee Directory   | Low    | Medium | ✅ Nice-to-have
4        | Self-Service Portal  | Medium | Medium | ✅ Recommended
5        | Shift Management     | Low    | Medium | ⚠️ If applicable
6        | Basic Appraisal      | Medium | Low    | ⚠️ Year 2+
7        | Onboarding Workflow  | Medium | Medium | ⚠️ Year 1+
8        | Document Management  | Medium | High   | ✅ Important
9        | Benefits Admin       | Medium | Medium | ⚠️ Year 1+
10       | Reporting/Analytics  | Low    | High   | ✅ Recommended
```

---

## Recommended Rollout Schedule

### Q2 2026 (Payroll - ✅ DONE)
- ✅ Payroll system production-ready

### Q3 2026 (Leave + Time Clock - Recommended Priority)
- Leave Management (Weeks 1-4)
- Time Clock & Attendance (Weeks 3-7, can overlap)

### Q4 2026 (Portal + Directory)
- Employee Self-Service Portal (Weeks 1-3)
- Employee Directory & Org Chart (Weeks 2-4)
- Reporting & Analytics (Weeks 3-5)

### Q1 2027 (Enhancements)
- Document Management
- Onboarding Workflow
- Advanced Appraisals

---

## Key Considerations for Startup Success

### 1. **Regulatory Compliance (Malaysia)**
- Leave: Statutory minimums (Annual Leave 20 days, etc.)
- Attendance: Working hours regulations
- Payroll: KWSP, SOCSO, EIS calculations ✅ (done)
- Overtime: Payment rules and maximums

### 2. **Scalability**
- Start with core features (payroll, leave, attendance)
- Add advanced features as company grows
- Avoid over-engineering; keep UI simple
- Mobile-first for attendance scanning

### 3. **Integration Points**
- Payroll → Leave balances on payslip
- Attendance → Overtime calculation in payroll
- Leave → Dashboard showing absence
- All → Finance reporting for accounting

### 4. **Employee Communication**
- Self-service reduces HR support burden
- Notifications for leave approvals, payslips
- Mobile app for clock-in scanning
- Leave balance visibility builds trust

### 5. **Security & Privacy**
- GPS location data stored securely
- Biometric data if using fingerprint (optional)
- Personal leave reasons confidential
- Audit logs for access to sensitive data

---

## Success Metrics

After implementing all phases:

- ✅ 100% payroll accuracy (balanced journal entries)
- ✅ Zero manual leave balance updates (automated)
- ✅ <5% attendance discrepancies (auto-reconciliation)
- ✅ 90%+ self-service usage (portal)
- ✅ <2 days leave approval time (workflow)
- ✅ Remote clock-in attempts: 0 (geofence)
- ✅ Dashboard showing real-time HR metrics

---

## Next Steps

1. **Approve Phase 2 (Leave Management)** - Start design/implementation
2. **Approve Phase 3 (Time Clock)** - Parallel planning
3. **Create feature branches** - Separate PRs for each phase
4. **Setup testing infrastructure** - Pest tests for each module
5. **Document APIs** - For mobile app integration later

---

**Last Updated:** 2026-05-05  
**Status:** Ready for Phase 2 implementation  
**Owner:** HR Module Team

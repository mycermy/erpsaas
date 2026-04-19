<?php

use Erpsaas\Core\Console\Commands\TriggerRecurringInvoiceGeneration;
use Erpsaas\Core\Console\Commands\UpdateOverdueInvoices;
use Illuminate\Support\Facades\Schedule;

Schedule::command(UpdateOverdueInvoices::class)->everyFiveMinutes();
Schedule::command(TriggerRecurringInvoiceGeneration::class, ['--queue'])->everyMinute();

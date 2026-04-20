<?php
require 'vendor/autoload.php';
use Carbon\Carbon;

$dDate = Carbon::parse('2025-12-28');
$cAt = Carbon::parse('2026-04-19');

echo "Deadline: " . $dDate->toDateTimeString() . "\n";
echo "Completed: " . $cAt->toDateTimeString() . "\n";

$delay1 = $cAt->diffInDays($dDate, false);
$delay2 = $dDate->diffInDays($cAt, false);

echo "cAt->diffInDays(dDate, false): " . $delay1 . "\n";
echo "dDate->diffInDays(cAt, false): " . $delay2 . "\n";

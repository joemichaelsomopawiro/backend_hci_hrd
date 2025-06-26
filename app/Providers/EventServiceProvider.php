<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;
use App\Services\AttendanceSyncService;
use Exception;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
        
        $this->registerEmployeeEvents();
    }
    
    /**
     * Register employee model events for attendance machine synchronization
     */
    private function registerEmployeeEvents(): void
    {
        // Auto sync employee to attendance machines when created
        Employee::created(function ($employee) {
            $this->syncEmployeeToMachines($employee, 'created');
        });

        // Auto sync employee to attendance machines when updated
        Employee::updated(function ($employee) {
            // Only sync if relevant fields changed
            if ($employee->wasChanged(['nip', 'nama_lengkap', 'jabatan_saat_ini'])) {
                $this->syncEmployeeToMachines($employee, 'updated');
            }
        });

        // Remove employee from attendance machines when deleted
        Employee::deleted(function ($employee) {
            $this->removeEmployeeFromMachines($employee);
        });
    }
    
    /**
     * Sync employee to all attendance machines
     */
    private function syncEmployeeToMachines(Employee $employee, string $action): void
    {
        try {
            // Only sync if employee has NIP (badge number)
            if (empty($employee->nip)) {
                Log::warning("Employee {$action} but has no NIP, skipping attendance machine sync", [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->nama_lengkap
                ]);
                return;
            }
            
            $syncService = app(AttendanceSyncService::class);
            $result = $syncService->syncUserToAllMachines($employee->id);
            
            Log::info("Employee {$action} and synced to attendance machines", [
                'employee_id' => $employee->id,
                'employee_nip' => $employee->nip,
                'sync_results' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to sync {$action} employee to attendance machines", [
                'employee_id' => $employee->id,
                'employee_nip' => $employee->nip ?? 'N/A',
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Remove employee from all attendance machines
     */
    private function removeEmployeeFromMachines(Employee $employee): void
    {
        try {
            if (empty($employee->nip)) {
                Log::info('Employee deleted but had no NIP, no attendance machine cleanup needed', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->nama_lengkap
                ]);
                return;
            }
            
            $syncService = app(AttendanceSyncService::class);
            $result = $syncService->removeUserFromAllMachines($employee->id);
            
            Log::info('Employee deleted and removed from attendance machines', [
                'employee_id' => $employee->id,
                'employee_nip' => $employee->nip,
                'removal_results' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to remove deleted employee from attendance machines', [
                'employee_id' => $employee->id,
                'employee_nip' => $employee->nip ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

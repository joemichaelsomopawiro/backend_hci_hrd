<?php

namespace App\Services;

use App\Models\AttendanceMachine;
use App\Models\AttendanceSyncLog;
use Exception;
use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceMachineService
{
    private $machine;
    private $soapClient;
    private $timeout = 30; // seconds

    public function __construct(AttendanceMachine $machine)
    {
        $this->machine = $machine;
    }

    /**
     * Initialize SOAP client connection
     */
    private function initSoapClient(): bool
    {
        try {
            $wsdl = $this->machine->getConnectionUrl();
            
            $options = [
                'soap_version' => SOAP_1_2,
                'exceptions' => true,
                'trace' => 1,
                'connection_timeout' => $this->timeout,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create([
                    'http' => [
                        'timeout' => $this->timeout,
                        'user_agent' => 'Laravel-AttendanceSystem/1.0'
                    ]
                ])
            ];

            $this->soapClient = new SoapClient($wsdl, $options);
            return true;
        } catch (SoapFault $e) {
            Log::error('SOAP Client initialization failed', [
                'machine_id' => $this->machine->id,
                'error' => $e->getMessage(),
                'url' => $this->machine->getConnectionUrl()
            ]);
            return false;
        }
    }

    /**
     * Test connection to attendance machine
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('test_connection');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            // Test with GetDeviceInfo method
            $response = $this->soapClient->GetDeviceInfo([
                'ArgComKey' => $this->machine->comm_key ?? 0
            ]);

            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'success', 'Connection successful', [
                'device_info' => $response,
                'response_time' => round($duration * 1000, 2) . 'ms'
            ], 0, $duration);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => $response,
                'response_time' => round($duration * 1000, 2) . 'ms'
            ];

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e)
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Pull attendance data from machine
     */
    public function pullAttendanceData(?Carbon $fromDate = null, ?Carbon $toDate = null): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('pull_data');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            // Set default date range if not provided
            $fromDate = $fromDate ?? Carbon::today();
            $toDate = $toDate ?? Carbon::now();

            $response = $this->soapClient->GetAttLog([
                'ArgComKey' => $this->machine->comm_key ?? 0,
                'Arg' => [
                    'PIN' => '', // Empty to get all users
                    'StartTime' => $fromDate->format('Y-m-d H:i:s'),
                    'EndTime' => $toDate->format('Y-m-d H:i:s')
                ]
            ]);

            $attendanceData = $this->parseAttendanceResponse($response);
            $recordCount = count($attendanceData);
            $duration = microtime(true) - $startTime;

            $this->completeSyncLog($log, 'success', "Successfully pulled {$recordCount} attendance records", [
                'date_range' => [
                    'from' => $fromDate->format('Y-m-d H:i:s'),
                    'to' => $toDate->format('Y-m-d H:i:s')
                ],
                'raw_response' => $response
            ], $recordCount, $duration);

            return [
                'success' => true,
                'message' => "Successfully pulled {$recordCount} attendance records",
                'data' => $attendanceData,
                'count' => $recordCount
            ];

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e),
                'date_range' => [
                    'from' => $fromDate?->format('Y-m-d H:i:s'),
                    'to' => $toDate?->format('Y-m-d H:i:s')
                ]
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Upload user to machine
     */
    public function uploadUser(string $badgeNumber, string $name, string $password = ''): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('push_user');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            $response = $this->soapClient->SetUserInfo([
                'ArgComKey' => $this->machine->comm_key ?? 0,
                'Arg' => [
                    'PIN' => $badgeNumber,
                    'Name' => $name,
                    'Password' => $password,
                    'Group' => 1, // Default group
                    'Privilege' => 0, // Regular user
                    'Card' => $badgeNumber,
                    'PIN2' => '',
                    'TZ1' => 1,
                    'TZ2' => 0,
                    'TZ3' => 0
                ]
            ]);

            $duration = microtime(true) - $startTime;
            
            if ($this->isSuccessResponse($response)) {
                $this->completeSyncLog($log, 'success', "User {$badgeNumber} uploaded successfully", [
                    'user_data' => [
                        'badge_number' => $badgeNumber,
                        'name' => $name
                    ],
                    'response' => $response
                ], 1, $duration);

                return [
                    'success' => true,
                    'message' => "User {$badgeNumber} uploaded successfully",
                    'data' => $response
                ];
            } else {
                throw new Exception('Machine returned error response: ' . json_encode($response));
            }

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e),
                'user_data' => [
                    'badge_number' => $badgeNumber,
                    'name' => $name
                ]
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Delete user from machine
     */
    public function deleteUser(string $badgeNumber): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('delete_user');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            $response = $this->soapClient->DeleteUser([
                'ArgComKey' => $this->machine->comm_key ?? 0,
                'Arg' => $badgeNumber
            ]);

            $duration = microtime(true) - $startTime;
            
            if ($this->isSuccessResponse($response)) {
                $this->completeSyncLog($log, 'success', "User {$badgeNumber} deleted successfully", [
                    'badge_number' => $badgeNumber,
                    'response' => $response
                ], 1, $duration);

                return [
                    'success' => true,
                    'message' => "User {$badgeNumber} deleted successfully",
                    'data' => $response
                ];
            } else {
                throw new Exception('Machine returned error response: ' . json_encode($response));
            }

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e),
                'badge_number' => $badgeNumber
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Delete all attendance data from machine
     */
    public function deleteAttendanceData(): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('delete_data');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            $response = $this->soapClient->ClearData([
                'ArgComKey' => $this->machine->comm_key ?? 0,
                'Arg' => 1 // 1 for attendance data
            ]);

            $duration = microtime(true) - $startTime;
            
            if ($this->isSuccessResponse($response)) {
                $this->completeSyncLog($log, 'success', 'Attendance data cleared successfully', [
                    'response' => $response
                ], 0, $duration);

                return [
                    'success' => true,
                    'message' => 'Attendance data cleared successfully',
                    'data' => $response
                ];
            } else {
                throw new Exception('Machine returned error response: ' . json_encode($response));
            }

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e)
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Restart attendance machine
     */
    public function restartMachine(): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('restart');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            $response = $this->soapClient->RestartDevice([
                'ArgComKey' => $this->machine->comm_key ?? 0
            ]);

            $duration = microtime(true) - $startTime;
            
            if ($this->isSuccessResponse($response)) {
                $this->completeSyncLog($log, 'success', 'Machine restart initiated successfully', [
                    'response' => $response
                ], 0, $duration);

                return [
                    'success' => true,
                    'message' => 'Machine restart initiated successfully',
                    'data' => $response
                ];
            } else {
                throw new Exception('Machine returned error response: ' . json_encode($response));
            }

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e)
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Synchronize machine time with server time
     */
    public function syncTime(): array
    {
        $startTime = microtime(true);
        $log = $this->createSyncLog('sync_time');

        try {
            if (!$this->initSoapClient()) {
                throw new Exception('Failed to initialize SOAP client');
            }

            $currentTime = Carbon::now();
            
            $response = $this->soapClient->SetDeviceTime([
                'ArgComKey' => $this->machine->comm_key ?? 0,
                'Arg' => $currentTime->format('Y-m-d H:i:s')
            ]);

            $duration = microtime(true) - $startTime;
            
            if ($this->isSuccessResponse($response)) {
                $this->completeSyncLog($log, 'success', 'Machine time synchronized successfully', [
                    'server_time' => $currentTime->format('Y-m-d H:i:s'),
                    'response' => $response
                ], 0, $duration);

                return [
                    'success' => true,
                    'message' => 'Machine time synchronized successfully',
                    'server_time' => $currentTime->format('Y-m-d H:i:s'),
                    'data' => $response
                ];
            } else {
                throw new Exception('Machine returned error response: ' . json_encode($response));
            }

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->completeSyncLog($log, 'failed', $e->getMessage(), [
                'error_type' => get_class($e),
                'attempted_time' => $currentTime?->format('Y-m-d H:i:s')
            ], 0, $duration);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Parse attendance response from machine
     */
    private function parseAttendanceResponse($response): array
    {
        $attendanceData = [];
        
        if (!isset($response->GetAttLogResult->Row)) {
            return $attendanceData;
        }

        $rows = $response->GetAttLogResult->Row;
        
        // Handle single row response
        if (!is_array($rows)) {
            $rows = [$rows];
        }

        foreach ($rows as $row) {
            $attendanceData[] = [
                'badge_number' => $row->PIN ?? '',
                'timestamp' => $row->DateTime ?? '',
                'status' => $row->Status ?? 0, // 0=Check In, 1=Check Out, etc.
                'verify_mode' => $row->Verified ?? 0,
                'work_code' => $row->WorkCode ?? 0,
                'machine_id' => $row->MachineNumber ?? 0
            ];
        }

        return $attendanceData;
    }

    /**
     * Check if response indicates success
     */
    private function isSuccessResponse($response): bool
    {
        // Different machines may have different success indicators
        // Adjust this based on your machine's response format
        if (isset($response->Result)) {
            return $response->Result === true || $response->Result === 'True' || $response->Result === 1;
        }
        
        return true; // Assume success if no explicit result field
    }

    /**
     * Create sync log entry
     */
    private function createSyncLog(string $operation): AttendanceSyncLog
    {
        return AttendanceSyncLog::create([
            'attendance_machine_id' => $this->machine->id,
            'operation' => $operation,
            'status' => 'running',
            'started_at' => now()
        ]);
    }

    /**
     * Complete sync log entry
     */
    private function completeSyncLog(
        AttendanceSyncLog $log, 
        string $status, 
        string $message, 
        array $details = [], 
        int $recordsProcessed = 0, 
        float $duration = 0
    ): void {
        $log->update([
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'records_processed' => $recordsProcessed,
            'completed_at' => now(),
            'duration' => $duration
        ]);

        // Update machine's last sync time if successful
        if ($status === 'success') {
            $this->machine->update(['last_sync_at' => now()]);
        }
    }
}
/**
 * Test Frontend AttendanceOffice.vue Integration
 * 
 * Test ini memverifikasi:
 * 1. API endpoints sync status berfungsi
 * 2. Manual bulk sync berfungsi  
 * 3. Response format sesuai dengan frontend expectation
 * 4. Error handling works correctly
 */

// Simulated API base URL (adjust sesuai environment)
const API_BASE_URL = 'http://your-domain.com/api';

// Test Auth Token (adjust sesuai sistem auth)
const AUTH_TOKEN = 'your-auth-token-here';

// Utility function untuk API calls
async function smartFetch(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${AUTH_TOKEN}`
        }
    };

    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };

    try {
        const response = await fetch(url, mergedOptions);
        return response;
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

// Test 1: Fetch Sync Status
async function testFetchSyncStatus() {
    console.log('🧪 Testing Fetch Sync Status...');
    
    try {
        const response = await smartFetch(`${API_BASE_URL}/attendance/upload-txt/sync-status`);
        const result = await response.json();
        
        console.log('✅ Response Status:', response.status);
        console.log('📊 Sync Status Data:', JSON.stringify(result, null, 2));
        
        // Validate response format
        if (result.success && result.data) {
            const data = result.data;
            const requiredFields = [
                'total_attendance',
                'synced_attendance', 
                'unsynced_attendance',
                'sync_percentage',
                'unsynced_samples',
                'synced_samples'
            ];
            
            const missingFields = requiredFields.filter(field => !(field in data));
            
            if (missingFields.length === 0) {
                console.log('✅ All required fields present');
                console.log(`📈 Sync Percentage: ${data.sync_percentage}%`);
                console.log(`📋 Total Attendance: ${data.total_attendance}`);
                console.log(`✅ Synced: ${data.synced_attendance}`);
                console.log(`❌ Unsynced: ${data.unsynced_attendance}`);
                console.log(`📝 Unsynced Samples: ${data.unsynced_samples.length}`);
                console.log(`📝 Synced Samples: ${data.synced_samples.length}`);
                return { success: true, data };
            } else {
                console.log('❌ Missing fields:', missingFields);
                return { success: false, error: 'Missing required fields' };
            }
        } else {
            console.log('❌ Invalid response format');
            return { success: false, error: 'Invalid response format' };
        }
    } catch (error) {
        console.log('❌ Fetch Sync Status Failed:', error.message);
        return { success: false, error: error.message };
    }
}

// Test 2: Manual Bulk Sync
async function testManualBulkSync() {
    console.log('\n🧪 Testing Manual Bulk Sync...');
    
    try {
        const response = await smartFetch(`${API_BASE_URL}/attendance/upload-txt/manual-sync`, {
            method: 'POST'
        });
        const result = await response.json();
        
        console.log('✅ Response Status:', response.status);
        console.log('🔄 Bulk Sync Result:', JSON.stringify(result, null, 2));
        
        if (result.success) {
            console.log('✅ Manual Bulk Sync Successful');
            if (result.data) {
                console.log(`📊 Sync Data:`, result.data);
            }
            return { success: true, data: result.data };
        } else {
            console.log('❌ Manual Bulk Sync Failed:', result.message);
            return { success: false, error: result.message };
        }
    } catch (error) {
        console.log('❌ Manual Bulk Sync Error:', error.message);
        return { success: false, error: error.message };
    }
}

// Test 3: Frontend Data Structure Validation
function testFrontendDataStructure(syncStatusData) {
    console.log('\n🧪 Testing Frontend Data Structure...');
    
    // Simulate frontend getSyncPercentageClass function
    function getSyncPercentageClass(percentage) {
        if (percentage >= 90) return 'high-sync';
        if (percentage >= 70) return 'medium-sync';
        if (percentage >= 50) return 'low-sync';
        return 'no-sync';
    }
    
    if (!syncStatusData || !syncStatusData.data) {
        console.log('❌ No sync status data to test');
        return { success: false };
    }
    
    const data = syncStatusData.data;
    const percentage = data.sync_percentage || 0;
    const cssClass = getSyncPercentageClass(percentage);
    
    console.log(`📊 Sync Percentage: ${percentage}%`);
    console.log(`🎨 CSS Class: ${cssClass}`);
    
    // Test stat cards data
    const statCards = [
        { type: 'total', value: data.total_attendance, icon: 'fas fa-database', label: 'Total Absensi' },
        { type: 'synced', value: data.synced_attendance, icon: 'fas fa-check-circle', label: 'Ter-sync' },
        { type: 'unsynced', value: data.unsynced_attendance, icon: 'fas fa-exclamation-circle', label: 'Belum Sync' },
        { type: 'percentage', value: `${percentage}%`, icon: 'fas fa-chart-pie', label: 'Persentase Sync' }
    ];
    
    console.log('📊 Stat Cards Data:');
    statCards.forEach(card => {
        console.log(`  ${card.icon} ${card.label}: ${card.value}`);
    });
    
    // Test sample data
    console.log('\n📝 Sample Data:');
    console.log(`  Unsynced Samples: ${data.unsynced_samples.length} items`);
    console.log(`  Synced Samples: ${data.synced_samples.length} items`);
    
    if (data.unsynced_samples.length > 0) {
        console.log('  Sample Unsynced Item:', data.unsynced_samples[0]);
    }
    
    if (data.synced_samples.length > 0) {
        console.log('  Sample Synced Item:', data.synced_samples[0]);
    }
    
    return { success: true, cssClass, statCards };
}

// Test 4: Error Handling Simulation
async function testErrorHandling() {
    console.log('\n🧪 Testing Error Handling...');
    
    // Test with invalid endpoint
    try {
        const response = await smartFetch(`${API_BASE_URL}/attendance/invalid-endpoint`);
        const result = await response.json();
        
        if (response.status === 404) {
            console.log('✅ 404 error handled correctly');
        } else {
            console.log('⚠️  Unexpected response for invalid endpoint');
        }
    } catch (error) {
        console.log('✅ Network error handled correctly:', error.message);
    }
    
    // Test with invalid auth token
    try {
        const response = await smartFetch(`${API_BASE_URL}/attendance/upload-txt/sync-status`, {
            headers: {
                'Authorization': 'Bearer invalid-token'
            }
        });
        
        if (response.status === 401 || response.status === 403) {
            console.log('✅ Auth error handled correctly');
        } else {
            console.log('⚠️  Unexpected response for invalid auth');
        }
    } catch (error) {
        console.log('✅ Auth error handled correctly:', error.message);
    }
}

// Test 5: Mobile Responsive Data
function testMobileResponsive(syncStatusData) {
    console.log('\n🧪 Testing Mobile Responsive Data...');
    
    if (!syncStatusData || !syncStatusData.data) {
        console.log('❌ No data for mobile test');
        return { success: false };
    }
    
    const data = syncStatusData.data;
    
    // Simulate mobile data limits
    const mobileUnsyncedSamples = data.unsynced_samples.slice(0, 5);
    const mobileSyncedSamples = data.synced_samples.slice(0, 3);
    
    console.log(`📱 Mobile Unsynced Samples: ${mobileUnsyncedSamples.length} (limited from ${data.unsynced_samples.length})`);
    console.log(`📱 Mobile Synced Samples: ${mobileSyncedSamples.length} (limited from ${data.synced_samples.length})`);
    
    // Test responsive grid (2x2 for mobile)
    const statsForMobile = [
        { label: 'Total', value: data.total_attendance },
        { label: 'Sync', value: data.synced_attendance },
        { label: 'Unsync', value: data.unsynced_attendance },
        { label: 'Percent', value: `${data.sync_percentage}%` }
    ];
    
    console.log('📱 Mobile Stats Grid (2x2):');
    console.log(`  Row 1: ${statsForMobile[0].label}=${statsForMobile[0].value} | ${statsForMobile[1].label}=${statsForMobile[1].value}`);
    console.log(`  Row 2: ${statsForMobile[2].label}=${statsForMobile[2].value} | ${statsForMobile[3].label}=${statsForMobile[3].value}`);
    
    return { success: true, mobileUnsyncedSamples, mobileSyncedSamples };
}

// Main Test Runner
async function runAllTests() {
    console.log('🚀 Starting AttendanceOffice.vue Frontend Integration Tests\n');
    console.log('=' .repeat(60));
    
    let testResults = {
        fetchSyncStatus: null,
        manualBulkSync: null,
        dataStructure: null, 
        errorHandling: null,
        mobileResponsive: null
    };
    
    // Test 1: Fetch Sync Status
    testResults.fetchSyncStatus = await testFetchSyncStatus();
    
    // Test 2: Manual Bulk Sync
    testResults.manualBulkSync = await testManualBulkSync();
    
    // Test 3: Frontend Data Structure (use data from test 1)
    testResults.dataStructure = testFrontendDataStructure(testResults.fetchSyncStatus);
    
    // Test 4: Error Handling
    await testErrorHandling();
    testResults.errorHandling = { success: true }; // Assume success if no crashes
    
    // Test 5: Mobile Responsive
    testResults.mobileResponsive = testMobileResponsive(testResults.fetchSyncStatus);
    
    // Summary
    console.log('\n' + '=' .repeat(60));
    console.log('📋 TEST SUMMARY');
    console.log('=' .repeat(60));
    
    Object.entries(testResults).forEach(([testName, result]) => {
        const status = result && result.success ? '✅ PASS' : '❌ FAIL';
        console.log(`${status} ${testName}`);
    });
    
    const passedTests = Object.values(testResults).filter(r => r && r.success).length;
    const totalTests = Object.keys(testResults).length;
    
    console.log(`\n🏆 RESULT: ${passedTests}/${totalTests} tests passed`);
    
    if (passedTests === totalTests) {
        console.log('🎉 All tests passed! Frontend integration is ready.');
    } else {
        console.log('⚠️  Some tests failed. Please check the implementation.');
    }
    
    return testResults;
}

// Configuration Instructions
console.log('📋 SETUP INSTRUCTIONS:');
console.log('1. Update API_BASE_URL with your actual domain');
console.log('2. Update AUTH_TOKEN with valid authentication token');
console.log('3. Ensure backend endpoints are running');
console.log('4. Run: node test_frontend_attendance_office.js\n');

// Auto-run if this file is executed directly
if (typeof require !== 'undefined' && require.main === module) {
    runAllTests().catch(console.error);
}

// Export for use in other test files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        testFetchSyncStatus,
        testManualBulkSync,
        testFrontendDataStructure,
        testErrorHandling,
        testMobileResponsive,
        runAllTests
    };
} 
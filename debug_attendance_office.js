/**
 * Debug helper untuk AttendanceOffice.vue
 * 
 * Jalankan di browser console untuk debug masalah tabel tidak muncul
 */

console.log('🔍 DEBUGGING ATTENDANCE OFFICE');
console.log('================================');

// 1. Check user data dan role
function debugUserRole() {
    console.log('\n📋 1. USER ROLE DEBUG:');
    
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    console.log('Raw user data:', user);
    
    const userRole = user.role || user.jabatan || '';
    console.log('Original role:', userRole);
    
    const normalizedUserRole = userRole.toLowerCase().replace(/\s+/g, '_');
    console.log('Normalized role:', normalizedUserRole);
    
    const managerRoles = ['general_affairs', 'hr', 'program_manager', 'distribution_manager', 'vp_president', 'president_director'];
    const allowedRoles = ['general_affairs', 'hr', 'program_manager', 'distribution_manager', 'vp_president', 'president_director'];
    
    const isEmployee = !managerRoles.includes(normalizedUserRole);
    const hasManagerAccess = allowedRoles.includes(normalizedUserRole);
    
    console.log('Manager roles:', managerRoles);
    console.log('Is employee?', isEmployee);
    console.log('Has manager access?', hasManagerAccess);
    
    if (normalizedUserRole === 'program_manager') {
        console.log('✅ Program Manager detected correctly');
    } else {
        console.log('❌ Program Manager NOT detected');
        console.log('Expected: "program_manager"');
        console.log('Got:', normalizedUserRole);
    }
    
    return { isEmployee, hasManagerAccess, normalizedUserRole };
}

// 2. Check API endpoint
async function debugMonthlyTableAPI() {
    console.log('\n🌐 2. MONTHLY TABLE API DEBUG:');
    
    try {
        // Get current month/year
        const exportDate = {
            month: String(new Date().getMonth() + 1).padStart(2, '0'),
            year: new Date().getFullYear()
        };
        
        console.log('Request params:', exportDate);
        
        const params = new URLSearchParams({
            month: parseInt(exportDate.month).toString(),
            year: exportDate.year
        });
        
        const apiUrl = `/api/attendance/monthly-table?${params}`;
        console.log('API URL:', apiUrl);
        
        const response = await fetch(apiUrl);
        console.log('Response status:', response.status);
        console.log('Response headers:', [...response.headers.entries()]);
        
        const result = await response.json();
        console.log('Response data:', result);
        
        if (result.success) {
            console.log('✅ API call successful');
            console.log('Records count:', result.data?.records?.length || 0);
            console.log('Working days count:', result.data?.working_days?.length || 0);
            
            if (result.data?.records?.length > 0) {
                console.log('Sample record:', result.data.records[0]);
            } else {
                console.log('⚠️  No records returned');
            }
        } else {
            console.log('❌ API call failed:', result.message);
        }
        
        return result;
    } catch (error) {
        console.log('❌ API Error:', error);
        return null;
    }
}

// 3. Check Vue component state
function debugVueState() {
    console.log('\n🔧 3. VUE COMPONENT STATE DEBUG:');
    
    // Try to find Vue component instance
    const app = document.querySelector('#app');
    if (app && app.__vue__) {
        const vueInstance = app.__vue__;
        console.log('Vue instance found:', vueInstance);
        
        // Check component data
        console.log('isEmployee:', vueInstance.isEmployee);
        console.log('monthlyTable.loading:', vueInstance.monthlyTable?.loading);
        console.log('monthlyTable.data length:', vueInstance.monthlyTable?.data?.length || 0);
        console.log('monthlyTable.workingDays length:', vueInstance.monthlyTable?.workingDays?.length || 0);
        console.log('monthlyTable full data:', vueInstance.monthlyTable);
        
        return vueInstance;
    } else {
        console.log('❌ Vue instance not found');
        return null;
    }
}

// 4. Check DOM elements
function debugDOM() {
    console.log('\n🎨 4. DOM ELEMENTS DEBUG:');
    
    const tableSection = document.querySelector('.table-section');
    const tableContainer = document.querySelector('.table-container');
    const monthlyTable = document.querySelector('.monthly-table');
    const loadingState = document.querySelector('.loading-state');
    const emptyState = document.querySelector('.empty-state');
    
    console.log('Table section found:', !!tableSection);
    console.log('Table container found:', !!tableContainer);
    console.log('Monthly table found:', !!monthlyTable);
    console.log('Loading state visible:', !!loadingState);
    console.log('Empty state visible:', !!emptyState);
    
    if (tableContainer) {
        console.log('Table container styles:', getComputedStyle(tableContainer).display);
    }
    
    if (loadingState) {
        console.log('Loading state styles:', getComputedStyle(loadingState).display);
    }
}

// 5. Check console errors
function debugConsoleErrors() {
    console.log('\n🚨 5. CONSOLE ERRORS DEBUG:');
    console.log('Check browser console for any JavaScript errors');
    console.log('Common issues:');
    console.log('- Network errors (404, 500)');
    console.log('- CORS issues');
    console.log('- Authentication errors');
    console.log('- JavaScript runtime errors');
}

// 6. Suggested fixes
function suggestFixes(roleDebug) {
    console.log('\n🔧 6. SUGGESTED FIXES:');
    
    if (!roleDebug.hasManagerAccess) {
        console.log('❌ ROLE ISSUE:');
        console.log('- Check if user role is exactly "Program Manager" (case sensitive)');
        console.log('- Or if it should be stored as "program_manager"');
        console.log('- Current role:', roleDebug.normalizedUserRole);
        console.log('- Expected: program_manager');
        console.log('- Fix: Update user role in database or localStorage');
        return;
    }
    
    console.log('✅ Role is correct, checking other issues...');
    console.log('- Clear browser cache and localStorage');
    console.log('- Check network tab for API failures');
    console.log('- Verify API endpoint is accessible');
    console.log('- Check if data exists in database');
}

// Manual test functions
window.debugAttendanceOffice = {
    testRole: debugUserRole,
    testAPI: debugMonthlyTableAPI,
    testVue: debugVueState,
    testDOM: debugDOM,
    testErrors: debugConsoleErrors,
    fixRole: function(newRole) {
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        user.role = newRole;
        localStorage.setItem('user', JSON.stringify(user));
        console.log('✅ Role updated to:', newRole);
        console.log('🔄 Please refresh page');
    },
    runAll: async function() {
        const roleDebug = debugUserRole();
        await debugMonthlyTableAPI();
        debugVueState();
        debugDOM();
        debugConsoleErrors();
        suggestFixes(roleDebug);
    }
};

// Auto-run debug
debugAttendanceOffice.runAll();

console.log('\n🎯 MANUAL DEBUG COMMANDS:');
console.log('Run these in console:');
console.log('- debugAttendanceOffice.testRole()');
console.log('- debugAttendanceOffice.testAPI()');
console.log('- debugAttendanceOffice.testVue()');
console.log('- debugAttendanceOffice.testDOM()');
console.log('- debugAttendanceOffice.fixRole("Program Manager")');
console.log('- debugAttendanceOffice.runAll()'); 
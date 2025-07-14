// fix_calendar_frontend.js
// Script untuk debug dan fix masalah calendar frontend

console.log('=== Calendar Frontend Debug ===');

// 1. Cek token dan user
const token = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');

console.log('Token:', token ? 'Present' : 'Missing');
console.log('User Role:', user.role);
console.log('User:', user);

// 2. Test GET endpoint
async function testGetCalendar() {
  try {
    console.log('\n--- Testing GET /api/calendar/data ---');
    
    const response = await fetch('/api/calendar/data?year=2024&month=12', {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });
    
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    if (response.ok) {
      const data = await response.json();
      console.log('✅ GET successful:', data);
      return true;
    } else {
      const errorText = await response.text();
      console.error('❌ GET failed:', response.status, errorText);
      return false;
    }
  } catch (error) {
    console.error('❌ GET error:', error);
    return false;
  }
}

// 3. Test POST endpoint
async function testPostCalendar() {
  try {
    console.log('\n--- Testing POST /api/calendar ---');
    
    const holidayData = {
      date: '2025-01-02',
      name: 'Libur Test Frontend',
      description: 'Test dari frontend debug script',
      type: 'custom'
    };
    
    console.log('Sending data:', holidayData);
    
    const response = await fetch('/api/calendar', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(holidayData)
    });
    
    console.log('Response status:', response.status);
    console.log('Response headers:', response.headers);
    
    if (response.ok) {
      const data = await response.json();
      console.log('✅ POST successful:', data);
      return true;
    } else {
      const errorText = await response.text();
      console.error('❌ POST failed:', response.status, errorText);
      
      // Jika response adalah HTML, tampilkan sebagian untuk debug
      if (errorText.includes('<!DOCTYPE')) {
        console.error('Server returned HTML instead of JSON. This usually means:');
        console.error('1. Wrong URL endpoint');
        console.error('2. Server error (500)');
        console.error('3. Route not found (404)');
        console.error('4. Authentication issue');
        
        // Tampilkan 200 karakter pertama dari response
        console.error('Response preview:', errorText.substring(0, 200));
      }
      
      return false;
    }
  } catch (error) {
    console.error('❌ POST error:', error);
    return false;
  }
}

// 4. Test dengan URL yang berbeda (untuk debug)
async function testAlternativeURLs() {
  console.log('\n--- Testing Alternative URLs ---');
  
  const urls = [
    '/api/calendar',
    '/api/calendar/',
    'api/calendar',
    '/calendar',
    '/api/calendar/add'
  ];
  
  for (const url of urls) {
    try {
      console.log(`Testing URL: ${url}`);
      
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          date: '2025-01-03',
          name: 'Test URL',
          type: 'custom'
        })
      });
      
      console.log(`  Status: ${response.status}`);
      
      if (response.ok) {
        const data = await response.json();
        console.log(`  ✅ Success: ${url}`);
        break;
      } else {
        const errorText = await response.text();
        if (errorText.includes('<!DOCTYPE')) {
          console.log(`  ❌ HTML response: ${url}`);
        } else {
          console.log(`  ❌ Error: ${response.status}`);
        }
      }
    } catch (error) {
      console.log(`  ❌ Exception: ${url} - ${error.message}`);
    }
  }
}

// 5. Cek apakah ada masalah dengan CORS
async function testCORS() {
  console.log('\n--- Testing CORS ---');
  
  try {
    const response = await fetch('/api/calendar/data?year=2024&month=12', {
      method: 'OPTIONS',
      headers: {
        'Origin': window.location.origin,
        'Access-Control-Request-Method': 'POST',
        'Access-Control-Request-Headers': 'Content-Type,Authorization'
      }
    });
    
    console.log('CORS preflight status:', response.status);
    console.log('CORS headers:', response.headers);
  } catch (error) {
    console.log('CORS test error:', error);
  }
}

// 6. Main test function
async function runAllTests() {
  console.log('Starting all tests...\n');
  
  const getResult = await testGetCalendar();
  const postResult = await testPostCalendar();
  
  if (getResult && postResult) {
    console.log('\n🎉 All tests passed! Backend is working correctly.');
    console.log('The issue might be in your frontend code implementation.');
  } else if (!postResult) {
    console.log('\n🔧 POST test failed. Testing alternative URLs...');
    await testAlternativeURLs();
    await testCORS();
  }
  
  console.log('\n=== Debug Complete ===');
  console.log('Check the console output above for issues.');
}

// 7. Helper function untuk test calendar service
function testCalendarService() {
  console.log('\n--- Testing Calendar Service ---');
  
  // Simulasi calendarService
  const calendarService = {
    baseURL: '/api/calendar',
    
    getHeaders() {
      const token = localStorage.getItem('token');
      return {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      };
    },
    
    async addHoliday(holidayData) {
      try {
        console.log('calendarService.addHoliday called with:', holidayData);
        console.log('URL:', this.baseURL);
        console.log('Headers:', this.getHeaders());
        
        const response = await fetch(this.baseURL, {
          method: 'POST',
          headers: this.getHeaders(),
          body: JSON.stringify(holidayData)
        });
        
        console.log('Response status:', response.status);
        
        if (!response.ok) {
          const errorText = await response.text();
          console.error('HTTP Error:', response.status, errorText);
          throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        console.log('Response data:', data);
        return data;
      } catch (error) {
        console.error('calendarService error:', error);
        return { success: false, message: 'Gagal menambah hari libur: ' + error.message };
      }
    }
  };
  
  // Test calendarService
  calendarService.addHoliday({
    date: '2025-01-04',
    name: 'Test Calendar Service',
    description: 'Test dari calendarService',
    type: 'custom'
  });
}

// Run tests
runAllTests().then(() => {
  // Test calendar service after main tests
  setTimeout(testCalendarService, 1000);
});

// Export untuk digunakan di console
window.calendarDebug = {
  testGetCalendar,
  testPostCalendar,
  testAlternativeURLs,
  testCORS,
  runAllTests
};

console.log('Debug functions available as window.calendarDebug'); 
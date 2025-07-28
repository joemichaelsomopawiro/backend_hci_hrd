// FIX FRONTEND GA DASHBOARD - COMPLETE SOLUTION
console.log("=== FIX FRONTEND GA DASHBOARD ===");

// 1. VERIFY CONFIGURATION
console.log("\n1. VERIFYING CONFIGURATION:");
const config = {
  apiBaseUrl: "https://api.hopemedia.id",
  endpoint: "/api/ga-dashboard/worship-attendance",
  fullUrl: "https://api.hopemedia.id/api/ga-dashboard/worship-attendance"
};

console.log("✅ API Base URL:", config.apiBaseUrl);
console.log("✅ Endpoint:", config.endpoint);
console.log("✅ Full URL:", config.fullUrl);

// 2. TEST API ENDPOINT
console.log("\n2. TESTING API ENDPOINT:");
const testAPI = async () => {
  try {
    console.log("🔍 Testing:", config.fullUrl);
    
    const response = await fetch(config.fullUrl, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer test-token',
        'Accept': 'application/json'
      }
    });
    
    console.log("📊 Response Status:", response.status);
    console.log("📊 Response Headers:", Object.fromEntries(response.headers.entries()));
    
    if (response.status === 200) {
      const data = await response.json();
      console.log("✅ SUCCESS: API working");
      console.log("📊 Records:", data.total_records || 'N/A');
      console.log("💬 Message:", data.message || 'N/A');
    } else if (response.status === 401) {
      console.log("✅ SUCCESS: API exists (401 = unauthorized, expected)");
    } else if (response.status === 404) {
      console.log("❌ ERROR: API not found (404)");
    } else {
      console.log("⚠️  WARNING: Unexpected status:", response.status);
    }
    
  } catch (error) {
    console.log("❌ NETWORK ERROR:", error.message);
  }
};

// 3. FRONTEND FIX INSTRUCTIONS
console.log("\n3. FRONTEND FIX INSTRUCTIONS:");
console.log("=================================");

console.log("\nA. UPDATE src/utils/apiConfig.js:");
console.log("```javascript");
console.log("export function getApiBaseUrl() {");
console.log("  return \"https://api.hopemedia.id\";");
console.log("}");
console.log("```");

console.log("\nB. UPDATE src/utils/fetchHelper.js:");
console.log("```javascript");
console.log("import { getApiBaseUrl } from './apiConfig'");
console.log("");
console.log("export async function smartFetch(url, options = {}) {");
console.log("  const baseURL = getApiBaseUrl();");
console.log("  const fullUrl = url.startsWith('http') ? url : `${baseURL}${url.startsWith('/') ? '' : '/'}${url}`;");
console.log("  return fetch(fullUrl, options);");
console.log("}");
console.log("```");

console.log("\nC. CLEAR BROWSER CACHE:");
console.log("1. Open Developer Tools (F12)");
console.log("2. Right-click refresh button");
console.log("3. Select 'Empty Cache and Hard Reload'");
console.log("4. Or use Ctrl+Shift+Delete");

console.log("\nD. CHECK NETWORK TAB:");
console.log("1. Open Developer Tools (F12)");
console.log("2. Go to Network tab");
console.log("3. Refresh page");
console.log("4. Look for requests to api.hopemedia.id");
console.log("5. Check if any requests are failing");

console.log("\nE. VERIFY TOKEN:");
console.log("1. Open Developer Tools (F12)");
console.log("2. Go to Application tab");
console.log("3. Check Local Storage");
console.log("4. Verify 'token' exists and is valid");

// 4. ALTERNATIVE SOLUTIONS
console.log("\n4. ALTERNATIVE SOLUTIONS:");
console.log("=========================");

console.log("\nA. Use absolute URL in GaDashboard.vue:");
console.log("```javascript");
console.log("// Instead of smartFetch, use direct fetch");
console.log("const response = await fetch('https://api.hopemedia.id/api/ga-dashboard/worship-attendance', {");
console.log("  method: 'GET',");
console.log("  headers: {");
console.log("    'Content-Type': 'application/json',");
console.log("    'Authorization': `Bearer ${localStorage.getItem('token')}`");
console.log("  }");
console.log("});");
console.log("```");

console.log("\nB. Check for CORS issues:");
console.log("1. Look for CORS errors in console");
console.log("2. Check if server allows requests from your domain");
console.log("3. Verify Access-Control-Allow-Origin headers");

console.log("\nC. Test with different browsers:");
console.log("1. Try Chrome, Firefox, Safari");
console.log("2. Check if issue is browser-specific");
console.log("3. Disable browser extensions");

// 5. DEBUGGING STEPS
console.log("\n5. DEBUGGING STEPS:");
console.log("===================");

console.log("\nStep 1: Add debug logs to GaDashboard.vue:");
console.log("```javascript");
console.log("console.log('🔍 [DEBUG] Base URL:', getApiBaseUrl());");
console.log("console.log('🔍 [DEBUG] Full URL:', fullUrl);");
console.log("console.log('🔍 [DEBUG] Token:', localStorage.getItem('token'));");
console.log("```");

console.log("\nStep 2: Test with curl:");
console.log("```bash");
console.log("curl -X GET 'https://api.hopemedia.id/api/ga-dashboard/worship-attendance' \\");
console.log("  -H 'Content-Type: application/json' \\");
console.log("  -H 'Authorization: Bearer YOUR_TOKEN'");
console.log("```");

console.log("\nStep 3: Check server logs:");
console.log("1. Check Laravel logs: storage/logs/laravel.log");
console.log("2. Check web server logs (Apache/Nginx)");
console.log("3. Look for 404 errors or authentication issues");

// Run the test
testAPI();

console.log("\n=== FRONTEND FIX COMPLETE ===");
console.log("\nNext steps:");
console.log("1. Update configuration files");
console.log("2. Clear browser cache");
console.log("3. Test in browser");
console.log("4. Check network tab for errors");
console.log("5. Verify token is valid"); 
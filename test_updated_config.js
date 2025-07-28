// Test Updated Configuration
console.log("=== TEST UPDATED CONFIGURATION ===");

// Simulate the apiConfig.js
const isDevelopment = false; // Simulate production
const isProduction = true;
const LOCAL_API_URL = 'http://127.0.0.1:8000';
const PRODUCTION_API_URL = 'https://api.hopemedia.id';

function convertApiUrl(url) {
  if (!url) return url;
  
  if (isDevelopment) {
    return url;
  }
  
  let convertedUrl = url;
  
  if (convertedUrl.includes(LOCAL_API_URL)) {
    convertedUrl = convertedUrl.replace(LOCAL_API_URL, PRODUCTION_API_URL);
  }
  
  return convertedUrl;
}

function getApiBaseUrl() {
  if (isDevelopment) {
    return LOCAL_API_URL;
  }
  return PRODUCTION_API_URL;
}

// Simulate apiFetch
async function apiFetch(endpoint, options = {}) {
  const baseUrl = getApiBaseUrl();
  const fullUrl = `${baseUrl}${endpoint}`;
  const convertedUrl = convertApiUrl(fullUrl);
  
  console.log(`🔍 [DEBUG] apiFetch - Endpoint: ${endpoint}`);
  console.log(`🔍 [DEBUG] apiFetch - Base URL: ${baseUrl}`);
  console.log(`🔍 [DEBUG] apiFetch - Full URL: ${fullUrl}`);
  console.log(`🔍 [DEBUG] apiFetch - Converted URL: ${convertedUrl}`);
  
  try {
    const response = await fetch(convertedUrl, options);
    
    let data;
    const contentType = response.headers.get('content-type');
    
    if (contentType && contentType.includes('application/json')) {
      try {
        data = await response.json();
      } catch (parseError) {
        console.warn('Failed to parse JSON response:', parseError);
        data = null;
      }
    } else {
      data = await response.text();
    }
    
    return {
      ok: response.ok,
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
      data: data,
      url: response.url
    };
  } catch (error) {
    console.error('apiFetch error:', error);
    throw error;
  }
}

// Test the configuration
console.log("\n1. Testing URL Conversion:");
const testUrls = [
  '/api/ga-dashboard/worship-attendance',
  'http://127.0.0.1:8000/api/ga-dashboard/worship-attendance',
  'https://api.hopemedia.id/api/ga-dashboard/worship-attendance'
];

testUrls.forEach(url => {
  const converted = convertApiUrl(url);
  console.log(`Input: ${url} → Output: ${converted}`);
});

console.log("\n2. Testing apiFetch:");
const testApiFetch = async () => {
  try {
    const response = await apiFetch('/api/ga-dashboard/worship-attendance', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer test-token'
      }
    });
    
    console.log(`📊 Response Status: ${response.status}`);
    console.log(`📊 Response URL: ${response.url}`);
    
    if (response.status === 200) {
      console.log("✅ SUCCESS: API working");
      console.log("📊 Records:", response.data?.total_records || 'N/A');
      console.log("💬 Message:", response.data?.message || 'N/A');
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

testApiFetch();

console.log("\n3. Configuration Summary:");
console.log("=========================");
console.log("✅ Environment:", isProduction ? "Production" : "Development");
console.log("✅ Base URL:", getApiBaseUrl());
console.log("✅ URL Conversion:", isProduction ? "Enabled" : "Disabled");

console.log("\n=== TEST COMPLETE ==="); 
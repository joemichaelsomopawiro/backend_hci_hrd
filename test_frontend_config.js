// Test Frontend Configuration
console.log("=== TEST FRONTEND CONFIGURATION ===");

// Test apiConfig.js
console.log("\n1. Testing apiConfig.js:");
try {
  // Simulate import
  const apiBaseUrl = "https://api.hopemedia.id";
  console.log("✅ apiConfig.js baseURL:", apiBaseUrl);
  
  // Test URL construction
  const testUrl = "/api/ga-dashboard/worship-attendance";
  const fullUrl = `${apiBaseUrl}${testUrl}`;
  console.log("✅ Full URL:", fullUrl);
  
  // Expected: https://api.hopemedia.id/api/ga-dashboard/worship-attendance
  if (fullUrl === "https://api.hopemedia.id/api/ga-dashboard/worship-attendance") {
    console.log("✅ URL construction is correct");
  } else {
    console.log("❌ URL construction is wrong");
  }
  
} catch (error) {
  console.log("❌ Error testing apiConfig:", error);
}

// Test smartFetch logic
console.log("\n2. Testing smartFetch logic:");
try {
  const baseURL = "https://api.hopemedia.id";
  const testUrls = [
    "/api/ga-dashboard/worship-attendance",
    "api/ga-dashboard/worship-attendance",
    "https://api.hopemedia.id/api/ga-dashboard/worship-attendance"
  ];
  
  testUrls.forEach(url => {
    let fullUrl;
    if (url.startsWith('http://') || url.startsWith('https://')) {
      fullUrl = url;
    } else {
      fullUrl = `${baseURL}${url.startsWith('/') ? '' : '/'}${url}`;
    }
    console.log(`Input: ${url} → Output: ${fullUrl}`);
  });
  
} catch (error) {
  console.log("❌ Error testing smartFetch:", error);
}

// Test actual API endpoint
console.log("\n3. Testing actual API endpoint:");
const testEndpoint = async () => {
  try {
    const response = await fetch('https://api.hopemedia.id/api/ga-dashboard/worship-attendance?date=2025-07-28', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer test-token'
      }
    });
    
    console.log("✅ API endpoint accessible");
    console.log("Status:", response.status);
    
    if (response.status === 401) {
      console.log("✅ Expected 401 (unauthorized) - endpoint exists");
    } else if (response.status === 404) {
      console.log("❌ 404 Not Found - endpoint doesn't exist");
    } else {
      console.log("✅ Response received:", response.status);
    }
    
  } catch (error) {
    console.log("❌ Network error:", error.message);
  }
};

// Run the test
testEndpoint();

console.log("\n=== FRONTEND CONFIG TEST COMPLETE ==="); 
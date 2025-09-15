/**
 * API Configuration Utility
 * Mengkonversi URL otomatis dari localhost ke production
 */

// Environment detection
const isDevelopment = import.meta.env.DEV || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
const isProduction = !isDevelopment

// Base URLs
const LOCAL_API_URL = 'http://127.0.0.1:8000'
const LOCAL_API_URL_ALT = 'http://localhost:8000'
const PRODUCTION_API_URL = 'https://api.hopemedia.id' // <-- Sudah diperbaiki di sini

/**
 * Konversi URL otomatis berdasarkan environment
 * @param {string} url - URL yang akan dikonversi
 * @returns {string} URL yang sudah dikonversi
 */
export function convertApiUrl(url) {
  if (!url) return url
  
  // Jika development, gunakan localhost
  if (isDevelopment) {
    return url
  }
  
  // Jika production, konversi ke api.hopemedia.id
  let convertedUrl = url
  
  // Konversi http://127.0.0.1:8000 ke http://api.hopemedia.id
  if (convertedUrl.includes(LOCAL_API_URL)) {
    convertedUrl = convertedUrl.replace(LOCAL_API_URL, PRODUCTION_API_URL)
  }
  
  // Konversi http://localhost:8000 ke http://api.hopemedia.id
  if (convertedUrl.includes(LOCAL_API_URL_ALT)) {
    convertedUrl = convertedUrl.replace(LOCAL_API_URL_ALT, PRODUCTION_API_URL)
  }
  
  return convertedUrl
}

/**
 * Mendapatkan base URL API berdasarkan environment
 * @returns {string} Base URL API
 */
export function getApiBaseUrl() {
  if (isDevelopment) {
    return LOCAL_API_URL
  }
  return PRODUCTION_API_URL
}

/**
 * Mendapatkan full API URL dengan path
 * @param {string} path - Path API (contoh: '/api/auth/login')
 * @returns {string} Full API URL
 */
export function getApiUrl(path) {
  const baseUrl = getApiBaseUrl()
  return `${baseUrl}${path}`
}

/**
 * Konversi storage URL untuk gambar/file
 * @param {string} filePath - Path file di storage
 * @returns {string} Full storage URL
 */
export function getStorageUrl(filePath) {
  if (!filePath) return null
  
  const baseUrl = getApiBaseUrl()
  return `${baseUrl}/storage/${filePath}`
}

/**
 * Environment info untuk debugging
 */
export const apiConfig = {
  isDevelopment,
  isProduction,
  localApiUrl: LOCAL_API_URL,
  productionApiUrl: PRODUCTION_API_URL,
  currentBaseUrl: getApiBaseUrl()
}

// Log environment info di development
if (isDevelopment) {
  console.log('🔧 API Config (Development):', apiConfig)
} else {
  console.log('🚀 API Config (Production):', apiConfig)
} 
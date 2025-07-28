/**
 * Fetch Helper dengan konversi URL otomatis
 * Menggantikan fetch biasa dengan versi yang otomatis konversi URL
 */

import { convertApiUrl, getStorageUrl } from './apiConfig'

/**
 * Fetch dengan konversi URL otomatis
 * @param {string} url - URL yang akan di-fetch
 * @param {object} options - Fetch options
 * @returns {Promise} Fetch response
 */
export async function smartFetch(url, options = {}) {
  // Konversi URL otomatis
  const convertedUrl = convertApiUrl(url)
  
  // Log untuk debugging
  if (url !== convertedUrl) {
    console.log(`🔄 URL converted: ${url} → ${convertedUrl}`)
  }
  
  return fetch(convertedUrl, options)
}

/**
 * Fetch untuk API endpoint dengan konversi otomatis
 * @param {string} endpoint - API endpoint (contoh: '/api/auth/login')
 * @param {object} options - Fetch options
 * @returns {Promise} Parsed response dengan properti ok, status, data
 */
export async function apiFetch(endpoint, options = {}) {
  const baseUrl = import.meta.env.DEV ? 'http://127.0.0.1:8000' : 'https://api.hopemedia.id'
  const fullUrl = `${baseUrl}${endpoint}`
  
  try {
    const response = await smartFetch(fullUrl, options)
    
    // Parse response body
    let data
    const contentType = response.headers.get('content-type')
    
    if (contentType && contentType.includes('application/json')) {
      try {
        data = await response.json()
      } catch (parseError) {
        console.warn('Failed to parse JSON response:', parseError)
        data = null
      }
    } else {
      // Jika bukan JSON, ambil sebagai text
      data = await response.text()
    }
    
    // Return response dengan data yang sudah diparse
    return {
      ok: response.ok,
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
      data: data,
      url: response.url
    }
  } catch (error) {
    console.error('apiFetch error:', error)
    throw error
  }
}

/**
 * Fetch untuk storage URL (gambar/file)
 * @param {string} filePath - Path file di storage
 * @param {object} options - Fetch options
 * @returns {Promise} Fetch response
 */
export async function storageFetch(filePath, options = {}) {
  const storageUrl = getStorageUrl(filePath)
  return smartFetch(storageUrl, options)
}

/**
 * Helper untuk mendapatkan URL yang sudah dikonversi
 * @param {string} url - URL asli
 * @returns {string} URL yang sudah dikonversi
 */
export function getConvertedUrl(url) {
  return convertApiUrl(url)
}

/**
 * Helper untuk mendapatkan storage URL
 * @param {string} filePath - Path file
 * @returns {string} Storage URL
 */
export function getConvertedStorageUrl(filePath) {
  return getStorageUrl(filePath)
} 
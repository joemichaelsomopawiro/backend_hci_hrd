// FIX UNTUK GaDashboard.vue - Routing Issue
// Ganti bagian loadData() method di GaDashboard.vue

// GANTI KODE INI (line 383-402):
/*
if (this.selectedWorshipPeriod === 'today') {
  const today = this.getTodayString();
  apiUrl += `?date=${today}`;
} else if (this.selectedWorshipPeriod === 'week') {
  apiUrl += `/week?start_date=${startWeekStr}`;  // ❌ SALAH
} else if (this.selectedWorshipPeriod === 'month') {
  apiUrl += `/month?start_date=${startMonthStr}`;  // ❌ SALAH  
} else if (this.selectedWorshipPeriod === 'all') {
  apiUrl += '/all';  // ❌ SALAH
}
*/

// DENGAN KODE INI: ✅ BENAR
if (this.selectedWorshipPeriod === 'today') {
  const today = this.getTodayString();
  apiUrl += `?date=${today}`;
  console.log(`🔍 [DEBUG] Mode "Today", loading data for today: ${today}`);
} else if (this.selectedWorshipPeriod === 'week') {
  const startOfWeek = new Date();
  startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1);
  const startWeekStr = startOfWeek.toISOString().split('T')[0];
  // ✅ GUNAKAN PARAMETER, BUKAN PATH TAMBAHAN
  apiUrl += `?period=week&start_date=${startWeekStr}`;
  console.log(`🔍 [DEBUG] Mode "Week", loading data from: ${startWeekStr}`);
} else if (this.selectedWorshipPeriod === 'month') {
  const startOfMonth = new Date();
  startOfMonth.setDate(1);
  const startMonthStr = startOfMonth.toISOString().split('T')[0];
  // ✅ GUNAKAN PARAMETER, BUKAN PATH TAMBAHAN
  apiUrl += `?period=month&start_date=${startMonthStr}`;
  console.log(`🔍 [DEBUG] Mode "Month", loading data from: ${startMonthStr}`);
} else if (this.selectedWorshipPeriod === 'all') {
  // ✅ GUNAKAN PARAMETER, BUKAN PATH TAMBAHAN
  apiUrl += '?all=true';
  console.log(`🔍 [DEBUG] Mode "All", loading all data`);
}

// ATAU SOLUSI ALTERNATIF - Gunakan endpoint terpisah sesuai routes yang ada:
/*
if (this.selectedWorshipPeriod === 'today') {
  const today = this.getTodayString();
  apiUrl = `/api/ga-dashboard/worship-attendance?date=${today}`;
} else if (this.selectedWorshipPeriod === 'week') {
  const startOfWeek = new Date();
  startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1);
  const startWeekStr = startOfWeek.toISOString().split('T')[0];
  apiUrl = `/api/ga-dashboard/worship-attendance/week?start_date=${startWeekStr}`;
} else if (this.selectedWorshipPeriod === 'month') {
  const startOfMonth = new Date();
  startOfMonth.setDate(1);
  const startMonthStr = startOfMonth.toISOString().split('T')[0];
  apiUrl = `/api/ga-dashboard/worship-attendance/month?start_date=${startMonthStr}`;
} else if (this.selectedWorshipPeriod === 'all') {
  apiUrl = `/api/ga-dashboard/worship-attendance/all`;
}
*/ 
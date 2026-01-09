# Cara atau Teknik Kerja Praktik

Selama periode dua bulan bekerja sebagai Full-Stack Developer di Hope Channel Indonesia, penulis fokus pada pengembangan frontend yaitu antarmuka (UI) web dan API menggunakan Framework Laravel untuk backend. Tugas utama mencakup pembuatan API dan antarmuka web, serta pengembangan fitur.

## Alur Kerja Sistem

Arsitektur sistem website hopemedia.id terdiri dari Vue.js pada frontend, sedangkan backend menggunakan Laravel dengan MySQL sebagai basis data. Alur kerja sistem dimulai dari pengguna mengakses sistem website hopemedia.id melalui perangkat mereka masing-masing dengan mengirimkan request HTTP ke server. Permintaan diterima oleh frontend yang menggunakan Vue.js sebagai framework JavaScript untuk membangun user interface dan mengelola state aplikasi. 

Frontend kemudian meneruskan request ke backend Laravel melalui RESTful API dengan autentikasi berbasis token menggunakan Laravel Sanctum. Backend memproses data melalui controller dan model, kemudian berinteraksi dengan database MySQL. Response dikembalikan dalam format JSON dan ditampilkan kepada pengguna melalui antarmuka di browser mereka.

## Pengembangan Backend API

Pengembangan backend dimulai dengan konfigurasi environment variables untuk menyimpan konfigurasi sensitif seperti kredensial database, API keys, dan pengaturan aplikasi. Konfigurasi ini disimpan dalam file environment yang tidak di-commit ke repository untuk menjaga keamanan data sensitif. Konfigurasi kemudian diakses melalui file konfigurasi services untuk digunakan di seluruh aplikasi.

Setelah API endpoint dibuat, pengujian dilakukan menggunakan aplikasi Postman untuk memastikan endpoint berfungsi dengan baik sebelum diintegrasikan ke frontend. Postman digunakan untuk menguji endpoint seperti calendar API dengan mengirimkan request HTTP yang dilengkapi header autentikasi Bearer Token. Response yang diterima dalam format JSON kemudian divalidasi untuk memastikan struktur dan format data sesuai dengan kebutuhan frontend. Pengujian juga dilakukan untuk memverifikasi status code HTTP dan error handling pada berbagai skenario.

## Pengembangan Frontend

Di frontend, dibuat service khusus untuk menangani komunikasi dengan API calendar. Service ini dirancang untuk menangani caching data hari libur di memory untuk mengurangi request ke backend, error handling dengan graceful fallback, dan otomatis menambahkan Bearer Token dari penyimpanan lokal ke setiap request. 

Dashboard HR di hopemedia.id menampilkan kalender hari libur nasional yang terintegrasi dengan Google Calendar API melalui backend, sehingga data hari libur selalu up-to-date dan tidak perlu update manual. User dapat melihat hari libur untuk tahun tertentu dengan memilih tahun di dropdown, dan kalender akan otomatis update menampilkan hari libur yang sesuai termasuk hari libur nasional, cuti bersama, dan perayaan. Setiap jenis hari libur ditampilkan dengan warna dan label khusus untuk memudahkan identifikasi.

## Alur Pengembangan

Pengembangan dilakukan dengan pendekatan incremental, dimulai dari pembuatan API endpoint di backend, kemudian pengujian menggunakan Postman, dan terakhir integrasi ke frontend. Setiap fitur yang dikembangkan melalui tahapan perancangan struktur data dan endpoint, implementasi backend dengan membuat controller, model, dan route di Laravel, pengujian API menggunakan Postman untuk verifikasi endpoint, implementasi frontend dengan membuat service dan komponen Vue.js, integrasi frontend dengan backend API, dan pengujian end-to-end untuk memastikan seluruh alur berfungsi dengan baik. 

Pendekatan ini memastikan setiap komponen sistem berfungsi dengan baik sebelum diintegrasikan, sehingga memudahkan proses debugging dan maintenance di kemudian hari. Selain itu, pendekatan ini juga memungkinkan pengembangan yang lebih terstruktur dan sistematis, dimana setiap tahap dapat divalidasi sebelum melanjutkan ke tahap berikutnya.


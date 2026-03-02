<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsappBotService
 * 
 * Menggantikan FontteService. Mengirim pesan OTP via
 * self-hosted WhatsApp bot (Node.js / whatsapp-web.js)
 * yang berjalan di port 3001.
 */
class WhatsappBotService
{
    private string $botUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->botUrl = rtrim(env('WHATSAPP_BOT_URL', 'http://localhost:3001'), '/');
        $this->secretKey = env('WHATSAPP_BOT_SECRET', '');
    }

    /**
     * Kirim OTP via WhatsApp Bot
     *
     * @param  string $phone   Nomor tujuan (08xx / +628xx / 628xx)
     * @param  string $otpCode Kode OTP 6 digit
     * @return bool
     */
    public function sendOtp(string $phone, string $otpCode): bool
    {
        try {
            $formattedPhone = $this->formatPhoneNumber($phone);
            $appName = env('APP_NAME', 'Hope Channel Indonesia');
            $message = "Kode OTP Hopemedia.id Anda : *{$otpCode}*\n\nBerlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun.";

            Log::info('[WhatsappBot] Mengirim OTP', [
                'original_phone' => $phone,
                'formatted_phone' => $formattedPhone,
                'bot_url' => $this->botUrl,
            ]);

            $response = Http::withHeaders([
                'X-Bot-Secret' => $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->botUrl}/send", [
                        'to' => $formattedPhone,
                        'message' => $message,
                    ]);

            if ($response->successful() && $response->json('success')) {
                Log::info('[WhatsappBot] OTP berhasil dikirim', ['phone' => $formattedPhone]);
                return true;
            }

            Log::error('[WhatsappBot] Gagal mengirim OTP', [
                'phone' => $formattedPhone,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('[WhatsappBot] Exception saat kirim OTP', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Ambil status koneksi bot
     *
     * @return array
     */
    public function getStatus(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Bot-Secret' => $this->secretKey,
            ])->timeout(5)->get("{$this->botUrl}/status");

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return ['status' => 'error', 'last_error' => 'Tidak dapat terhubung ke bot service'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'last_error' => $e->getMessage()];
        }
    }

    /**
     * Ambil QR Code (base64) untuk ditampilkan di admin panel
     *
     * @return array
     */
    public function getQR(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Bot-Secret' => $this->secretKey,
            ])->timeout(5)->get("{$this->botUrl}/qr");

            return $response->json() ?? ['success' => false, 'message' => 'Tidak dapat mengambil QR'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Logout bot (hapus session, bot akan minta scan QR ulang)
     *
     * @return array
     */
    public function logout(): array
    {
        try {
            $response = Http::withHeaders([
                'X-Bot-Secret' => $this->secretKey,
            ])->timeout(10)->post("{$this->botUrl}/logout");

            return $response->json() ?? ['success' => false, 'message' => 'Gagal logout'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Format nomor telepon ke format internasional tanpa +
     * Contoh: 08123456789 → 628123456789
     *
     * @param  string $phone
     * @return string
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Hapus semua karakter kecuali angka
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Jika dimulai dengan 0, ganti dengan 62
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        // Jika dimulai dengan 8 (tanpa 0 atau 62)
        elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}

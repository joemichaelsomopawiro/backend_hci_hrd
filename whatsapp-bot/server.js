/**
 * server.js — HTTP API Server untuk WhatsApp Bot
 * Laravel akan memanggil API ini untuk mengirim OTP
 */

import 'dotenv/config';
import express from 'express';
import cors from 'cors';
import * as bot from './bot.js';

const app = express();
const PORT = process.env.BOT_PORT || 3001;
const SECRET_KEY = process.env.BOT_SECRET_KEY || 'change-this-secret';

// Middleware
app.use(express.json());
app.use(cors({
    origin: ['http://localhost:8000', 'http://127.0.0.1:8000'],
    methods: ['GET', 'POST'],
}));

// Middleware: validasi secret key untuk endpoint sensitif
function requireSecret(req, res, next) {
    const key = req.headers['x-bot-secret'] || req.body?.secret;
    if (key !== SECRET_KEY) {
        return res.status(403).json({
            success: false,
            message: 'Akses ditolak. Secret key tidak valid.'
        });
    }
    next();
}

// ============================================================
// ROUTES
// ============================================================

/**
 * GET /health — Health check (tanpa auth)
 */
app.get('/health', (req, res) => {
    res.json({
        success: true,
        service: 'HCI WhatsApp Bot',
        timestamp: new Date().toISOString()
    });
});

/**
 * GET /status — Status koneksi bot
 * Digunakan oleh frontend admin panel
 */
app.get('/status', requireSecret, (req, res) => {
    const state = bot.getState();
    res.json({
        success: true,
        data: state
    });
});

/**
 * GET /qr — Ambil QR code dalam format base64
 * Digunakan oleh frontend admin panel untuk ditampilkan ke user
 */
app.get('/qr', requireSecret, (req, res) => {
    const qr = bot.getQR();

    if (!qr.available) {
        const state = bot.getState();
        if (state.status === 'connected') {
            return res.json({
                success: false,
                message: 'Bot sudah terhubung, tidak ada QR yang perlu di-scan.',
                status: state.status
            });
        }
        return res.json({
            success: false,
            message: 'QR belum tersedia. Bot sedang loading...',
            status: state.status
        });
    }

    res.json({
        success: true,
        data: {
            qr_base64: qr.qr_base64,
            message: 'Scan QR ini dengan WhatsApp nomor bot Anda'
        }
    });
});

/**
 * POST /send — Kirim pesan WhatsApp
 * Body: { to: "628xxx", message: "..." }
 * Digunakan oleh Laravel (OtpService)
 */
app.post('/send', requireSecret, async (req, res) => {
    const { to, message } = req.body;

    // Validasi input
    if (!to || !message) {
        return res.status(400).json({
            success: false,
            message: 'Parameter "to" dan "message" wajib diisi.'
        });
    }

    if (typeof to !== 'string' || !/^[0-9+\s\-]+$/.test(to)) {
        return res.status(400).json({
            success: false,
            message: 'Format nomor tujuan tidak valid.'
        });
    }

    // Kirim pesan via bot
    const result = await bot.sendMessage(to, message);

    if (result.success) {
        return res.json({
            success: true,
            message: `Pesan berhasil dikirim ke ${to}`
        });
    }

    return res.status(500).json({
        success: false,
        message: 'Gagal mengirim pesan.',
        error: result.error
    });
});

/**
 * POST /logout — Logout bot (hapus session, perlu scan QR ulang)
 */
app.post('/logout', requireSecret, async (req, res) => {
    const result = await bot.logoutBot();

    if (result.success) {
        // Restart client setelah logout
        setTimeout(() => bot.initClient(), 2000);
        return res.json({
            success: true,
            message: 'Bot berhasil logout. Silakan scan QR baru.'
        });
    }

    return res.status(500).json({
        success: false,
        message: 'Gagal logout bot.',
        error: result.error
    });
});

// ============================================================
// START SERVER
// ============================================================

app.listen(PORT, () => {
    console.log(`\n====================================`);
    console.log(`  HCI WhatsApp OTP Bot`);
    console.log(`  HTTP API berjalan di port ${PORT}`);
    console.log(`====================================\n`);

    // Inisialisasi bot WhatsApp
    bot.initClient();
});

// Handle process termination
process.on('SIGINT', async () => {
    console.log('\n[Server] Menerima SIGINT, shutdown...');
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('\n[Server] Menerima SIGTERM, shutdown...');
    process.exit(0);
});

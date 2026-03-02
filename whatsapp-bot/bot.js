/**
 * bot.js — Core WhatsApp Client menggunakan Baileys
 * Lebih ringan, tanpa browser (Chromium), cocok untuk Shared Hosting.
 */

import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} from '@whiskeysockets/baileys';
import pino from 'pino';
import qrcode from 'qrcode';
import fs from 'fs';
import path from 'path';

// State bot
let botState = {
    status: 'disconnected',   // 'disconnected' | 'qr_pending' | 'connected' | 'loading'
    qrBase64: null,
    phone: null,
    connectedAt: null,
    lastError: null,
};

let sock = null;

/**
 * Inisialisasi / restart client WhatsApp menggunakan Baileys
 */
async function initClient() {
    console.log('[WhatsApp Bot] Memulai inisialisasi Baileys...');
    botState.status = 'loading';
    botState.lastError = null;

    const { state, saveCreds } = await useMultiFileAuthState('./session');
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`[WhatsApp Bot] Menggunakan WA v${version.join('.')}, latest: ${isLatest}`);

    sock = makeWASocket({
        version,
        logger: pino({ level: 'silent' }),
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'silent' })),
        },
        browser: ['HCI Bot', 'Chrome', '1.0.0'],
    });

    // Handle Connection Update
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('[WhatsApp Bot] QR Code tersedia.');
            botState.status = 'qr_pending';
            try {
                botState.qrBase64 = await qrcode.toDataURL(qr, {
                    errorCorrectionLevel: 'M',
                    width: 300,
                    margin: 2,
                });
            } catch (err) {
                console.error('[WhatsApp Bot] Gagal generate QR base64:', err.message);
            }
        }

        if (connection === 'close') {
            const shouldReconnect = (lastDisconnect.error)?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log('[WhatsApp Bot] Koneksi terputus. Reason:', lastDisconnect.error, 'Reconnect:', shouldReconnect);

            botState.status = 'disconnected';
            botState.phone = null;

            if (shouldReconnect) {
                console.log('[WhatsApp Bot] Mencoba reconnect...');
                setTimeout(() => initClient(), 5000);
            } else {
                console.log('[WhatsApp Bot] Koneksi ditutup (Logout). Silakan scan QR ulang.');
                botState.lastError = 'Logged out. Need scan QR.';
                // Hapus folder session jika logout bersih
                try {
                    fs.rmSync('./session', { recursive: true, force: true });
                } catch (e) { }
            }
        } else if (connection === 'open') {
            console.log('[WhatsApp Bot] ✅ Koneksi Terbuka!');
            botState.status = 'connected';
            botState.qrBase64 = null;
            botState.phone = sock.user.id.split(':')[0];
            botState.connectedAt = new Date().toISOString();
            botState.lastError = null;
        }
    });

    // Handle Creds Update (Penting agar session tersimpan)
    sock.ev.on('creds.update', saveCreds);
}

/**
 * Kirim pesan WhatsApp
 * @param {string} to - Nomor tujuan (format: 628xxxxxxxxxx)
 * @param {string} message - Isi pesan
 * @returns {Promise<{success: boolean, error?: string}>}
 */
async function sendMessage(to, message) {
    if (botState.status !== 'connected' || !sock) {
        return {
            success: false,
            error: `Bot belum terhubung. Status: ${botState.status}`
        };
    }

    try {
        // Format nomor Baileys: 62xxxxxxxxx@s.whatsapp.net
        const jid = to.replace(/\D/g, '') + '@s.whatsapp.net';

        await sock.sendMessage(jid, { text: message });
        console.log(`[WhatsApp Bot] ✉️ Pesan terkirim ke ${to}`);
        return { success: true };
    } catch (err) {
        console.error(`[WhatsApp Bot] ❌ Gagal kirim ke ${to}:`, err.message);
        return { success: false, error: err.message };
    }
}

/**
 * Logout dan hapus session
 */
async function logoutBot() {
    try {
        if (sock) {
            await sock.logout();
        }
        botState.status = 'disconnected';
        botState.phone = null;
        botState.qrBase64 = null;
        console.log('[WhatsApp Bot] Logout sukses.');
        return { success: true };
    } catch (err) {
        // Jika gagal logout via socket (mungkin sudah terputus), hapus paksa folder session
        try {
            fs.rmSync('./session', { recursive: true, force: true });
            botState.status = 'disconnected';
            return { success: true };
        } catch (e) {
            console.error('[WhatsApp Bot] Error logout:', err.message);
            return { success: false, error: err.message };
        }
    }
}

/**
 * Ambil state bot saat ini
 */
function getState() {
    return {
        status: botState.status,
        phone: botState.phone,
        connected_at: botState.connectedAt,
        last_error: botState.lastError,
        has_qr: botState.qrBase64 !== null,
    };
}

/**
 * Ambil QR code dalam format base64
 */
function getQR() {
    return {
        qr_base64: botState.qrBase64,
        available: botState.qrBase64 !== null,
    };
}

export {
    initClient,
    sendMessage,
    logoutBot,
    getState,
    getQR,
};

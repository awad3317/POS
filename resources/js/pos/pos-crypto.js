/**
 * Pure ES Module for AES-GCM Emergency Backup Encryption via Web Crypto API.
 * Works in browsers and Node 20+ (globalThis.crypto.subtle).
 */

function toBase64(bytes) {
    if (typeof Buffer !== 'undefined') {
        return Buffer.from(bytes).toString('base64');
    }
    let binary = '';
    for (let i = 0; i < bytes.length; i += 1) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

function fromBase64(b64) {
    if (typeof Buffer !== 'undefined') {
        return new Uint8Array(Buffer.from(b64, 'base64'));
    }
    const binary = atob(b64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

async function deriveAesKey(passphrase, saltBytes, iterations = 100000) {
    const subtle = globalThis.crypto?.subtle;
    if (!subtle) {
        throw new Error('Web Crypto SubtleCrypto API is unavailable (requires HTTPS or localhost).');
    }

    const enc = new TextEncoder();
    const keyMaterial = await subtle.importKey(
        'raw',
        enc.encode(String(passphrase)),
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    return subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt: saltBytes,
            iterations,
            hash: 'SHA-256',
        },
        keyMaterial,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

/**
 * Encrypts a JSON-serializable backup payload using AES-256-GCM + PBKDF2-SHA256.
 * @param {Object} data
 * @param {string} passphrase
 * @returns {Promise<Object>}
 */
export async function encryptEmergencyBackup(data, passphrase) {
    const cleanPass = String(passphrase ?? '').trim();
    if (cleanPass.length < 4) {
        throw new Error('كلمة مرور التشفير يجب أن تكون 4 أحرف على الأقل.');
    }

    const cryptoApi = globalThis.crypto;
    const salt = cryptoApi.getRandomValues(new Uint8Array(16));
    const iv = cryptoApi.getRandomValues(new Uint8Array(12));
    const iterations = 100000;

    const aesKey = await deriveAesKey(cleanPass, salt, iterations);
    const encodedPlaintext = new TextEncoder().encode(JSON.stringify(data));
    const cipherBuf = await cryptoApi.subtle.encrypt(
        { name: 'AES-GCM', iv },
        aesKey,
        encodedPlaintext,
    );

    return {
        format: 'yemen-pos-emergency-backup-v1',
        alg: 'AES-256-GCM',
        kdf: 'PBKDF2-SHA256',
        iterations,
        exported_at: new Date().toISOString(),
        salt_b64: toBase64(salt),
        iv_b64: toBase64(iv),
        ciphertext_b64: toBase64(new Uint8Array(cipherBuf)),
    };
}

/**
 * Decrypts an AES-256-GCM emergency backup envelope.
 * @param {Object} envelope
 * @param {string} passphrase
 * @returns {Promise<Object>}
 */
export async function decryptEmergencyBackup(envelope, passphrase) {
    if (!envelope || envelope.format !== 'yemen-pos-emergency-backup-v1') {
        throw new Error('Unsupported emergency backup format.');
    }

    const salt = fromBase64(envelope.salt_b64);
    const iv = fromBase64(envelope.iv_b64);
    const ciphertext = fromBase64(envelope.ciphertext_b64);
    const iterations = Number(envelope.iterations || 100000);

    const aesKey = await deriveAesKey(String(passphrase ?? '').trim(), salt, iterations);
    const plainBuf = await globalThis.crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        aesKey,
        ciphertext,
    );

    return JSON.parse(new TextDecoder().decode(plainBuf));
}

/**
 * Hashes a 4-digit PIN locally using SHA-256 with a static/device salt for offline PIN lock.
 * @param {string} pin
 * @param {string} [deviceSalt]
 * @returns {Promise<string>}
 */
export async function hashLocalPin(pin, deviceSalt = 'yemen-pos-pin') {
    const text = `${deviceSalt}:${String(pin ?? '').trim()}`;
    const subtle = globalThis.crypto?.subtle;
    if (!subtle) {
        throw new Error('Web Crypto SubtleCrypto API is unavailable.');
    }
    const digest = await subtle.digest('SHA-256', new TextEncoder().encode(text));
    return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Pure ES Module for RFC 9562 UUID v7 generation and Yemen POS invoice numbering.
 * Zero DOM dependencies — runnable in browsers and Node test runner.
 */

let lastTimestampMs = 0;
let monotonicSeq = 0;

function randomBytes(length) {
    const out = new Uint8Array(length);
    const cryptoApi = globalThis.crypto;
    if (!cryptoApi || typeof cryptoApi.getRandomValues !== 'function') {
        throw new Error('Secure Web Crypto getRandomValues API is unavailable.');
    }
    cryptoApi.getRandomValues(out);
    return out;
}

/**
 * Generates a time-ordered, strictly monotonic RFC 9562 UUID v7 string.
 * @returns {string}
 */
export function generateUuidV7() {
    let nowMs = Date.now();
    const rand = randomBytes(10);

    if (nowMs <= lastTimestampMs) {
        monotonicSeq += 1;
        if (monotonicSeq > 0x0fff) {
            lastTimestampMs += 1;
            monotonicSeq = 0;
        }
        nowMs = lastTimestampMs;
    } else {
        lastTimestampMs = nowMs;
        // Seed lower 11 bits of rand_a to leave burst headroom within the same ms
        monotonicSeq = ((rand[0] & 0x07) << 8) | rand[1];
    }

    const bytes = new Uint8Array(16);

    // 48-bit big-endian Unix epoch milliseconds
    let ts = BigInt(nowMs);
    for (let i = 5; i >= 0; i -= 1) {
        bytes[i] = Number(ts & 0xffn);
        ts >>= 8n;
    }

    // Version 7 (0111) + 12-bit monotonic rand_a
    bytes[6] = 0x70 | ((monotonicSeq >> 8) & 0x0f);
    bytes[7] = monotonicSeq & 0xff;

    // Variant 10xx + 62-bit rand_b
    bytes[8] = 0x80 | (rand[2] & 0x3f);
    for (let i = 0; i < 7; i += 1) {
        bytes[9 + i] = rand[3 + i];
    }

    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20, 32)}`;
}

/**
 * Validates whether a string matches UUID v7 format.
 * @param {string} value
 * @returns {boolean}
 */
export function isValidUuidV7(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value ?? ''));
}

/**
 * Formats sequential POS invoice numbers matching OrderService::createSale:
 * INV-{TERMINAL}-{YYYY}-{SEQ} with 6 zero-padded digits.
 *
 * @param {string} terminalCode
 * @param {number|string} sequence
 * @param {Date|string|number} [date]
 * @returns {string}
 */
export function formatInvoiceNumber(terminalCode, sequence, date = new Date()) {
    const cleanCode = String(terminalCode || 'T01').trim().toUpperCase();
    const d = date instanceof Date ? date : new Date(date);
    const year = Number.isNaN(d.getTime()) ? new Date().getFullYear() : d.getFullYear();
    const seqInt = Math.max(1, Number.parseInt(String(sequence ?? 1), 10) || 1);
    const paddedSeq = String(seqInt).padStart(6, '0');

    return `INV-${cleanCode}-${year}-${paddedSeq}`;
}

/**
 * Extracts trailing numeric sequence from an invoice number.
 * @param {string} invoiceNumber
 * @returns {number}
 */
export function parseInvoiceSequence(invoiceNumber) {
    const match = /(\d+)$/.exec(String(invoiceNumber ?? ''));
    return match ? Number.parseInt(match[1], 10) : 0;
}

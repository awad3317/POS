import { formatMoneyDisplay } from './pos-cart-math.js';

/**
 * Formats Yemeni phone numbers to international WhatsApp digits (9677XXXXXXXX).
 * Mirrors App\Support\WhatsAppLink::formatPhone().
 *
 * @param {string|null|undefined} phone
 * @returns {string}
 */
export function formatYemeniWhatsAppPhone(phone) {
    if (!phone) {
        return '';
    }
    let digits = String(phone).replace(/\D+/g, '');
    if (digits.startsWith('00')) {
        digits = digits.slice(2);
    }
    if (digits.length === 10 && digits.startsWith('07')) {
        digits = `967${digits.slice(1)}`;
    } else if (digits.length === 9 && digits.startsWith('7')) {
        digits = `967${digits}`;
    }
    return digits;
}

/**
 * Builds a https://wa.me/{phone}?text=... URL safely encoded in UTF-8.
 *
 * @param {string|null|undefined} phone
 * @param {string} message
 * @returns {string}
 */
export function buildWhatsAppUrl(phone, message) {
    const cleanPhone = formatYemeniWhatsAppPhone(phone);
    const encoded = encodeURIComponent(String(message ?? ''));
    return cleanPhone ? `https://wa.me/${cleanPhone}?text=${encoded}` : `https://wa.me/?text=${encoded}`;
}

/**
 * Formats a POS sale receipt snapshot into an Arabic WhatsApp message and returns the wa.me URL.
 *
 * @param {Object} snapshot
 * @returns {string}
 */
export function buildPosOrderWhatsAppUrl(snapshot) {
    if (!snapshot) {
        return 'https://wa.me/';
    }

    const store = snapshot.store || {};
    const storeName = store.name || 'المتجر';
    const currency = store.currency_symbol || 'ر.ي';
    const dp = Number(store.decimal_places ?? 0);

    const totalFmt = `${formatMoneyDisplay(snapshot.total_amount || '0', dp)} ${currency}`;
    const paidFmt = `${formatMoneyDisplay(snapshot.paid_amount || '0', dp)} ${currency}`;
    const creditNum = Number(snapshot.credit_amount || 0);
    const creditFmt = `${formatMoneyDisplay(snapshot.credit_amount || '0', dp)} ${currency}`;

    const lines = [
        `🧾 *فاتورة مبيعات — ${storeName}*`,
        `رقم الفاتورة: *${snapshot.invoice_number || '-'}*`,
        `العميل: ${snapshot.customer_name || 'عميل نقدي'}`,
    ];

    if (Array.isArray(snapshot.items) && snapshot.items.length > 0) {
        lines.push('----------------');
        for (const it of snapshot.items.slice(0, 15)) {
            const qty = String(it.quantity || '1').replace(/\.000$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
            const net = formatMoneyDisplay(it.net_total || '0', dp);
            lines.push(`• ${it.product_name} (${qty}) = ${net} ${currency}`);
        }
        if (snapshot.items.length > 15) {
            lines.push(`... (+${snapshot.items.length - 15} أصناف أخرى)`);
        }
        lines.push('----------------');
    }

    lines.push(`الإجمالي: *${totalFmt}*`);
    lines.push(`المدفوع: ${paidFmt}`);
    if (creditNum > 0) {
        lines.push(`المتبقي آجل: *${creditFmt}*`);
    }

    const footerNote = store.receipt_settings?.footer_note;
    if (footerNote) {
        lines.push(footerNote);
    }

    return buildWhatsAppUrl(snapshot.customer_phone, lines.join('\n'));
}

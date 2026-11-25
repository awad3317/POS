import QRCode from 'qrcode';
import { formatMoneyDisplay, PosDecimal, toQty } from './pos-cart-math.js';

const METHOD_AR = {
    cash: 'نقدي',
    kuraimi: 'الكريمي',
    jawali: 'جوالي',
    floosak: 'فلوسك',
    onecash: 'ون كاش',
    card: 'بطاقة',
    bank_transfer: 'تحويل بنكي',
};

const SOURCE_AR = {
    cash_drawer: 'درج الكاشير',
    owner_pocket: 'حساب المالك',
    bank: 'البنك',
};

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function fmtQty(value) {
    try {
        const d = new PosDecimal(toQty(value ?? '0'));
        if (!d.isFinite() || d.isZero()) {
            return '0';
        }
        const trimmed = d.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
        return trimmed === '' || trimmed === '-0' ? '0' : trimmed;
    } catch {
        return '0';
    }
}

function fmtDate(iso) {
    try {
        const d = iso ? new Date(iso) : new Date();
        if (Number.isNaN(d.getTime())) {
            return String(iso || '');
        }
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        return `${y}-${m}-${day} ${hh}:${mm}`;
    } catch {
        return String(iso || '');
    }
}

function resolvePaperCss(store) {
    const width = store?.receipt_settings?.paper_width === '58mm' ? '58mm' : '80mm';
    const fontSize = width === '58mm' ? '11px' : '12px';

    return `
        @font-face {
            font-family: "IBM Plex Sans Arabic";
            font-style: normal;
            font-weight: 400;
            src: url("/fonts/ibm-plex-sans-arabic-400.woff2") format("woff2");
        }
        @font-face {
            font-family: "IBM Plex Sans Arabic";
            font-style: normal;
            font-weight: 700;
            src: url("/fonts/ibm-plex-sans-arabic-700.woff2") format("woff2");
        }
        @page {
            size: ${width} auto;
            margin: 2mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 2mm;
            width: ${width};
            font-family: "IBM Plex Sans Arabic", ui-sans-serif, system-ui, sans-serif;
            font-size: ${fontSize};
            line-height: 1.45;
            color: #000;
            background: #fff;
            direction: rtl;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { color: #333; font-size: 10px; }
        .hr { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 11px; }
        th, td { padding: 3px 2px; text-align: right; vertical-align: top; }
        th { border-bottom: 1px solid #000; font-weight: 700; }
        td { border-bottom: 1px dotted #aaa; }
        .num { text-align: left; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .total-box { font-size: 14px; font-weight: 700; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin: 4px 0; }
        .qr-wrap { display: flex; flex-direction: column; align-items: center; margin-top: 6px; }
        .qr-wrap svg { width: 84px; height: 84px; }
    `;
}

function wrapReceiptHtml(store, title, bodyHtml) {
    return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>${esc(title)}</title>
<style>${resolvePaperCss(store)}</style>
</head>
<body>${bodyHtml}</body>
</html>`;
}

export async function buildSaleReceiptHtml(snapshot) {
    const store = snapshot.store || {};
    const receipt = store.receipt_settings || {};
    const currency = store.currency_symbol || 'ر.ي';
    const dp = Number(store.decimal_places ?? 0);
    const fmt = (val) => formatMoneyDisplay(val ?? '0', dp);

    let qrSvg = '';
    if (receipt.show_qr !== false && snapshot.invoice_number) {
        try {
            qrSvg = await QRCode.toString(String(snapshot.invoice_number), {
                type: 'svg',
                width: 88,
                margin: 1,
                errorCorrectionLevel: 'M',
            });
        } catch {
            qrSvg = '';
        }
    }

    const itemsRows = (snapshot.items || []).map((item) => {
        const discNum = Number(item.discount_amount || 0);
        const discLine = discNum > 0 ? `<div class="muted">خصم: -${esc(fmt(item.discount_amount))}</div>` : '';
        return `
            <tr>
                <td>
                    <div class="bold">${esc(item.product_name)}</div>
                    <div class="muted">${esc(fmtQty(item.quantity))} ${esc(item.unit_name_snapshot || 'حبة')} × ${esc(fmt(item.unit_price))}</div>
                    ${discLine}
                </td>
                <td class="num bold">${esc(fmt(item.net_total))}</td>
            </tr>
        `;
    }).join('');

    const paymentsRows = (snapshot.payments || []).map((p) => {
        const label = METHOD_AR[p.method] || p.method;
        const ref = p.reference_number ? ` (${esc(p.reference_number)})` : '';
        return `<div class="row"><span>${esc(label)}${ref}:</span><span class="num">${esc(fmt(p.amount))} ${esc(currency)}</span></div>`;
    }).join('');

    const totalDiscount = new PosDecimal(snapshot.item_discount_total || '0')
        .add(new PosDecimal(snapshot.order_discount_amount || '0'))
        .toFixed(4);

    const bodyHtml = `
        <div class="center">
            <div class="bold" style="font-size:15px">${esc(store.name || 'Yemen POS')}</div>
            ${store.phone ? `<div class="muted">هاتف: ${esc(store.phone)}</div>` : ''}
            ${store.address ? `<div class="muted">${esc(store.address)}</div>` : ''}
            ${receipt.header_note ? `<div class="muted" style="margin-top:2px">${esc(receipt.header_note)}</div>` : ''}
        </div>
        <div class="hr"></div>
        <div class="row"><span>رقم الفاتورة:</span><span class="bold num">${esc(snapshot.invoice_number)}</span></div>
        <div class="row"><span>التاريخ:</span><span class="num">${esc(fmtDate(snapshot.sold_at))}</span></div>
        <div class="row"><span>الكاشير:</span><span>${esc(snapshot.cashier?.name || '-')}</span></div>
        <div class="row"><span>العميل:</span><span class="bold">${esc(snapshot.customer_name || 'عميل نقدي')}</span></div>
        <div class="hr"></div>
        <table>
            <thead>
                <tr><th>الصنف</th><th class="num">الإجمالي</th></tr>
            </thead>
            <tbody>${itemsRows}</tbody>
        </table>
        <div class="row"><span>المجموع:</span><span class="num">${esc(fmt(snapshot.subtotal))} ${esc(currency)}</span></div>
        ${Number(totalDiscount) > 0 ? `<div class="row"><span>إجمالي الخصم:</span><span class="num">-${esc(fmt(totalDiscount))} ${esc(currency)}</span></div>` : ''}
        ${Number(snapshot.tax_total || 0) > 0 ? `<div class="row"><span>الضريبة:</span><span class="num">${esc(fmt(snapshot.tax_total))} ${esc(currency)}</span></div>` : ''}
        <div class="row total-box"><span>الصافي:</span><span class="num">${esc(fmt(snapshot.total_amount))} ${esc(currency)}</span></div>
        ${paymentsRows}
        <div class="row"><span>المدفوع الصافي:</span><span class="num bold">${esc(fmt(snapshot.paid_amount))} ${esc(currency)}</span></div>
        ${Number(snapshot.change_amount || 0) > 0 ? `<div class="row"><span>الباقي للعميل:</span><span class="num bold">${esc(fmt(snapshot.change_amount))} ${esc(currency)}</span></div>` : ''}
        ${Number(snapshot.credit_amount || 0) > 0 ? `<div class="row bold"><span>المتبقي آجل:</span><span class="num">${esc(fmt(snapshot.credit_amount))} ${esc(currency)}</span></div>` : ''}
        ${snapshot.notes ? `<div class="hr"></div><div class="muted">ملاحظة: ${esc(snapshot.notes)}</div>` : ''}
        ${qrSvg ? `<div class="qr-wrap">${qrSvg}<div class="muted">${esc(snapshot.invoice_number)}</div></div>` : ''}
        <div class="hr"></div>
        <div class="center muted">${esc(receipt.footer_note || 'شكراً لتسوقكم معنا')}</div>
    `;

    return wrapReceiptHtml(store, `فاتورة ${snapshot.invoice_number}`, bodyHtml);
}

export function buildZReportHtml(snapshot) {
    const store = snapshot.store || {};
    const shift = snapshot.shift || {};
    const currency = store.currency_symbol || 'ر.ي';
    const dp = Number(store.decimal_places ?? 0);
    const fmt = (val) => formatMoneyDisplay(val ?? '0', dp);

    const bodyHtml = `
        <div class="center">
            <div class="bold" style="font-size:15px">${esc(store.name || 'Yemen POS')}</div>
            <div class="bold" style="margin-top:2px">تقرير إغلاق الوردية (Z-Report)</div>
        </div>
        <div class="hr"></div>
        <div class="row"><span>الجهاز:</span><span class="bold">${esc(shift.terminal_code || snapshot.terminal?.code || 'T01')}</span></div>
        <div class="row"><span>الكاشير:</span><span>${esc(snapshot.cashier?.name || '-')}</span></div>
        <div class="row"><span>وقت الفتح:</span><span class="num">${esc(fmtDate(shift.opened_at))}</span></div>
        <div class="row"><span>وقت الإغلاق:</span><span class="num">${esc(fmtDate(shift.closed_at))}</span></div>
        <div class="hr"></div>
        <div class="row"><span>الرصيد الافتتاحي:</span><span class="num">${esc(fmt(shift.opening_cash))} ${esc(currency)}</span></div>
        <div class="row"><span>المبيعات النقدية:</span><span class="num">+${esc(fmt(shift.cash_sales_total))} ${esc(currency)}</span></div>
        <div class="row"><span>سندات قبض نقدية:</span><span class="num">+${esc(fmt(shift.cash_in_total))} ${esc(currency)}</span></div>
        <div class="row"><span>مرتجعات نقدية:</span><span class="num">-${esc(fmt(shift.cash_returns_total || '0'))} ${esc(currency)}</span></div>
        <div class="row"><span>مصروفات وسحوبات الدرج:</span><span class="num">-${esc(fmt(shift.cash_out_total))} ${esc(currency)}</span></div>
        <div class="hr"></div>
        <div class="row bold"><span>النقد المتوقع:</span><span class="num">${esc(fmt(shift.expected_cash))} ${esc(currency)}</span></div>
        <div class="row bold"><span>النقد الفعلي المعدود:</span><span class="num">${esc(fmt(shift.actual_cash))} ${esc(currency)}</span></div>
        <div class="row total-box"><span>الفرق (عجز/زيادة):</span><span class="num">${esc(fmt(shift.difference))} ${esc(currency)}</span></div>
        ${shift.notes ? `<div class="muted">ملاحظات: ${esc(shift.notes)}</div>` : ''}
        <div class="hr"></div>
        <div class="center muted">تمت الطباعة: ${esc(fmtDate(snapshot.printed_at))}</div>
    `;

    return wrapReceiptHtml(store, 'تقرير إغلاق الوردية Z-Report', bodyHtml);
}

export function buildDrawerVoucherHtml(snapshot) {
    const store = snapshot.store || {};
    const currency = store.currency_symbol || 'ر.ي';
    const dp = Number(store.decimal_places ?? 0);
    const fmt = (val) => formatMoneyDisplay(val ?? '0', dp);

    const titles = {
        expense: 'سند صرف مصروف نثري',
        employee_advance: 'سند صرف سلفة عامل',
        supplier_payment: 'سند صرف دفعة مورد',
        customer_settlement: 'سند قبض من عميل',
    };

    const title = titles[snapshot.voucher_type] || 'سند حركة درج';

    const bodyHtml = `
        <div class="center">
            <div class="bold" style="font-size:15px">${esc(store.name || 'Yemen POS')}</div>
            <div class="bold" style="margin-top:2px">${esc(title)}</div>
        </div>
        <div class="hr"></div>
        <div class="row"><span>التاريخ:</span><span class="num">${esc(fmtDate(snapshot.created_at))}</span></div>
        <div class="row"><span>الكاشير:</span><span>${esc(snapshot.cashier?.name || '-')}</span></div>
        ${snapshot.category ? `<div class="row"><span>البند:</span><span class="bold">${esc(snapshot.category)}</span></div>` : ''}
        ${snapshot.payee_name ? `<div class="row"><span>المستفيد/العميل:</span><span class="bold">${esc(snapshot.payee_name)}</span></div>` : ''}
        ${snapshot.method ? `<div class="row"><span>الوسيلة:</span><span>${esc(METHOD_AR[snapshot.method] || snapshot.method)}</span></div>` : ''}
        ${snapshot.payment_source ? `<div class="row"><span>المصدر:</span><span>${esc(SOURCE_AR[snapshot.payment_source] || snapshot.payment_source)}</span></div>` : ''}
        ${snapshot.reference_number ? `<div class="row"><span>رقم المرجع:</span><span class="num">${esc(snapshot.reference_number)}</span></div>` : ''}
        <div class="row total-box"><span>المبلغ:</span><span class="num">${esc(fmt(snapshot.amount))} ${esc(currency)}</span></div>
        ${snapshot.notes ? `<div class="muted">ملاحظات: ${esc(snapshot.notes)}</div>` : ''}
        <div class="hr"></div>
        <div class="row muted" style="margin-top:14px"><span>توقيع الكاشير: ..........</span><span>توقيع المستلم: ..........</span></div>
    `;

    return wrapReceiptHtml(store, title, bodyHtml);
}

export async function buildReceiptHtml(snapshot) {
    if (!snapshot) {
        return '';
    }
    if (snapshot.type === 'shift_close') {
        return buildZReportHtml(snapshot);
    }
    if (snapshot.type === 'drawer_voucher') {
        return buildDrawerVoucherHtml(snapshot);
    }
    return buildSaleReceiptHtml(snapshot);
}

/**
 * Prints an HTML document cleanly via hidden iframe.srcdoc in < 200ms without external dependencies.
 *
 * @param {string} html
 * @returns {Promise<void>}
 */
export function printHtmlViaIframe(html) {
    return new Promise((resolve) => {
        if (typeof document === 'undefined' || !html) {
            resolve();
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.position = 'fixed';
        iframe.style.right = '-9999px';
        iframe.style.bottom = '-9999px';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';

        let printed = false;
        const cleanup = () => {
            window.setTimeout(() => {
                iframe.remove();
            }, 1000);
        };

        const triggerPrint = () => {
            if (printed) {
                return;
            }
            printed = true;
            try {
                const win = iframe.contentWindow;
                if (win) {
                    win.focus();
                    win.print();
                }
            } catch {
                // Ignore print errors in headless/sandbox environments
            } finally {
                cleanup();
                resolve();
            }
        };

        iframe.onload = () => {
            window.setTimeout(triggerPrint, 30);
        };

        iframe.srcdoc = html;
        document.body.appendChild(iframe);

        // Safety fallback if onload doesn't fire within 200ms
        window.setTimeout(triggerPrint, 200);
    });
}

export async function printThermalReceipt(snapshot) {
    const html = await buildReceiptHtml(snapshot);
    if (!html) {
        return;
    }
    await printHtmlViaIframe(html);
}

/**
 * Optional WebUSB ESC/POS Cash Drawer Kick Pulse (ESC p m t1 t2).
 * Triggers connected thermal printer / cash drawer without backend involvement.
 */
export async function kickCashDrawerViaWebUsb() {
    if (typeof navigator === 'undefined' || !navigator.usb) {
        return { ok: false, message: 'المتصفح أو الاتصال (HTTPS مطلوب) لا يدعم WebUSB.' };
    }

    try {
        const existingDevices = await navigator.usb.getDevices();
        const device = existingDevices[0] || await navigator.usb.requestDevice({ filters: [] });
        if (!device) {
            return { ok: false, message: 'لم يتم اختيار طابعة USB.' };
        }

        if (!device.opened) {
            await device.open();
        }
        if (device.configuration === null) {
            await device.selectConfiguration(1);
        }

        const iface = device.configuration?.interfaces?.[0];
        const ifaceNumber = iface?.interfaceNumber ?? 0;
        await device.claimInterface(ifaceNumber);

        const endpoint = iface?.alternates?.[0]?.endpoints?.find((ep) => ep.direction === 'out');
        if (!endpoint) {
            return { ok: false, message: 'لم يتم العثور على منفذ إخراج ESC/POS.' };
        }

        // Standard ESC/POS drawer kick command: ESC p 0 25 250
        const kickPayload = new Uint8Array([0x1B, 0x70, 0x00, 0x19, 0xFA]);
        await device.transferOut(endpoint.endpointNumber, kickPayload);
        return { ok: true, message: 'تم إرسال نبضة فتح الدرج عبر WebUSB.' };
    } catch (err) {
        return { ok: false, message: err?.message || 'تعذر فتح درج النقدية عبر WebUSB.' };
    }
}

let printerInitialized = false;

export function initPosPrinter() {
    if (printerInitialized || typeof window === 'undefined') {
        return;
    }
    printerInitialized = true;
    window.addEventListener('pos:print-receipt', (event) => {
        printThermalReceipt(event.detail).catch(() => {});
    });
}

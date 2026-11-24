import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import JsBarcode from 'jsbarcode';
import { formatMoneyDisplay } from './pos/pos-cart-math.js';
import { printHtmlViaIframe } from './pos/pos-print.js';

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.JsBarcode = JsBarcode;
window.Alpine = Alpine;

function esc(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function generateBarcodeSvg(code, height = 28, barWidth = 1.4, fontSize = 10) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const clean = String(code ?? '').trim();
    const isEan13Candidate = /^\d{13}$/.test(clean);

    try {
        JsBarcode(svg, clean, {
            format: isEan13Candidate ? 'EAN13' : 'CODE128',
            width: barWidth,
            height,
            displayValue: true,
            fontSize,
            margin: 2,
            background: '#ffffff',
            lineColor: '#000000',
        });
    } catch {
        JsBarcode(svg, clean, {
            format: 'CODE128',
            width: barWidth,
            height,
            displayValue: true,
            fontSize,
            margin: 2,
            background: '#ffffff',
            lineColor: '#000000',
        });
    }

    return svg.outerHTML;
}

const LABEL_DIMENSIONS = {
    '50x25mm': { widthMm: 50, heightMm: 25, svgHeight: 24, barWidth: 1.25, fontSize: 9 },
    '40x30mm': { widthMm: 40, heightMm: 30, svgHeight: 26, barWidth: 1.15, fontSize: 9 },
    '38x25mm': { widthMm: 38, heightMm: 25, svgHeight: 22, barWidth: 1.05, fontSize: 8 },
    'a4': { widthMm: 48, heightMm: 26, svgHeight: 24, barWidth: 1.2, fontSize: 9 },
};

Alpine.data('barcodeLabelsApp', (config = {}) => ({
    store: config.store || {},
    searchUrl: config.searchUrl || '/barcodes/search',
    query: '',
    loading: false,
    searchItems: config.initialItems || [],
    selectedLabels: [],
    paperSize: '50x25mm',
    showStoreName: true,
    showProductName: true,
    showPrice: true,
    _debounceTimer: null,

    init() {
        if (this.searchItems.length > 0 && this.selectedLabels.length === 0) {
            this.addLabel(this.searchItems[0], 1);
        }
    },

    fmtPrice(price) {
        const dp = Number(this.store?.decimal_places ?? 0);
        const sym = this.store?.currency_symbol || 'ر.ي';
        return `${formatMoneyDisplay(price || '0', dp)} ${sym}`;
    },

    renderPreviewSvg(code) {
        if (!code) {
            return '';
        }
        return generateBarcodeSvg(code, 26, 1.25, 9);
    },

    onSearchInput() {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
        }
        this._debounceTimer = setTimeout(() => {
            this.fetchSearch();
        }, 180);
    },

    async fetchSearch() {
        this.loading = true;
        try {
            const url = `${this.searchUrl}?q=${encodeURIComponent(String(this.query || '').trim())}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (res.ok) {
                const data = await res.json();
                this.searchItems = data.items || [];
            }
        } finally {
            this.loading = false;
        }
    },

    addLabel(item, qty = 1) {
        if (!item || !item.barcode) {
            return;
        }
        const existing = this.selectedLabels.find((l) => l.key === item.key);
        if (existing) {
            existing.copies = Math.min(500, Number(existing.copies || 1) + qty);
        } else {
            this.selectedLabels.push({
                ...item,
                copies: qty,
            });
        }
    },

    removeLabel(idx) {
        this.selectedLabels.splice(idx, 1);
    },

    totalStickerCount() {
        return this.selectedLabels.reduce((acc, item) => acc + Math.max(1, Number(item.copies || 1)), 0);
    },

    async printLabels() {
        if (this.selectedLabels.length === 0) {
            return;
        }

        const spec = LABEL_DIMENSIONS[this.paperSize] || LABEL_DIMENSIONS['50x25mm'];
        const isA4 = this.paperSize === 'a4';
        const storeName = this.store?.name || 'Yemen POS';

        const stickersHtml = [];
        for (const item of this.selectedLabels) {
            const copies = Math.max(1, Math.min(500, Number.parseInt(String(item.copies || 1), 10) || 1));
            const svgMarkup = generateBarcodeSvg(item.barcode, spec.svgHeight, spec.barWidth, spec.fontSize);
            const titleText = item.subtitle ? `${item.name} (${item.subtitle})` : item.name;

            const singleSticker = `
                <div class="sticker">
                    ${this.showStoreName ? `<div class="store">${esc(storeName)}</div>` : ''}
                    ${this.showProductName ? `<div class="title">${esc(titleText)}</div>` : ''}
                    <div class="barcode">${svgMarkup}</div>
                    ${this.showPrice ? `<div class="price">${esc(this.fmtPrice(item.price))}</div>` : ''}
                </div>
            `;

            for (let c = 0; c < copies; c += 1) {
                stickersHtml.push(singleSticker);
            }
        }

        const pageCss = isA4
            ? `
                @page { size: A4 portrait; margin: 6mm; }
                body { width: 198mm; }
                .sheet { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2.5mm; }
                .sticker {
                    border: 0.3mm dashed #cbd5e1;
                    border-radius: 1.5mm;
                    padding: 1.5mm;
                    height: 28mm;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    page-break-inside: avoid;
                    break-inside: avoid;
                }
            `
            : `
                @page { size: ${spec.widthMm}mm ${spec.heightMm}mm; margin: 0; }
                body { width: ${spec.widthMm}mm; }
                .sheet { display: block; }
                .sticker {
                    width: ${spec.widthMm}mm;
                    height: ${spec.heightMm}mm;
                    padding: 1mm 1.5mm;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    page-break-after: always;
                    break-after: page;
                    overflow: hidden;
                }
                .sticker:last-child { page-break-after: auto; break-after: auto; }
            `;

        const html = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>ملصقات الباركود</title>
<style>
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
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 0;
        font-family: "IBM Plex Sans Arabic", ui-sans-serif, system-ui, sans-serif;
        color: #000;
        background: #fff;
        text-align: center;
    }
    ${pageCss}
    .store { font-size: 7.5px; font-weight: 700; line-height: 1.1; max-width: 100%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .title { font-size: 8px; font-weight: 600; line-height: 1.15; max-width: 100%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; margin: 0.5mm 0; }
    .barcode { max-width: 100%; display: flex; justify-content: center; }
    .barcode svg { max-width: 100%; height: auto; }
    .price { font-size: 9px; font-weight: 700; line-height: 1.1; }
</style>
</head>
<body><div class="sheet">${stickersHtml.join('')}</div></body>
</html>`;

        await printHtmlViaIframe(html);
    },
}));

Alpine.start();

import test from 'node:test';
import assert from 'node:assert/strict';

import {
    generateUuidV7,
    isValidUuidV7,
    formatInvoiceNumber,
    parseInvoiceSequence,
} from '../../resources/js/pos/pos-uuid.js';
import { resolveBarcodeScan } from '../../resources/js/pos/pos-barcode.js';
import {
    calculateLineItem,
    calculateCartTotals,
    calculatePaymentBalance,
    formatMoneyDisplay,
} from '../../resources/js/pos/pos-cart-math.js';
import {
    encryptEmergencyBackup,
    decryptEmergencyBackup,
    hashLocalPin,
} from '../../resources/js/pos/pos-crypto.js';
import {
    buildSaleReceiptHtml,
    buildZReportHtml,
    buildDrawerVoucherHtml,
} from '../../resources/js/pos/pos-print.js';
import {
    formatYemeniWhatsAppPhone,
    buildPosOrderWhatsAppUrl,
} from '../../resources/js/pos/pos-whatsapp.js';
import {
    createPosApp,
    formatInputMoney,
    formatInputQty,
} from '../../resources/js/pos/pos-app.js';

const sampleCatalog = {
    products: [
        {
            id: 1,
            type: 'simple',
            name: 'عصير راني خوخ 240 مل',
            barcode: '6281007041126',
            scale_plu_code: null,
            sale_price: '300.0000',
            min_allowed_sale_price: '250.0000',
            tax_rate: '0.00',
            is_active: true,
        },
        {
            id: 2,
            type: 'weighted',
            name: 'جبن بلدي تعزي',
            barcode: '2010025000000',
            scale_plu_code: '10025',
            sale_price: '5000.0000',
            min_allowed_sale_price: '4200.0000',
            tax_rate: '0.00',
            is_active: true,
        },
        {
            id: 3,
            type: 'variant',
            name: 'حذاء جلدي تركي كلاسيك',
            barcode: '8690001122000',
            scale_plu_code: null,
            sale_price: '16000.0000',
            min_allowed_sale_price: '11200.0000',
            tax_rate: '0.00',
            is_active: true,
        },
    ],
    variants: [
        {
            id: 11,
            product_id: 3,
            size: '41',
            color: 'أسود',
            attribute_label: '41 - أسود',
            barcode: '8690001122041',
            sale_price: '16000.0000',
            min_allowed_sale_price: '11200.0000',
            is_active: true,
        },
        {
            id: 12,
            product_id: 3,
            size: '42',
            color: 'أسود',
            attribute_label: '42 - أسود',
            barcode: '8690001122042',
            sale_price: '16500.0000',
            min_allowed_sale_price: '11200.0000',
            is_active: true,
        },
    ],
    units: [
        {
            id: 21,
            product_id: 1,
            unit_name: 'حبة',
            conversion_factor: '1.000',
            barcode: '6281007041126',
            sale_price: '300.0000',
            min_allowed_sale_price: '250.0000',
            is_default: true,
        },
        {
            id: 22,
            product_id: 1,
            unit_name: 'باكت 6 حبات',
            conversion_factor: '6.000',
            barcode: '6281007041126-P6',
            sale_price: '1750.0000',
            min_allowed_sale_price: '1480.0000',
            is_default: false,
        },
        {
            id: 23,
            product_id: 1,
            unit_name: 'كرتون 24 حبة',
            conversion_factor: '24.000',
            barcode: '6281007041126-C24',
            sale_price: '6800.0000',
            min_allowed_sale_price: '5900.0000',
            is_default: false,
        },
    ],
};

test('Smart 4-priority barcode parser resolves scale (20 weight & 21 price), units, variants, and base products', () => {
    // Priority 1a: Scale prefix 20 (weight in grams: 01750g -> 1.750 kg)
    const weightRes = resolveBarcodeScan('2010025017504', sampleCatalog, { decimalPlaces: 0 });
    assert.equal(weightRes.matched, true);
    assert.equal(weightRes.priority, 1);
    assert.equal(weightRes.matchType, 'scale_weight');
    assert.equal(weightRes.product.id, 2);
    assert.equal(weightRes.quantity, '1.750');
    assert.equal(weightRes.unitPrice, '5000.0000');

    // Priority 1b: Scale prefix 21 (embedded price: 02500 YER / 5000 per kg -> 0.500 kg)
    const priceRes = resolveBarcodeScan('2110025025009', sampleCatalog, { decimalPlaces: 0 });
    assert.equal(priceRes.matched, true);
    assert.equal(priceRes.priority, 1);
    assert.equal(priceRes.matchType, 'scale_price');
    assert.equal(priceRes.product.id, 2);
    assert.equal(priceRes.embeddedPrice, '2500.0000');
    assert.equal(priceRes.quantity, '0.500');
    assert.equal(priceRes.unitPrice, '5000.0000');

    // Priority 1c: Scale prefix 21 with 2-decimal currency (02500 -> 25.00)
    const sarCatalog = {
        products: [{ id: 9, name: 'عسل سدر', scale_plu_code: '20010', sale_price: '50.0000', is_active: true }],
        variants: [],
        units: [],
    };
    const priceSar = resolveBarcodeScan('2120010025001', sarCatalog, { decimalPlaces: 2 });
    assert.equal(priceSar.matched, true);
    assert.equal(priceSar.embeddedPrice, '25.0000');
    assert.equal(priceSar.quantity, '0.500');

    // Priority 2: Unit barcode (pack of 6)
    const unitRes = resolveBarcodeScan('6281007041126-P6', sampleCatalog);
    assert.equal(unitRes.matched, true);
    assert.equal(unitRes.priority, 2);
    assert.equal(unitRes.matchType, 'unit');
    assert.equal(unitRes.unit.id, 22);
    assert.equal(unitRes.conversionFactor, '6.000');
    assert.equal(unitRes.unitPrice, '1750.0000');

    // Priority 3: Variant barcode (size 42 - black)
    const varRes = resolveBarcodeScan('8690001122042', sampleCatalog);
    assert.equal(varRes.matched, true);
    assert.equal(varRes.priority, 3);
    assert.equal(varRes.matchType, 'variant');
    assert.equal(varRes.variant.id, 12);
    assert.equal(varRes.unitPrice, '16500.0000');

    // Priority 4: Base product barcode for variant product triggers variant selection
    const baseVarRes = resolveBarcodeScan('8690001122000', sampleCatalog);
    assert.equal(baseVarRes.matched, true);
    assert.equal(baseVarRes.priority, 4);
    assert.equal(baseVarRes.matchType, 'product_select_variant');
    assert.equal(baseVarRes.variants.length, 2);

    // Unknown barcode returns matched: false
    const missing = resolveBarcodeScan('9999999999999', sampleCatalog);
    assert.equal(missing.matched, false);
    assert.equal(missing.reason, 'not_found');
});

test('Decimal.js cart math computes 12.5M YER invoice with 0.0000 discrepancy and rejects invalid inputs', () => {
    const cart = calculateCartTotals(
        [
            {
                product_id: 99,
                quantity: '10.000',
                unit_price: '1250000.0000',
                discount_amount: '250000.0000',
                tax_rate: '5.00',
                conversion_factor: '1.000',
                min_allowed_unit_cost: '950000.0000',
            },
        ],
        '150000.0000',
    );

    assert.equal(cart.valid, true);
    assert.equal(cart.subtotal, '12500000.0000');
    assert.equal(cart.itemDiscountTotal, '250000.0000');
    assert.equal(cart.orderDiscountAmount, '150000.0000');
    assert.equal(cart.taxTotal, '612500.0000');
    assert.equal(cart.totalAmount, '12712500.0000');
    assert.equal(cart.requiresCostOverride, false);
    assert.equal(formatMoneyDisplay(cart.totalAmount, 0), '12,712,500');

    const pay = calculatePaymentBalance(
        cart.totalAmount,
        [
            { method: 'cash', amount: '5500000.0000' },
            { method: 'kuraimi', amount: '4712500.0000', reference_number: 'KUR-998811' },
        ],
        '3000000.0000',
    );

    assert.equal(pay.isBalanced, true);
    assert.equal(pay.changeAmount, '500000.0000');
    assert.equal(pay.paidAmount, '9712500.0000');
    assert.equal(pay.creditAmount, '3000000.0000');
    assert.equal(pay.netCashReceived, '5000000.0000');
    assert.equal(pay.discrepancy, '0.0000');

    // Reject negative item discount
    const negItemDisc = calculateLineItem({
        quantity: '1',
        unit_price: '300',
        discount_amount: '-50',
        tax_rate: '0',
    });
    assert.equal(negItemDisc.valid, false);

    // Reject negative order discount
    const negOrderDisc = calculateCartTotals(
        [{ quantity: '1', unit_price: '300', discount_amount: '0', tax_rate: '0' }],
        '-10',
    );
    assert.equal(negOrderDisc.valid, false);

    // Reject unbalanced payment (200 cash + 0 credit != 300 total)
    const unbalanced = calculatePaymentBalance('300.0000', [{ method: 'cash', amount: '200.0000' }], '0');
    assert.equal(unbalanced.isBalanced, false);
    assert.equal(unbalanced.remainingDue, '100.0000');

    // Reject electronic overpayment (cannot give cash change from Kuraimi transfer)
    const badWalletChange = calculatePaymentBalance('300.0000', [{ method: 'kuraimi', amount: '500.0000' }], '0');
    assert.equal(badWalletChange.isBalanced, false);

    // Flag cost-floor override when selling below purchase cost
    const belowCost = calculateCartTotals([
        {
            quantity: '1',
            unit_price: '300',
            discount_amount: '100',
            tax_rate: '0',
            min_allowed_unit_cost: '250',
        },
    ]);
    assert.equal(belowCost.valid, true);
    assert.equal(belowCost.requiresCostOverride, true);
});

test('UUID v7 generator is RFC 9562 valid & monotonically ordered, and invoice number formats INV-{TERM}-{YEAR}-{SEQ}', () => {
    const generated = [];
    for (let i = 0; i < 200; i += 1) {
        const id = generateUuidV7();
        assert.equal(isValidUuidV7(id), true, `Invalid UUID v7: ${id}`);
        generated.push(id);
    }

    assert.equal(new Set(generated).size, 200);
    for (let i = 1; i < generated.length; i += 1) {
        assert.equal(generated[i - 1] < generated[i], true, `UUID v7 not strictly sorted at index ${i}`);
    }

    const inv = formatInvoiceNumber('t01', 42, new Date('2026-11-24T12:00:00Z'));
    assert.equal(inv, 'INV-T01-2026-000042');
    assert.equal(parseInvoiceSequence(inv), 42);
});

test('Web Crypto AES-256-GCM emergency backup encrypts and decrypts outbox/meta cleanly', async () => {
    const payload = {
        terminal_code: 'T01',
        outbox: [{ client_uuid: generateUuidV7(), entity_type: 'orders', payload: { total_amount: '1500.0000' } }],
        meta: [{ key: 'last_invoice_seq', value: 15 }],
    };

    const envelope = await encryptEmergencyBackup(payload, 'YemenSecret#2026');
    assert.equal(envelope.format, 'yemen-pos-emergency-backup-v1');
    assert.equal(envelope.alg, 'AES-256-GCM');

    const decrypted = await decryptEmergencyBackup(envelope, 'YemenSecret#2026');
    assert.deepEqual(decrypted, payload);

    await assert.rejects(() => decryptEmergencyBackup(envelope, 'WrongPassword'));

    const pinHash = await hashLocalPin('1234', 'T01');
    assert.equal(pinHash.length, 64);
});

test('Offline thermal printer builds RTL 80mm/58mm receipts with local Arabic fonts, SVG QR code, Z-report, and XSS escaping', async () => {
    const saleHtml = await buildSaleReceiptHtml({
        type: 'sale',
        store: {
            name: 'بقالة الخير <script>alert(1)</script>',
            phone: '777100200',
            currency_symbol: 'ر.ي',
            decimal_places: 0,
            receipt_settings: { paper_width: '80mm', show_qr: true, footer_note: 'شكراً لكم' },
        },
        cashier: { name: 'أكرم الكاشير' },
        invoice_number: 'INV-T01-2026-000055',
        sold_at: '2026-11-24T12:30:00Z',
        customer_name: 'عبدالرحمن الجرادي',
        subtotal: '1750.0000',
        item_discount_total: '0.0000',
        order_discount_amount: '0.0000',
        tax_total: '0.0000',
        total_amount: '1750.0000',
        paid_amount: '1750.0000',
        credit_amount: '0.0000',
        change_amount: '250.0000',
        items: [
            {
                product_name: 'عصير راني خوخ',
                unit_name_snapshot: 'باكت 6 حبات',
                quantity: '1.000',
                unit_price: '1750.0000',
                discount_amount: '0.0000',
                net_total: '1750.0000',
            },
        ],
        payments: [{ method: 'cash', amount: '2000.0000' }],
    });

    assert.equal(saleHtml.includes('dir="rtl"'), true);
    assert.equal(saleHtml.includes('width: 80mm'), true);
    assert.equal(saleHtml.includes('/fonts/ibm-plex-sans-arabic-400.woff2'), true);
    assert.equal(saleHtml.includes('<svg'), true);
    assert.equal(saleHtml.includes('INV-T01-2026-000055'), true);
    assert.equal(saleHtml.includes('<script>alert(1)</script>'), false);
    assert.equal(saleHtml.includes('&lt;script&gt;alert(1)&lt;/script&gt;'), true);

    const zHtml = buildZReportHtml({
        type: 'shift_close',
        store: { name: 'بقالة الخير', currency_symbol: 'ر.ي', decimal_places: 0, receipt_settings: { paper_width: '58mm' } },
        cashier: { name: 'أكرم الكاشير' },
        shift: {
            terminal_code: 'T01',
            opened_at: '2026-11-24T08:00:00Z',
            closed_at: '2026-11-24T16:00:00Z',
            opening_cash: '50000.0000',
            cash_sales_total: '10000.0000',
            cash_in_total: '0.0000',
            cash_out_total: '2000.0000',
            expected_cash: '58000.0000',
            actual_cash: '58000.0000',
            difference: '0.0000',
        },
        printed_at: '2026-11-24T16:00:00Z',
    });

    assert.equal(zHtml.includes('width: 58mm'), true);
    assert.equal(zHtml.includes('Z-Report'), true);
    assert.equal(zHtml.includes('58,000'), true);

    const voucherHtml = buildDrawerVoucherHtml({
        type: 'drawer_voucher',
        voucher_type: 'employee_advance',
        store: { name: 'بقالة الخير', currency_symbol: 'ر.ي', decimal_places: 0 },
        cashier: { name: 'أكرم' },
        payee_name: 'مبارك',
        amount: '3000.0000',
        payment_source: 'cash_drawer',
        created_at: '2026-11-24T13:00:00Z',
    });
    assert.equal(voucherHtml.includes('سلفة عامل'), true);
    assert.equal(voucherHtml.includes('3,000'), true);
});

test('WhatsApp 1-click JS helper formats Yemeni numbers and encodes Arabic POS invoices cleanly', () => {
    assert.equal(formatYemeniWhatsAppPhone('771223344'), '967771223344');
    assert.equal(formatYemeniWhatsAppPhone('0771223344'), '967771223344');
    assert.equal(formatYemeniWhatsAppPhone('00967 771 223 344'), '967771223344');

    const url = buildPosOrderWhatsAppUrl({
        store: { name: 'بقالة الخير', currency_symbol: 'ر.ي', decimal_places: 0 },
        invoice_number: 'INV-T01-2026-000010',
        customer_name: 'أحمد',
        customer_phone: '0771223344',
        total_amount: '5000.0000',
        paid_amount: '3000.0000',
        credit_amount: '2000.0000',
        items: [{ product_name: 'جبن بلدي', quantity: '1.000', net_total: '5000.0000' }],
    });

    assert.equal(url.startsWith('https://wa.me/967771223344?text='), true);
    const decoded = decodeURIComponent(url.split('?text=')[1]);
    assert.equal(decoded.includes('INV-T01-2026-000010'), true);
    assert.equal(decoded.includes('5,000 ر.ي'), true);
    assert.equal(decoded.includes('2,000 ر.ي'), true);
});

test('POS input formatters strip trailing .0000/.000 across cart, Yemeni payment modal, banknotes, split payments, and shift/drawer inputs while preserving 4-decimal math', () => {
    assert.equal(formatInputMoney('13325.0000'), '13325');
    assert.equal(formatInputMoney('0.0000'), '0');
    assert.equal(formatInputMoney('262.5000', 0), '262.5');
    assert.equal(formatInputQty('20.000'), '20');
    assert.equal(formatInputQty('2.500'), '2.5');
    assert.equal(formatInputQty('0.375'), '0.375');

    const app = createPosApp({
        store: { name: 'بقالة الخير', currency_symbol: 'ر.ي', decimal_places: 0 },
    });
    app.$nextTick = (fn) => fn && fn();
    app.$refs = {};
    app.activeShift = { client_uuid: generateUuidV7(), expected_cash: '50000.0000', status: 'open' };
    app.products = sampleCatalog.products;
    app.unitsByProductId = new Map();
    app.variantsByProductId = new Map();

    // 1. Cart line item discount_amount & quantity start without trailing .0000 / .000
    app.selectProductFromGrid({
        id: 101,
        type: 'simple',
        name: 'أرز بسمتي 10 كجم',
        barcode: '6280000000101',
        sale_price: '13325.0000',
        purchase_price: '11000.0000',
        tax_rate: '0.00',
        is_active: true,
    });

    assert.equal(app.cart.length, 1);
    assert.equal(app.cart[0].quantity, '1');
    assert.equal(app.cart[0].discount_amount, '0');
    assert.equal(app.orderDiscountAmount, '0');
    assert.equal(app.orderDiscount, '0');

    // Internal Decimal.js precision remains 4 decimals / 3 decimals
    assert.equal(app.cartComputation.totalAmount, '13325.0000');
    assert.equal(app.cartComputation.lines[0].quantity, '1.000');
    assert.equal(app.cartComputation.lines[0].discountAmount, '0.0000');

    // 2. Yemeni Payment Modal & Quick Banknotes & Exact Cash & Split Payments
    app.openPaymentModal();
    assert.equal(app.paymentRows[0].amount, '13325');
    assert.equal(app.creditAmountInput, '0');

    app.applyBanknote(1000); // untouchedDefault -> replaces with '1000'
    assert.equal(app.paymentRows[0].amount, '1000');

    app.applyBanknote(5000); // adds -> '6000'
    assert.equal(app.paymentRows[0].amount, '6000');

    app.applyBanknote(10000); // adds -> '16000'
    assert.equal(app.paymentRows[0].amount, '16000');

    app.clearCashPayment();
    assert.equal(app.paymentRows[0].amount, '0');

    app.applyBanknote(20000);
    assert.equal(app.paymentRows[0].amount, '20000');

    // Exact cash ('بالضبط')
    app.setExactCash();
    assert.equal(app.paymentRows[0].amount, '13325');

    // Split payment: set cash to 5000, add Kuraimi -> auto-fills remaining '8325' without '.0000'
    app.paymentRows[0].amount = '5000';
    app.addPaymentRow('kuraimi');
    assert.equal(app.paymentRows.length, 2);
    assert.equal(app.paymentRows[1].method, 'kuraimi');
    assert.equal(app.paymentRows[1].amount, '8325');
    assert.equal(app.paymentSummary.isBalanced, true);
    assert.equal(app.paymentSummary.payments[0].amount, '5000.0000');
    assert.equal(app.paymentSummary.payments[1].amount, '8325.0000');

    // Remaining as credit
    app.paymentRows.pop(); // keep cash 5000
    app.setRemainingAsCredit();
    assert.equal(app.creditAmountInput, '8325');

    // 3. Shift & Drawer modal initializers strip trailing .0000
    app.openShiftModal('25000.0000', '58000.0000');
    assert.equal(app.shiftOpeningCash, '25000');
    assert.equal(app.shiftClosingActualCash, '58000');

    app.openDrawerModal('1500.0000');
    assert.equal(app.drawerForm.amount, '1500');
});


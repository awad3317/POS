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

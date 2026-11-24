import Decimal from 'decimal.js';

/**
 * Pure ES Module for Smart 4-Priority Barcode Resolution.
 * Priorities:
 *  1. Electronic Scale EAN-13 (20 = weight in grams / 1000, 21 = embedded price) via products.scale_plu_code
 *  2. Multi-unit barcode (units.barcode with conversion_factor)
 *  3. Variant barcode (variants.barcode for size/color)
 *  4. Base product barcode (products.barcode)
 */

const ScaleDecimal = Decimal.clone({
    precision: 20,
    rounding: Decimal.ROUND_HALF_UP,
});

function moneyStr(val) {
    return new ScaleDecimal(val ?? 0).toDecimalPlaces(4, ScaleDecimal.ROUND_HALF_UP).toFixed(4);
}

function qtyStr(val) {
    return new ScaleDecimal(val ?? 0).toDecimalPlaces(3, ScaleDecimal.ROUND_HALF_UP).toFixed(3);
}

function normalizePlu(plu) {
    if (plu === null || plu === undefined) {
        return '';
    }
    const trimmed = String(plu).trim();
    return trimmed === '' ? '' : trimmed;
}

/**
 * Resolves a scanned barcode against local catalog arrays using strict 4-tier priority.
 *
 * @param {string} rawBarcode
 * @param {{ products?: Array, variants?: Array, units?: Array }} catalog
 * @param {{ decimalPlaces?: number, priceDivisor?: number }} [options]
 * @returns {Object}
 */
export function resolveBarcodeScan(rawBarcode, catalog = {}, options = {}) {
    const code = String(rawBarcode ?? '').trim();
    if (!code) {
        return { matched: false, reason: 'empty', barcode: '' };
    }

    const activeProducts = (catalog.products || []).filter((p) => p && p.is_active !== false);
    const activeVariants = (catalog.variants || []).filter((v) => v && v.is_active !== false);
    const allUnits = (catalog.units || []).filter(Boolean);

    const productById = new Map();
    for (const p of activeProducts) {
        productById.set(Number(p.id), p);
    }

    // Priority 1: Electronic Scale EAN-13 (Prefix 20 = weight in grams, Prefix 21 = price)
    const scaleMatch = /^(20|21)(\d{5})(\d{5})(\d)$/.exec(code);
    if (scaleMatch) {
        const prefix = scaleMatch[1];
        const plu = scaleMatch[2];
        const valueDigits = scaleMatch[3];
        const valueDec = new ScaleDecimal(valueDigits);

        const scaleProduct = activeProducts.find((p) => {
            const pPlu = normalizePlu(p.scale_plu_code);
            return pPlu !== '' && (pPlu === plu || pPlu.padStart(5, '0') === plu);
        });

        if (scaleProduct && valueDec.gt(0)) {
            const salePrice = new ScaleDecimal(scaleProduct.sale_price ?? 0).toDecimalPlaces(4, ScaleDecimal.ROUND_HALF_UP);

            if (prefix === '20') {
                const quantity = valueDec.div(1000).toDecimalPlaces(3, ScaleDecimal.ROUND_HALF_UP).toFixed(3);
                return {
                    matched: true,
                    matchType: 'scale_weight',
                    priority: 1,
                    product: scaleProduct,
                    variant: null,
                    unit: null,
                    quantity,
                    unitPrice: salePrice.toFixed(4),
                    conversionFactor: '1.000',
                    scalePlu: plu,
                    scaleValue: valueDigits,
                    barcode: code,
                };
            }

            // Prefix 21: Price-embedded scale barcode
            const decimalPlaces = Number(options.decimalPlaces ?? 0);
            const divisor = options.priceDivisor ?? (decimalPlaces >= 2 ? 100 : 1);
            const embeddedPrice = valueDec.div(divisor).toDecimalPlaces(4, ScaleDecimal.ROUND_HALF_UP);

            let quantity = '1.000';
            let unitPrice = embeddedPrice.toFixed(4);

            if (salePrice.gt(0)) {
                let qtyDec = embeddedPrice.div(salePrice).toDecimalPlaces(3, ScaleDecimal.ROUND_HALF_UP);
                if (qtyDec.lte(0)) {
                    qtyDec = new ScaleDecimal('0.001');
                }
                quantity = qtyDec.toFixed(3);
                unitPrice = salePrice.toFixed(4);
            }

            return {
                matched: true,
                matchType: 'scale_price',
                priority: 1,
                product: scaleProduct,
                variant: null,
                unit: null,
                quantity,
                unitPrice,
                embeddedPrice: embeddedPrice.toFixed(4),
                conversionFactor: '1.000',
                scalePlu: plu,
                scaleValue: valueDigits,
                barcode: code,
            };
        }
    }

    // Priority 2: Unit barcode (units.barcode with conversion_factor)
    const matchedUnit = allUnits.find((u) => u.barcode && String(u.barcode).trim() === code);
    if (matchedUnit) {
        const product = productById.get(Number(matchedUnit.product_id));
        if (product) {
            return {
                matched: true,
                matchType: 'unit',
                priority: 2,
                product,
                variant: null,
                unit: matchedUnit,
                quantity: '1.000',
                unitPrice: moneyStr(matchedUnit.sale_price ?? product.sale_price),
                conversionFactor: qtyStr(matchedUnit.conversion_factor ?? '1.000'),
                barcode: code,
            };
        }
    }

    // Priority 3: Variant barcode (variants.barcode for size/color)
    const matchedVariant = activeVariants.find((v) => v.barcode && String(v.barcode).trim() === code);
    if (matchedVariant) {
        const product = productById.get(Number(matchedVariant.product_id));
        if (product) {
            return {
                matched: true,
                matchType: 'variant',
                priority: 3,
                product,
                variant: matchedVariant,
                unit: null,
                quantity: '1.000',
                unitPrice: moneyStr(matchedVariant.sale_price ?? product.sale_price),
                conversionFactor: '1.000',
                barcode: code,
            };
        }
    }

    // Priority 4: Base product barcode (products.barcode)
    const matchedProduct = activeProducts.find((p) => p.barcode && String(p.barcode).trim() === code);
    if (matchedProduct) {
        if (matchedProduct.type === 'variant') {
            const productVariants = activeVariants.filter((v) => Number(v.product_id) === Number(matchedProduct.id));
            if (productVariants.length === 1) {
                const onlyVariant = productVariants[0];
                return {
                    matched: true,
                    matchType: 'variant',
                    priority: 4,
                    product: matchedProduct,
                    variant: onlyVariant,
                    unit: null,
                    quantity: '1.000',
                    unitPrice: moneyStr(onlyVariant.sale_price ?? matchedProduct.sale_price),
                    conversionFactor: '1.000',
                    barcode: code,
                };
            }
            if (productVariants.length > 1) {
                return {
                    matched: true,
                    matchType: 'product_select_variant',
                    priority: 4,
                    product: matchedProduct,
                    variants: productVariants,
                    variant: null,
                    unit: null,
                    quantity: '1.000',
                    unitPrice: moneyStr(matchedProduct.sale_price),
                    conversionFactor: '1.000',
                    barcode: code,
                };
            }
        }

        const defaultUnit = allUnits.find(
            (u) => Number(u.product_id) === Number(matchedProduct.id) && Boolean(u.is_default),
        ) || null;

        return {
            matched: true,
            matchType: 'product',
            priority: 4,
            product: matchedProduct,
            variant: null,
            unit: defaultUnit,
            quantity: '1.000',
            unitPrice: moneyStr(defaultUnit?.sale_price ?? matchedProduct.sale_price),
            conversionFactor: qtyStr(defaultUnit?.conversion_factor ?? '1.000'),
            barcode: code,
        };
    }

    return {
        matched: false,
        reason: 'not_found',
        barcode: code,
    };
}

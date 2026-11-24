import Decimal from 'decimal.js';

/**
 * Pure ES Module for high-precision POS Cart & Payment Accounting using Decimal.js.
 * Mirrors App\Support\MoneyMath & App\Services\OrderService exactly:
 *  - Money scale: 4 decimal places (DECIMAL(19,4)), ROUND_HALF_UP
 *  - Quantity scale: 3 decimal places (DECIMAL(12,3)), ROUND_HALF_UP
 *  - Tax rate scale: 2 decimal places, ROUND_HALF_UP
 *  - Zero native JS floating-point arithmetic on prices, quantities, discounts, taxes, or payments.
 */

export const PosDecimal = Decimal.clone({
    precision: 28,
    rounding: Decimal.ROUND_HALF_UP,
});

function parseDec(value, fallback = '0') {
    if (value === null || value === undefined || value === '') {
        return new PosDecimal(fallback);
    }
    try {
        return new PosDecimal(String(value).trim());
    } catch {
        return null;
    }
}

export function toMoney(value) {
    const d = parseDec(value, '0');
    if (!d) {
        throw new Error(`Invalid money amount: ${value}`);
    }
    return d.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
}

export function toQty(value) {
    const d = parseDec(value, '0');
    if (!d) {
        throw new Error(`Invalid quantity: ${value}`);
    }
    return d.toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP).toFixed(3);
}

export function toRate(value) {
    const d = parseDec(value, '0');
    if (!d) {
        throw new Error(`Invalid rate: ${value}`);
    }
    return d.toDecimalPlaces(2, PosDecimal.ROUND_HALF_UP).toFixed(2);
}

export function addMoney(a, b) {
    return new PosDecimal(toMoney(a)).add(new PosDecimal(toMoney(b))).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
}

export function subMoney(a, b) {
    return new PosDecimal(toMoney(a)).sub(new PosDecimal(toMoney(b))).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
}

export function formatMoneyDisplay(value, decimalPlaces = 0) {
    const dp = Math.max(0, Math.min(4, Number.parseInt(String(decimalPlaces ?? 0), 10) || 0));
    const d = parseDec(value, '0') ?? new PosDecimal('0');
    const fixed = d.toDecimalPlaces(dp, PosDecimal.ROUND_HALF_UP).toFixed(dp);
    const [intPart, fracPart] = fixed.split('.');
    const withCommas = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return fracPart !== undefined ? `${withCommas}.${fracPart}` : withCommas;
}

/**
 * Resolves minimum allowed unit cost matching OrderService::createSale lines 136-140.
 */
export function resolveUnitCostFloor(line) {
    if (line.min_allowed_unit_cost !== undefined && line.min_allowed_unit_cost !== null) {
        return new PosDecimal(toMoney(line.min_allowed_unit_cost));
    }

    if (line.variant_min_cost !== undefined && line.variant_min_cost !== null) {
        return new PosDecimal(toMoney(line.variant_min_cost));
    }

    const unitCostRaw = line.unit_min_cost;
    if (unitCostRaw !== undefined && unitCostRaw !== null) {
        const unitCostDec = new PosDecimal(toMoney(unitCostRaw));
        if (unitCostDec.gt(0)) {
            return unitCostDec;
        }
    }

    const productCost = new PosDecimal(toMoney(line.product_min_cost ?? '0'));
    const factor = new PosDecimal(toQty(line.conversion_factor ?? '1.000'));
    return productCost.mul(factor).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
}

/**
 * Calculates a single cart line item with strict validation.
 */
export function calculateLineItem(line) {
    const errors = [];

    const qtyDec = parseDec(line.quantity ?? '0');
    if (!qtyDec || !qtyDec.isFinite()) {
        errors.push('الكمية غير صالحة.');
    }
    const qtyScaled = qtyDec ? qtyDec.toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (qtyScaled.lte(0)) {
        errors.push('الكمية يجب أن تكون أكبر من صفر.');
    }

    const factorDec = parseDec(line.conversion_factor ?? '1.000');
    const factorScaled = factorDec ? factorDec.toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!factorDec || factorScaled.lte(0)) {
        errors.push('معامل التحويل يجب أن يكون أكبر من صفر.');
    }

    const priceDec = parseDec(line.unit_price ?? '0');
    const priceScaled = priceDec ? priceDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!priceDec || priceScaled.lt(0)) {
        errors.push('سعر الوحدة لا يمكن أن يكون سالباً.');
    }

    const discountDec = parseDec(line.discount_amount ?? '0');
    const discountScaled = discountDec ? discountDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!discountDec) {
        errors.push('قيمة الخصم غير صالحة.');
    } else if (discountScaled.lt(0)) {
        errors.push('ممنوع إدخال خصم سالب على الصنف.');
    }

    const taxRateDec = parseDec(line.tax_rate ?? '0');
    const taxRateScaled = taxRateDec ? taxRateDec.toDecimalPlaces(2, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!taxRateDec || taxRateScaled.lt(0)) {
        errors.push('نسبة الضريبة لا يمكن أن تكون سالبة.');
    }

    const lineGross = priceScaled.mul(qtyScaled).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    if (discountScaled.gt(lineGross)) {
        errors.push('خصم الصنف يتجاوز إجمالي السطر.');
    }

    const lineAfterDiscount = lineGross.sub(discountScaled).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const lineTax = lineAfterDiscount.mul(taxRateScaled).div(100).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const lineNet = lineAfterDiscount.add(lineTax).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const baseQuantity = qtyScaled.mul(factorScaled).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP);

    const costFloorUnit = resolveUnitCostFloor(line);
    const lineCostFloor = costFloorUnit.mul(qtyScaled).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const requiresCostOverride = costFloorUnit.gt(0) && lineAfterDiscount.lt(lineCostFloor);

    return {
        valid: errors.length === 0,
        errors,
        quantity: qtyScaled.toFixed(3),
        conversionFactor: factorScaled.toFixed(3),
        baseQuantity: baseQuantity.toFixed(3),
        unitPrice: priceScaled.toFixed(4),
        lineGross: lineGross.toFixed(4),
        discountAmount: discountScaled.toFixed(4),
        lineAfterDiscount: lineAfterDiscount.toFixed(4),
        taxRate: taxRateScaled.toFixed(2),
        taxAmount: lineTax.toFixed(4),
        lineNet: lineNet.toFixed(4),
        costFloorUnit: costFloorUnit.toFixed(4),
        lineCostFloor: lineCostFloor.toFixed(4),
        requiresCostOverride,
    };
}

/**
 * Calculates full cart totals, item/order discounts, tax, net total, and cost-floor check.
 */
export function calculateCartTotals(items = [], orderDiscountInput = '0') {
    const errors = [];

    if (!Array.isArray(items) || items.length === 0) {
        errors.push('السلة فارغة.');
    }

    const orderDiscDec = parseDec(orderDiscountInput ?? '0');
    const orderDiscScaled = orderDiscDec ? orderDiscDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!orderDiscDec) {
        errors.push('قيمة الخصم الإجمالي غير صالحة.');
    } else if (orderDiscScaled.lt(0)) {
        errors.push('ممنوع إدخال خصم سالب على الفاتورة.');
    }

    let subtotal = new PosDecimal('0');
    let itemDiscountTotal = new PosDecimal('0');
    let taxTotal = new PosDecimal('0');
    let totalCostFloor = new PosDecimal('0');
    let anyLineBelowCost = false;
    const computedLines = [];

    for (let idx = 0; idx < (items || []).length; idx += 1) {
        const calc = calculateLineItem(items[idx]);
        computedLines.push(calc);
        if (!calc.valid) {
            errors.push(...calc.errors);
        }
        subtotal = subtotal.add(new PosDecimal(calc.lineGross));
        itemDiscountTotal = itemDiscountTotal.add(new PosDecimal(calc.discountAmount));
        taxTotal = taxTotal.add(new PosDecimal(calc.taxAmount));
        totalCostFloor = totalCostFloor.add(new PosDecimal(calc.lineCostFloor));
        if (calc.requiresCostOverride) {
            anyLineBelowCost = true;
        }
    }

    const subtotalScaled = subtotal.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const itemDiscountScaled = itemDiscountTotal.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const taxTotalScaled = taxTotal.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const totalCostFloorScaled = totalCostFloor.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);

    const netRevenueBeforeTax = subtotalScaled
        .sub(itemDiscountScaled)
        .sub(orderDiscScaled)
        .toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);

    if (netRevenueBeforeTax.lt(0)) {
        errors.push('الخصم الإجمالي يتجاوز صافي الفاتورة.');
    }

    const totalAmount = netRevenueBeforeTax
        .add(taxTotalScaled)
        .toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);

    const requiresCostOverride = anyLineBelowCost || (totalCostFloorScaled.gt(0) && netRevenueBeforeTax.lt(totalCostFloorScaled));

    return {
        valid: errors.length === 0,
        errors,
        lines: computedLines,
        subtotal: subtotalScaled.toFixed(4),
        itemDiscountTotal: itemDiscountScaled.toFixed(4),
        orderDiscountAmount: orderDiscScaled.toFixed(4),
        netRevenueBeforeTax: netRevenueBeforeTax.toFixed(4),
        taxTotal: taxTotalScaled.toFixed(4),
        totalAmount: totalAmount.toFixed(4),
        totalCostFloor: totalCostFloorScaled.toFixed(4),
        requiresCostOverride,
    };
}

/**
 * Validates mixed/credit/cash payments against invoice total_amount with 4-decimal precision.
 * Guarantees paid_amount + credit_amount == total_amount (discrepancy === '0.0000').
 */
export function calculatePaymentBalance(totalAmountInput, payments = [], creditAmountInput = '0') {
    const errors = [];

    const totalDec = parseDec(totalAmountInput ?? '0');
    const totalScaled = totalDec ? totalDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!totalDec || totalScaled.lt(0)) {
        errors.push('إجمالي الفاتورة غير صالح.');
    }

    const creditDec = parseDec(creditAmountInput ?? '0');
    const creditScaled = creditDec ? creditDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP) : new PosDecimal('0');
    if (!creditDec || creditScaled.lt(0)) {
        errors.push('مبلغ الآجل لا يمكن أن يكون سالباً.');
    }
    if (creditScaled.gt(totalScaled)) {
        errors.push('مبلغ الآجل يتجاوز إجمالي الفاتورة.');
    }

    let tenderedTotal = new PosDecimal('0');
    let cashTendered = new PosDecimal('0');
    const normalizedPayments = [];

    for (const p of payments || []) {
        const amtDec = parseDec(p?.amount ?? '0');
        if (!amtDec) {
            errors.push('قيمة الدفعة غير صالحة.');
            continue;
        }
        const amtScaled = amtDec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
        if (amtScaled.lte(0)) {
            errors.push('مبلغ كل وسيلة دفع يجب أن يكون أكبر من صفر.');
            continue;
        }
        const method = String(p.method || 'cash');
        tenderedTotal = tenderedTotal.add(amtScaled);
        if (method === 'cash') {
            cashTendered = cashTendered.add(amtScaled);
        }
        normalizedPayments.push({
            method,
            amount: amtScaled.toFixed(4),
            reference_number: p.reference_number ? String(p.reference_number).trim() : null,
            notes: p.notes ? String(p.notes).trim() : null,
        });
    }

    const tenderedScaled = tenderedTotal.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const cashTenderedScaled = cashTendered.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);

    const requiredPaid = totalScaled.sub(creditScaled).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const excess = tenderedScaled.sub(requiredPaid).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);

    let changeAmount = new PosDecimal('0.0000');
    let remainingDue = new PosDecimal('0.0000');

    if (excess.gt(0)) {
        if (excess.gt(cashTenderedScaled)) {
            errors.push('المبلغ المدفوع بالوسائل الإلكترونية يتجاوز المطلوب ولا يمكن صرف باقي نقدي منه.');
        } else {
            changeAmount = excess;
        }
    } else if (excess.lt(0)) {
        remainingDue = excess.abs().toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
        errors.push('معادلة الدفع غير متوازنة: المدفوع + الآجل أقل من إجمالي الفاتورة.');
    }

    const paidAmount = tenderedScaled.sub(changeAmount).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const netCashReceived = cashTenderedScaled.sub(changeAmount).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const discrepancy = paidAmount.add(creditScaled).sub(totalScaled).toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP);
    const isBalanced = errors.length === 0 && discrepancy.eq(0);

    return {
        valid: isBalanced,
        isBalanced,
        errors,
        totalAmount: totalScaled.toFixed(4),
        tenderedTotal: tenderedScaled.toFixed(4),
        cashTendered: cashTenderedScaled.toFixed(4),
        netCashReceived: netCashReceived.toFixed(4),
        paidAmount: paidAmount.toFixed(4),
        creditAmount: creditScaled.toFixed(4),
        changeAmount: changeAmount.toFixed(4),
        remainingDue: remainingDue.toFixed(4),
        discrepancy: discrepancy.toFixed(4),
        payments: normalizedPayments,
    };
}

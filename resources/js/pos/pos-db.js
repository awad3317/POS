import Dexie from 'dexie';
import { formatInvoiceNumber } from './pos-uuid.js';
import { PosDecimal, toQty } from './pos-cart-math.js';

/**
 * IndexedDB Database Schema for Yemen POS Offline-First Cashier Engine.
 */
export class YemenPosDB extends Dexie {
    constructor() {
        super('YemenPosDB');
        this.version(1).stores({
            products: 'id, barcode, scale_plu_code, category_id, name, is_quick_pick',
            variants: 'id, product_id, barcode',
            units: 'id, product_id, barcode',
            categories: 'id, sort_order',
            customers: 'id, client_uuid, name, phone',
            quick_payees: '[type+id], id, type, name',
            held_carts: '++id, created_at, customer_name',
            outbox: 'client_uuid, entity_type, status, retry_count, created_at, last_error',
            meta: 'key',
        });
    }
}

export const posDb = new YemenPosDB();

export async function getMeta(key, defaultValue = null) {
    const record = await posDb.meta.get(key);
    return record !== undefined ? record.value : defaultValue;
}

export async function setMeta(key, value) {
    await posDb.meta.put({ key, value });
    return value;
}

export async function setMetas(entries) {
    const rows = Object.entries(entries).map(([key, value]) => ({ key, value }));
    await posDb.meta.bulkPut(rows);
}

export async function deleteMeta(key) {
    await posDb.meta.delete(key);
}

/**
 * Requests persistent browser storage so IndexedDB is protected from eviction.
 * @returns {Promise<boolean>}
 */
export async function requestPersistentStorage() {
    try {
        if (typeof navigator !== 'undefined' && navigator.storage && typeof navigator.storage.persist === 'function') {
            return await navigator.storage.persist();
        }
    } catch {
        return false;
    }
    return false;
}

/**
 * Atomically increments local invoice sequence and generates INV-{TERMINAL}-{YYYY}-{SEQ}.
 */
export async function allocateNextInvoiceNumber() {
    return posDb.transaction('rw', posDb.meta, async () => {
        const terminalCode = await getMeta('terminal_code', 'T01');
        const currentSeq = Number(await getMeta('last_invoice_seq', 0)) || 0;
        const nextSeq = currentSeq + 1;
        await setMeta('last_invoice_seq', nextSeq);

        return {
            seq: nextSeq,
            invoiceNumber: formatInvoiceNumber(terminalCode, nextSeq, new Date()),
        };
    });
}

/**
 * Deducts sold base quantities from IndexedDB products and variants using Decimal.js.
 * @param {Array<{ product_id: number, variant_id?: number|null, base_quantity: string }>} items
 */
export async function deductLocalInventory(items) {
    if (!Array.isArray(items) || items.length === 0) {
        return;
    }

    await posDb.transaction('rw', posDb.products, posDb.variants, async () => {
        for (const item of items) {
            const baseDelta = new PosDecimal(toQty(item.base_quantity ?? item.quantity ?? '0'));
            const product = await posDb.products.get(Number(item.product_id));
            if (product) {
                const currentQty = new PosDecimal(toQty(product.quantity ?? '0'));
                product.quantity = currentQty.sub(baseDelta).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP).toFixed(3);
                await posDb.products.put(product);
            }

            if (item.variant_id) {
                const variant = await posDb.variants.get(Number(item.variant_id));
                if (variant) {
                    const currentVarQty = new PosDecimal(toQty(variant.quantity ?? '0'));
                    variant.quantity = currentVarQty.sub(baseDelta).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP).toFixed(3);
                    await posDb.variants.put(variant);
                }
            }
        }
    });
}

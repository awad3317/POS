import { posDb, getMeta, setMeta } from './pos-db.js';
import { PosDecimal, toQty } from './pos-cart-math.js';
import { encryptEmergencyBackup } from './pos-crypto.js';

export const SYNC_ENTITY_ORDER = [
    'customers',
    'shifts',
    'orders',
    'payments',
    'expenses',
    'employee_advances',
    'supplier_payments',
    'audit_events',
];

const AUTO_SYNC_INTERVAL_MS = 3 * 60 * 1000; // 3 minutes
const MAX_BACKOFF_MS = 5 * 60 * 1000; // 5 minutes

export class PosSyncEngine {
    constructor(options = {}) {
        this.onStateChange = options.onStateChange || (() => {});
        this.onCatalogUpdated = options.onCatalogUpdated || (() => {});
        this.onAuthInvalid = options.onAuthInvalid || (() => {});
        this.syncInFlight = null;
        this.retryTimer = null;
        this.autoInterval = null;
        this.unsyncedCountCache = 0;
        this._boundOnline = null;
        this._boundBeforeUnload = null;
    }

    async refreshOutboxState() {
        const count = await posDb.outbox.count();
        this.unsyncedCountCache = count;
        const lastSyncAt = await getMeta('last_sync_at', null);
        const errors = await posDb.outbox.where('status').equals('error').toArray();
        this.onStateChange({
            outboxCount: count,
            lastSyncAt,
            syncErrors: errors,
        });
        return count;
    }

    start() {
        this.stop();

        this.refreshOutboxState();

        this.autoInterval = window.setInterval(() => {
            if (navigator.onLine) {
                this.syncNow().catch(() => {});
            }
        }, AUTO_SYNC_INTERVAL_MS);

        this._boundOnline = () => {
            this.syncNow().catch(() => {});
        };
        window.addEventListener('online', this._boundOnline);

        this._boundBeforeUnload = (event) => {
            if (this.unsyncedCountCache > 0) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this._boundBeforeUnload);
    }

    stop() {
        if (this.autoInterval) {
            clearInterval(this.autoInterval);
            this.autoInterval = null;
        }
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }
        if (this._boundOnline) {
            window.removeEventListener('online', this._boundOnline);
            this._boundOnline = null;
        }
        if (this._boundBeforeUnload) {
            window.removeEventListener('beforeunload', this._boundBeforeUnload);
            this._boundBeforeUnload = null;
        }
    }

    async syncNow({ forceBootstrap = false, includeErrors = false } = {}) {
        await this.pushOutbox({ includeErrors });
        await this.bootstrapOrPull(forceBootstrap);
    }

    async _authHeaders() {
        const token = await getMeta('terminal_token', null);
        if (!token) {
            throw new Error('لم يتم تفعيل هذا الجهاز بعد.');
        }
        return {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        };
    }

    async _pendingOutboxDeductions() {
        const pendingOrders = await posDb.outbox
            .where('entity_type')
            .equals('orders')
            .toArray();

        const prodDeductions = new Map();
        const varDeductions = new Map();

        for (const entry of pendingOrders) {
            const items = entry.payload?.items || [];
            for (const item of items) {
                const qty = new PosDecimal(toQty(item.quantity ?? '0'));
                const factor = new PosDecimal(toQty(item.conversion_factor ?? '1.000'));
                const base = qty.mul(factor).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP);

                const pId = Number(item.product_id);
                prodDeductions.set(pId, (prodDeductions.get(pId) || new PosDecimal('0')).add(base));

                if (item.variant_id) {
                    const vId = Number(item.variant_id);
                    varDeductions.set(vId, (varDeductions.get(vId) || new PosDecimal('0')).add(base));
                }
            }
        }

        return { prodDeductions, varDeductions };
    }

    async bootstrapOrPull(forceBootstrap = false) {
        const headers = await this._authHeaders();
        const lastSyncAt = await getMeta('last_sync_at', null);
        const isBootstrap = Boolean(forceBootstrap || !lastSyncAt);

        const url = isBootstrap
            ? '/api/v1/sync/bootstrap'
            : `/api/v1/sync/pull?since=${encodeURIComponent(lastSyncAt)}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': headers.Authorization,
                'Accept': 'application/json',
            },
        });

        if (response.status === 401 || response.status === 403) {
            this.onAuthInvalid(response.status);
            throw new Error('انتهت صلاحية توكن الجهاز أو تم إيقافه من الإدارة.');
        }

        if (!response.ok) {
            throw new Error(`فشل جلب بيانات المتجر (HTTP ${response.status}).`);
        }

        const data = await response.json();
        const { prodDeductions, varDeductions } = await this._pendingOutboxDeductions();

        const adjustedProducts = (data.products || []).map((p) => {
            const pending = prodDeductions.get(Number(p.id));
            if (!pending) {
                return p;
            }
            const qty = new PosDecimal(toQty(p.quantity ?? '0')).sub(pending).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP).toFixed(3);
            return { ...p, quantity: qty };
        });

        const adjustedVariants = (data.variants || []).map((v) => {
            const pending = varDeductions.get(Number(v.id));
            if (!pending) {
                return v;
            }
            const qty = new PosDecimal(toQty(v.quantity ?? '0')).sub(pending).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP).toFixed(3);
            return { ...v, quantity: qty };
        });

        await posDb.transaction(
            'rw',
            posDb.products,
            posDb.variants,
            posDb.units,
            posDb.categories,
            posDb.customers,
            posDb.quick_payees,
            posDb.meta,
            async () => {
                if (data.store) {
                    await setMeta('store_settings', data.store);
                }

                if (isBootstrap) {
                    await posDb.categories.clear();
                    if ((data.categories || []).length > 0) {
                        await posDb.categories.bulkPut(data.categories);
                    }

                    await posDb.products.clear();
                    if (adjustedProducts.length > 0) {
                        await posDb.products.bulkPut(adjustedProducts);
                    }

                    await posDb.variants.clear();
                    if (adjustedVariants.length > 0) {
                        await posDb.variants.bulkPut(adjustedVariants);
                    }

                    await posDb.units.clear();
                    if ((data.units || []).length > 0) {
                        await posDb.units.bulkPut(data.units);
                    }
                } else {
                    if ((data.deleted_product_ids || []).length > 0) {
                        await posDb.products.bulkDelete(data.deleted_product_ids.map(Number));
                    }
                    if ((data.deleted_variant_ids || []).length > 0) {
                        await posDb.variants.bulkDelete(data.deleted_variant_ids.map(Number));
                    }
                    if ((data.deleted_customer_ids || []).length > 0) {
                        await posDb.customers.bulkDelete(data.deleted_customer_ids.map(Number));
                    }

                    if (adjustedProducts.length > 0) {
                        await posDb.products.bulkPut(adjustedProducts);
                    }
                    if (adjustedVariants.length > 0) {
                        await posDb.variants.bulkPut(adjustedVariants);
                    }
                    if ((data.units || []).length > 0) {
                        await posDb.units.bulkPut(data.units);
                    }
                }

                // Reconcile customers preserving unsynced offline-created customers (id < 0)
                const serverCustomers = data.customers || [];
                const serverUuids = new Set(serverCustomers.map((c) => c.client_uuid).filter(Boolean));
                const existingOffline = await posDb.customers.where('id').below(0).toArray();

                for (const offCust of existingOffline) {
                    if (offCust.client_uuid && serverUuids.has(offCust.client_uuid)) {
                        await posDb.customers.delete(offCust.id);
                    }
                }

                if (isBootstrap) {
                    const remainingOffline = await posDb.customers.where('id').below(0).toArray();
                    await posDb.customers.clear();
                    if (serverCustomers.length > 0) {
                        await posDb.customers.bulkPut(serverCustomers);
                    }
                    if (remainingOffline.length > 0) {
                        await posDb.customers.bulkPut(remainingOffline);
                    }
                } else if (serverCustomers.length > 0) {
                    await posDb.customers.bulkPut(serverCustomers);
                }

                if (Array.isArray(data.quick_payees)) {
                    await posDb.quick_payees.clear();
                    if (data.quick_payees.length > 0) {
                        await posDb.quick_payees.bulkPut(data.quick_payees);
                    }
                }

                if (data.server_time) {
                    await setMeta('last_sync_at', data.server_time);
                }
            },
        );

        await this.refreshOutboxState();
        await this.onCatalogUpdated();

        return data;
    }

    async pushOutbox({ includeErrors = false } = {}) {
        if (this.syncInFlight) {
            return this.syncInFlight;
        }

        this.syncInFlight = this._runPushOutbox({ includeErrors }).finally(() => {
            this.syncInFlight = null;
        });

        return this.syncInFlight;
    }

    async _runPushOutbox({ includeErrors }) {
        const headers = await this._authHeaders();
        const allItems = await posDb.outbox.orderBy('created_at').toArray();

        const itemsToPush = allItems.filter((item) => {
            if (item.status === 'error') {
                return Boolean(includeErrors);
            }
            return item.status === 'pending' || item.status === 'retry' || !item.status;
        });

        if (itemsToPush.length === 0) {
            await this.refreshOutboxState();
            return { pushedCount: 0, syncedCount: 0, errorCount: 0 };
        }

        const batchBody = {};
        const itemsByType = {};
        for (const entityType of SYNC_ENTITY_ORDER) {
            batchBody[entityType] = [];
            itemsByType[entityType] = [];
        }

        for (const entry of itemsToPush) {
            if (batchBody[entry.entity_type]) {
                batchBody[entry.entity_type].push(entry.payload);
                itemsByType[entry.entity_type].push(entry);
            }
        }

        let response;
        try {
            response = await fetch('/api/v1/sync/push', {
                method: 'POST',
                headers,
                body: JSON.stringify(batchBody),
            });
        } catch (networkErr) {
            await this._scheduleRetry(itemsToPush, networkErr?.message || 'فشل الاتصال بالسيرفر');
            throw networkErr;
        }

        if (response.status === 401 || response.status === 403) {
            this.onAuthInvalid(response.status);
            throw new Error('تم إبطال جلسة الجهاز أو رفض الصلاحية.');
        }

        if (response.status >= 500 || response.status === 429) {
            const msg = `خطأ مؤقت في السيرفر (HTTP ${response.status})`;
            await this._scheduleRetry(itemsToPush, msg);
            throw new Error(msg);
        }

        if (!response.ok) {
            const msg = `فشل إرسال الدفعة (HTTP ${response.status})`;
            await this._scheduleRetry(itemsToPush, msg);
            throw new Error(msg);
        }

        const data = await response.json();
        const results = data.results || {};
        let syncedCount = 0;
        let errorCount = 0;

        await posDb.transaction(
            'rw',
            posDb.outbox,
            posDb.customers,
            posDb.meta,
            async () => {
                for (const entityType of SYNC_ENTITY_ORDER) {
                    const typeResults = results[entityType] || {};
                    for (const entry of itemsByType[entityType]) {
                        const res = typeResults[entry.client_uuid];
                        if (!res) {
                            continue;
                        }

                        if (res.status === 'synced' || res.status === 'duplicate') {
                            syncedCount += 1;
                            await posDb.outbox.delete(entry.client_uuid);

                            if (entityType === 'customers' && res.server_id) {
                                const offlineMatches = await posDb.customers
                                    .where('client_uuid')
                                    .equals(entry.client_uuid)
                                    .toArray();
                                const baseInfo = offlineMatches[0] || entry.payload;
                                for (const m of offlineMatches) {
                                    if (Number(m.id) < 0) {
                                        await posDb.customers.delete(m.id);
                                    }
                                }
                                await posDb.customers.put({
                                    ...baseInfo,
                                    id: Number(res.server_id),
                                    client_uuid: entry.client_uuid,
                                    current_balance: baseInfo.current_balance ?? '0.0000',
                                });
                            }

                            if (entityType === 'shifts' && res.server_id) {
                                const activeShift = await getMeta('active_shift', null);
                                if (activeShift && activeShift.client_uuid === entry.client_uuid) {
                                    activeShift.server_id = Number(res.server_id);
                                    await setMeta('active_shift', activeShift);
                                }
                            }
                        } else if (res.status === 'error') {
                            errorCount += 1;
                            await posDb.outbox.put({
                                ...entry,
                                status: 'error',
                                retry_count: (entry.retry_count || 0) + 1,
                                last_error: res.message || 'خطأ غير معروف أثناء الحفظ على السيرفر',
                            });
                        }
                    }
                }
            },
        );

        // Apply server stock_balances minus any remaining pending local outbox deductions
        await this._applyServerStockBalances(data.stock_balances || {});
        await this.refreshOutboxState();
        await this.onCatalogUpdated();

        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
            this.retryTimer = null;
        }

        return {
            pushedCount: itemsToPush.length,
            syncedCount,
            errorCount,
            results,
        };
    }

    async _applyServerStockBalances(stockBalances) {
        const productBalances = stockBalances.products || {};
        const variantBalances = stockBalances.variants || {};
        const { prodDeductions, varDeductions } = await this._pendingOutboxDeductions();

        await posDb.transaction('rw', posDb.products, posDb.variants, async () => {
            for (const [idStr, serverQty] of Object.entries(productBalances)) {
                const id = Number(idStr);
                const prod = await posDb.products.get(id);
                if (prod) {
                    const pending = prodDeductions.get(id) || new PosDecimal('0');
                    prod.quantity = new PosDecimal(toQty(serverQty))
                        .sub(pending)
                        .toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP)
                        .toFixed(3);
                    await posDb.products.put(prod);
                }
            }

            for (const [idStr, serverQty] of Object.entries(variantBalances)) {
                const id = Number(idStr);
                const variant = await posDb.variants.get(id);
                if (variant) {
                    const pending = varDeductions.get(id) || new PosDecimal('0');
                    variant.quantity = new PosDecimal(toQty(serverQty))
                        .sub(pending)
                        .toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP)
                        .toFixed(3);
                    await posDb.variants.put(variant);
                }
            }
        });
    }

    async _scheduleRetry(items, errorMessage) {
        let maxRetry = 0;
        await posDb.transaction('rw', posDb.outbox, async () => {
            for (const item of items) {
                const nextRetry = (item.retry_count || 0) + 1;
                if (nextRetry > maxRetry) {
                    maxRetry = nextRetry;
                }
                await posDb.outbox.put({
                    ...item,
                    status: 'retry',
                    retry_count: nextRetry,
                    last_error: errorMessage,
                });
            }
        });

        await this.refreshOutboxState();

        const delayMs = Math.min(MAX_BACKOFF_MS, 2000 * (2 ** Math.max(0, maxRetry - 1)));
        if (this.retryTimer) {
            clearTimeout(this.retryTimer);
        }
        this.retryTimer = window.setTimeout(() => {
            if (navigator.onLine) {
                this.pushOutbox().catch(() => {});
            }
        }, delayMs);
    }

    async exportEncryptedBackup(passphraseInput = '') {
        const terminalCode = await getMeta('terminal_code', 'T01');
        const effectivePass = String(passphraseInput || '').trim() || `${terminalCode}-YEMENPOS`;

        const [outbox, metaRows, heldCarts, offlineCustomers] = await Promise.all([
            posDb.outbox.toArray(),
            posDb.meta.toArray(),
            posDb.held_carts.toArray(),
            posDb.customers.where('id').below(0).toArray(),
        ]);

        const sanitizedMeta = metaRows.filter((row) => row.key !== 'terminal_token');

        const snapshot = {
            terminal_code: terminalCode,
            exported_at: new Date().toISOString(),
            outbox,
            meta: sanitizedMeta,
            held_carts: heldCarts,
            offline_customers: offlineCustomers,
        };

        const encryptedEnvelope = await encryptEmergencyBackup(snapshot, effectivePass);
        const jsonString = JSON.stringify(encryptedEnvelope, null, 2);
        const safeTimestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const filename = `yemen-pos-emergency-backup-${terminalCode}-${safeTimestamp}.json`;

        return {
            filename,
            jsonString,
            outboxCount: outbox.length,
        };
    }
}

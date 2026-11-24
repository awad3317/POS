import {
    posDb,
    getMeta,
    setMeta,
    setMetas,
    deleteMeta,
    requestPersistentStorage,
    allocateNextInvoiceNumber,
    deductLocalInventory,
} from './pos-db.js';
import { generateUuidV7 } from './pos-uuid.js';
import { resolveBarcodeScan } from './pos-barcode.js';
import {
    PosDecimal,
    toMoney,
    toQty,
    addMoney,
    subMoney,
    formatMoneyDisplay,
    calculateCartTotals,
    calculatePaymentBalance,
} from './pos-cart-math.js';
import { PosSyncEngine } from './pos-sync.js';

const PAYMENT_METHOD_LABELS = {
    cash: 'نقدي',
    kuraimi: 'الكريمي',
    jawali: 'جوالي',
    floosak: 'فلوسك',
    onecash: 'ون كاش',
    card: 'بطاقة بنكية',
    bank_transfer: 'تحويل بنكي',
};

const EXPENSE_CATEGORIES = [
    'أكياس وتغليف',
    'ضيافة ومياه',
    'مواصلات وتوصيل',
    'كهرباء ومولد',
    'صيانة سريعة',
    'نثريات عامة',
];

async function hashPinSafe(pin, salt = 'yemen-pos-pin') {
    const input = `${salt}:${String(pin ?? '').trim()}`;
    try {
        if (globalThis.crypto?.subtle) {
            const buf = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(input));
            return Array.from(new Uint8Array(buf), (b) => b.toString(16).padStart(2, '0')).join('');
        }
    } catch {
        // Fallback when Web Crypto subtle is unavailable on plain HTTP LAN origin
    }
    let h1 = 0x811c9dc5;
    let h2 = 0x9e3779b9;
    for (let i = 0; i < input.length; i += 1) {
        const ch = input.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 0x01000193);
        h2 = Math.imul(h2 ^ (ch + i), 0x85ebca6b);
    }
    return `fb-${(h1 >>> 0).toString(16).padStart(8, '0')}${(h2 >>> 0).toString(16).padStart(8, '0')}`;
}

export function createPosApp(bootConfig = {}) {
    return {
        bootConfig,
        initialized: false,
        isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
        terminalToken: null,
        terminalCode: '',
        terminalInfo: null,
        userInfo: bootConfig.user || null,
        storeSettings: bootConfig.store || { currency_symbol: 'ر.ي', decimal_places: 0, feature_flags: {} },
        activeShift: null,

        // Lock screen state
        hasCashierPin: false,
        isLocked: false,
        lockPinInput: '',
        lockSetupPinInput: '',
        lockError: '',
        unlockPasswordMode: false,
        unlockPasswordInput: '',

        // Sync state
        outboxCount: 0,
        lastSyncAt: null,
        syncErrors: [],
        isSyncing: false,
        authInvalidWarning: false,
        syncEngine: null,

        // Terminal activation form
        activationForm: {
            login: bootConfig.user?.email || bootConfig.user?.phone || '',
            password: '',
            terminal_code: bootConfig.terminals?.[0]?.code || 'T01',
            quick_pin: '',
        },
        activationLoading: false,
        activationError: '',

        // Local catalog cache (in-memory for < 2ms search)
        products: [],
        variants: [],
        units: [],
        categories: [],
        customers: [],
        quickPayees: [],
        heldCarts: [],
        productsById: new Map(),
        variantsByProductId: new Map(),
        unitsByProductId: new Map(),

        // Sales UI state
        selectedCategoryId: null,
        barcodeInput: '',
        searchQuery: '',
        cart: [],
        selectedLineIndex: 0,
        orderDiscountAmount: '0',
        orderNotes: '',
        selectedCustomer: null,
        managerOverridePin: '',

        // Modals
        modal: null,
        variantPickerData: { product: null, variants: [] },
        pendingOverrideCallback: null,
        overridePinInput: '',
        overridePinError: '',

        // Shift modal inputs
        shiftOpeningCash: '0',
        shiftClosingActualCash: '',
        shiftNotes: '',

        // Payment modal state
        paymentRows: [{ method: 'cash', amount: '0', reference_number: '', untouchedDefault: true }],
        creditAmountInput: '0',
        paymentNotes: '',
        customerSearchQuery: '',
        showNewCustomerForm: false,
        newCustomerForm: { name: '', phone: '', credit_limit: '0', address: '' },
        paymentOverridePin: '',

        // Quick Drawer modal state
        drawerTab: 'expense',
        drawerCategories: EXPENSE_CATEGORIES,
        drawerForm: {
            category: 'أكياس وتغليف',
            amount: '',
            payee_id: '',
            method: 'cash',
            reference_number: '',
            customer_id: '',
            notes: '',
        },

        // Emergency backup modal
        backupPassphrase: '',
        backupStatusMsg: '',
        pendingOutboxItems: [],

        // Toast notifications
        toast: { show: false, type: 'success', message: '' },
        _toastTimer: null,

        paymentMethodLabels: PAYMENT_METHOD_LABELS,

        async init() {
            window.addEventListener('online', () => {
                this.isOnline = true;
            });
            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            this.syncEngine = new PosSyncEngine({
                onStateChange: ({ outboxCount, lastSyncAt, syncErrors }) => {
                    this.outboxCount = outboxCount;
                    this.lastSyncAt = lastSyncAt;
                    this.syncErrors = syncErrors || [];
                },
                onCatalogUpdated: async () => {
                    await this.refreshLocalStateFromDb();
                },
                onAuthInvalid: () => {
                    this.authInvalidWarning = true;
                    this.showToast('error', 'تم إبطال توكن الجهاز أو رفض الصلاحية. يرجى إعادة التفعيل.');
                },
            });

            await this.restoreTerminalSession();
            this.initialized = true;

            if (this.terminalToken) {
                this.syncEngine.start();
                if (this.isOnline) {
                    this.syncNow(false).catch(() => {});
                }
                this.$nextTick(() => this.focusBarcodeInput());
            }
        },

        async restoreTerminalSession() {
            const [token, code, termInfo, userInfo, storeSettings, activeShift, pinHash] = await Promise.all([
                getMeta('terminal_token', null),
                getMeta('terminal_code', ''),
                getMeta('terminal_info', null),
                getMeta('user_info', null),
                getMeta('store_settings', null),
                getMeta('active_shift', null),
                getMeta('cashier_pin_hash', null),
            ]);

            this.terminalToken = token;
            this.terminalCode = code || '';
            this.terminalInfo = termInfo;
            if (userInfo) {
                this.userInfo = userInfo;
            }
            if (storeSettings) {
                this.storeSettings = storeSettings;
            }
            this.activeShift = activeShift;
            this.hasCashierPin = Boolean(pinHash);

            await this.refreshLocalStateFromDb();
            await this.syncEngine.refreshOutboxState();
        },

        async refreshLocalStateFromDb() {
            const [products, variants, units, categories, customers, quickPayees, heldCarts, activeShift] = await Promise.all([
                posDb.products.toArray(),
                posDb.variants.toArray(),
                posDb.units.toArray(),
                posDb.categories.orderBy('sort_order').toArray(),
                posDb.customers.toArray(),
                posDb.quick_payees.toArray(),
                posDb.held_carts.orderBy('created_at').reverse().toArray(),
                getMeta('active_shift', null),
            ]);

            const prodMap = new Map();
            for (const p of products) {
                prodMap.set(Number(p.id), p);
            }

            const varMap = new Map();
            for (const v of variants) {
                const pId = Number(v.product_id);
                if (!varMap.has(pId)) {
                    varMap.set(pId, []);
                }
                varMap.get(pId).push(v);
            }

            const unitMap = new Map();
            for (const u of units) {
                const pId = Number(u.product_id);
                if (!unitMap.has(pId)) {
                    unitMap.set(pId, []);
                }
                unitMap.get(pId).push(u);
            }

            this.products = products;
            this.variants = variants;
            this.units = units;
            this.categories = categories;
            this.customers = customers;
            this.quickPayees = quickPayees;
            this.heldCarts = heldCarts;
            this.activeShift = activeShift;
            this.productsById = prodMap;
            this.variantsByProductId = varMap;
            this.unitsByProductId = unitMap;

            if (this.selectedCustomer) {
                const refreshedCust = customers.find(
                    (c) => (this.selectedCustomer.client_uuid && c.client_uuid === this.selectedCustomer.client_uuid)
                        || Number(c.id) === Number(this.selectedCustomer.id),
                );
                if (refreshedCust) {
                    this.selectedCustomer = refreshedCust;
                }
            }
        },

        async activateTerminal() {
            this.activationError = '';
            const login = String(this.activationForm.login || '').trim();
            const password = String(this.activationForm.password || '');
            const terminalCode = String(this.activationForm.terminal_code || '').trim().toUpperCase();
            const quickPin = String(this.activationForm.quick_pin || '').trim();

            if (!login || !password || !terminalCode) {
                this.activationError = 'يرجى إدخال بيانات الحساب ورمز جهاز الكاشير.';
                return;
            }
            if (quickPin && !/^\d{4}$/.test(quickPin)) {
                this.activationError = 'رمز القفل السريع PIN يجب أن يكون 4 أرقام.';
                return;
            }

            this.activationLoading = true;
            try {
                const res = await fetch('/api/v1/auth/terminal-login', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        login,
                        password,
                        terminal_code: terminalCode,
                    }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.activationError = data.message || 'فشل تفعيل الجهاز. تأكد من كلمة المرور ورمز الجهاز.';
                    return;
                }

                const existingSeq = Number(await getMeta('last_invoice_seq', 0)) || 0;
                const serverSeq = Number(data.terminal?.last_invoice_seq || 0) || 0;

                const metaEntries = {
                    terminal_token: data.token,
                    terminal_code: data.terminal.code,
                    terminal_info: data.terminal,
                    user_info: data.user,
                    store_settings: data.store,
                    last_invoice_seq: Math.max(existingSeq, serverSeq),
                };

                if (quickPin) {
                    metaEntries.cashier_pin_hash = await hashPinSafe(quickPin, data.terminal.code);
                }

                await setMetas(metaEntries);
                await requestPersistentStorage();

                // Adopt server open shift for this terminal if local active_shift is absent
                const localShift = await getMeta('active_shift', null);
                if (!localShift && Array.isArray(this.bootConfig.open_shifts)) {
                    const serverShift = this.bootConfig.open_shifts.find(
                        (s) => Number(s.terminal_id) === Number(data.terminal.id),
                    );
                    if (serverShift) {
                        await setMeta('active_shift', {
                            ...serverShift,
                            server_id: Number(serverShift.id),
                            terminal_code: data.terminal.code,
                        });
                    }
                }

                this.authInvalidWarning = false;
                await this.restoreTerminalSession();
                this.syncEngine.start();
                await this.syncEngine.bootstrapOrPull(true);
                this.activationForm.password = '';
                this.showToast('success', `تم تفعيل الجهاز (${data.terminal.code}) وسحب بيانات المتجر بنجاح.`);
                this.$nextTick(() => this.focusBarcodeInput());
            } catch (err) {
                this.activationError = err?.message || 'حدث خطأ أثناء الاتصال بالسيرفر.';
            } finally {
                this.activationLoading = false;
            }
        },

        async deactivateTerminalPrompt() {
            if (this.outboxCount > 0) {
                this.showToast('error', 'لا يمكن فصل الجهاز مع وجود عمليات غير مزامنة. قم بالمزامنة أو تصدير نسخة طوارئ أولاً.');
                return;
            }
            await deleteMeta('terminal_token');
            this.syncEngine?.stop();
            this.terminalToken = null;
            this.authInvalidWarning = false;
        },

        async syncNow(manual = true) {
            if (!this.terminalToken) {
                return;
            }
            if (!this.isOnline) {
                if (manual) {
                    this.showToast('warning', 'الجهاز غير متصل بالإنترنت حالياً. العمليات محفوظة بأمان.');
                }
                return;
            }
            this.isSyncing = true;
            try {
                await this.syncEngine.syncNow({
                    forceBootstrap: false,
                    includeErrors: Boolean(manual),
                });
                if (manual) {
                    this.showToast('success', 'تمت المزامنة مع السيرفر بنجاح.');
                }
            } catch (err) {
                if (manual) {
                    this.showToast('error', err?.message || 'تعذرت المزامنة حالياً.');
                }
            } finally {
                this.isSyncing = false;
            }
        },

        // Permissions & display helpers
        canOverrideDiscounts() {
            const perms = this.userInfo?.permissions || [];
            const roles = this.userInfo?.roles || [];
            return perms.includes('pos.override_discounts') || roles.includes('owner') || roles.includes('manager');
        },

        currencySymbol() {
            return this.storeSettings?.currency_symbol || 'ر.ي';
        },

        decimalPlaces() {
            return Number(this.storeSettings?.decimal_places ?? 0);
        },

        fmtMoney(val) {
            return formatMoneyDisplay(val, this.decimalPlaces());
        },

        fmtQty(val) {
            try {
                const d = new PosDecimal(toQty(val ?? '0'));
                const s = d.toFixed(3);
                return s.replace(/\.000$/, '').replace(/(\.\d*[1-9])0+$/, '$1');
            } catch {
                return '0';
            }
        },

        // Instant in-memory search (< 2ms, max 20 results) — pure getter with zero reactive mutations
        get searchResults() {
            const q = String(this.searchQuery || '').trim().toLowerCase();
            if (!q && !this.selectedCategoryId) {
                return [];
            }

            const matches = [];
            for (const p of this.products) {
                if (p.is_active === false) {
                    continue;
                }
                if (this.selectedCategoryId && Number(p.category_id) !== Number(this.selectedCategoryId)) {
                    continue;
                }
                if (!q) {
                    matches.push(p);
                } else {
                    const nameMatch = (p.name || '').toLowerCase().includes(q);
                    const barcodeMatch = (p.barcode || '').toLowerCase().includes(q);
                    const skuMatch = (p.sku || '').toLowerCase().includes(q);
                    const pluMatch = (p.scale_plu_code || '').toLowerCase().includes(q);
                    let childBarcodeMatch = false;

                    if (!nameMatch && !barcodeMatch && !skuMatch && !pluMatch) {
                        const pVars = this.variantsByProductId.get(Number(p.id)) || [];
                        const pUnits = this.unitsByProductId.get(Number(p.id)) || [];
                        childBarcodeMatch = pVars.some((v) => (v.barcode || '').toLowerCase().includes(q))
                            || pUnits.some((u) => (u.barcode || '').toLowerCase().includes(q));
                    }

                    if (nameMatch || barcodeMatch || skuMatch || pluMatch || childBarcodeMatch) {
                        matches.push(p);
                    }
                }
                if (matches.length >= 20) {
                    break;
                }
            }
            return matches;
        },

        get quickPickProducts() {
            return this.products.filter((p) => {
                if (p.is_active === false || !p.is_quick_pick) {
                    return false;
                }
                if (this.selectedCategoryId && Number(p.category_id) !== Number(this.selectedCategoryId)) {
                    return false;
                }
                return true;
            });
        },

        get filteredPaymentCustomers() {
            const q = String(this.customerSearchQuery || '').trim().toLowerCase();
            const list = !q
                ? this.customers
                : this.customers.filter(
                    (c) => (c.name || '').toLowerCase().includes(q) || (c.phone || '').toLowerCase().includes(q),
                );
            return list.slice(0, 20);
        },

        get employeeQuickPayees() {
            return this.quickPayees.filter((p) => p.type === 'employee');
        },

        get supplierQuickPayees() {
            return this.quickPayees.filter((p) => p.type === 'supplier');
        },

        get cartComputation() {
            if (this.cart.length === 0) {
                return {
                    valid: false,
                    errors: [],
                    lines: [],
                    subtotal: '0.0000',
                    itemDiscountTotal: '0.0000',
                    orderDiscountAmount: '0.0000',
                    netRevenueBeforeTax: '0.0000',
                    taxTotal: '0.0000',
                    totalAmount: '0.0000',
                    totalCostFloor: '0.0000',
                    requiresCostOverride: false,
                };
            }
            return calculateCartTotals(this.cart, this.orderDiscountAmount || '0');
        },

        get paymentSummary() {
            const activeRows = (this.paymentRows || []).filter((r) => {
                try {
                    return new PosDecimal(String(r.amount || '0')).gt(0);
                } catch {
                    return false;
                }
            });
            return calculatePaymentBalance(
                this.cartComputation.totalAmount,
                activeRows,
                this.creditAmountInput || '0',
            );
        },

        focusBarcodeInput() {
            if (this.isLocked || !this.terminalToken || this.modal) {
                return;
            }
            this.$refs.barcodeInput?.focus();
        },

        handleBarcodeSubmit() {
            const code = String(this.barcodeInput || '').trim();
            this.barcodeInput = '';
            if (!code) {
                return;
            }

            const result = resolveBarcodeScan(
                code,
                {
                    products: this.products,
                    variants: this.variants,
                    units: this.units,
                },
                {
                    decimalPlaces: this.decimalPlaces(),
                },
            );

            if (!result.matched) {
                this.showToast('error', `لم يتم العثور على باركود: ${code}`);
                this.$nextTick(() => this.focusBarcodeInput());
                return;
            }

            if (result.matchType === 'product_select_variant') {
                this.variantPickerData = { product: result.product, variants: result.variants };
                this.modal = 'variant_picker';
                return;
            }

            this.addResolvedLineToCart(result);
        },

        selectProductFromGrid(product) {
            if (!product) {
                return;
            }
            if (product.type === 'variant') {
                const pVars = (this.variantsByProductId.get(Number(product.id)) || []).filter((v) => v.is_active !== false);
                if (pVars.length === 1) {
                    this.addResolvedLineToCart({
                        matchType: 'variant',
                        product,
                        variant: pVars[0],
                        unit: null,
                        quantity: '1.000',
                        unitPrice: toMoney(pVars[0].sale_price ?? product.sale_price),
                        conversionFactor: '1.000',
                        barcode: pVars[0].barcode || product.barcode,
                    });
                    return;
                }
                if (pVars.length > 1) {
                    this.variantPickerData = { product, variants: pVars };
                    this.modal = 'variant_picker';
                    return;
                }
            }

            const pUnits = this.unitsByProductId.get(Number(product.id)) || [];
            const defaultUnit = pUnits.find((u) => Boolean(u.is_default)) || null;

            this.addResolvedLineToCart({
                matchType: 'product',
                product,
                variant: null,
                unit: defaultUnit,
                quantity: '1.000',
                unitPrice: toMoney(defaultUnit?.sale_price ?? product.sale_price),
                conversionFactor: toQty(defaultUnit?.conversion_factor ?? '1.000'),
                barcode: defaultUnit?.barcode || product.barcode,
            });
        },

        pickVariantForCart(variant) {
            const product = this.variantPickerData.product;
            if (!product || !variant) {
                return;
            }
            this.addResolvedLineToCart({
                matchType: 'variant',
                product,
                variant,
                unit: null,
                quantity: '1.000',
                unitPrice: toMoney(variant.sale_price ?? product.sale_price),
                conversionFactor: '1.000',
                barcode: variant.barcode || product.barcode,
            });
            this.closeModal();
        },

        addResolvedLineToCart(res) {
            const product = res.product;
            const variant = res.variant || null;
            const unit = res.unit || null;
            const productId = Number(product.id);
            const variantId = variant ? Number(variant.id) : null;
            const unitId = unit ? Number(unit.id) : null;
            const isScale = res.matchType === 'scale_weight' || res.matchType === 'scale_price';
            const availableUnits = this.unitsByProductId.get(productId) || [];

            const displayName = variant
                ? `${product.name} (${variant.attribute_label || [variant.size, variant.color].filter(Boolean).join(' - ')})`
                : product.name;

            const existingIdx = isScale
                ? -1
                : this.cart.findIndex(
                    (item) => Number(item.product_id) === productId
                        && Number(item.variant_id || 0) === Number(variantId || 0)
                        && Number(item.unit_id || 0) === Number(unitId || 0),
                );

            if (existingIdx >= 0) {
                const existing = this.cart[existingIdx];
                const newQty = new PosDecimal(toQty(existing.quantity))
                    .add(new PosDecimal(toQty(res.quantity || '1.000')))
                    .toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP)
                    .toFixed(3);
                existing.quantity = newQty;
                this.selectedLineIndex = existingIdx;
            } else {
                this.cart.push({
                    lineKey: generateUuidV7(),
                    product_id: productId,
                    variant_id: variantId,
                    unit_id: unitId,
                    product_name: displayName,
                    barcode_snapshot: res.barcode || variant?.barcode || unit?.barcode || product.barcode || null,
                    unit_name_snapshot: unit?.unit_name || (product.type === 'weighted' ? 'كجم' : 'حبة'),
                    conversion_factor: toQty(res.conversionFactor || unit?.conversion_factor || '1.000'),
                    quantity: toQty(res.quantity || '1.000'),
                    unit_price: toMoney(res.unitPrice ?? unit?.sale_price ?? variant?.sale_price ?? product.sale_price ?? '0'),
                    discount_amount: '0.0000',
                    tax_rate: product.tax_rate ?? '0.00',
                    product_min_cost: product.min_allowed_sale_price ?? product.purchase_price ?? '0.0000',
                    variant_min_cost: variant ? (variant.min_allowed_sale_price ?? variant.purchase_price ?? '0.0000') : null,
                    unit_min_cost: unit ? (unit.min_allowed_sale_price ?? unit.purchase_price ?? '0.0000') : null,
                    available_units: availableUnits,
                    is_weighted: product.type === 'weighted',
                });
                this.selectedLineIndex = this.cart.length - 1;
            }

            this.$nextTick(() => this.focusBarcodeInput());
        },

        changeLineUnit(index, newUnitId) {
            const line = this.cart[index];
            if (!line) {
                return;
            }
            const unit = (line.available_units || []).find((u) => Number(u.id) === Number(newUnitId));
            const product = this.productsById.get(Number(line.product_id));
            if (unit) {
                line.unit_id = Number(unit.id);
                line.unit_name_snapshot = unit.unit_name;
                line.conversion_factor = toQty(unit.conversion_factor);
                line.unit_price = toMoney(unit.sale_price ?? product?.sale_price ?? line.unit_price);
                line.unit_min_cost = unit.min_allowed_sale_price ?? unit.purchase_price ?? '0.0000';
                line.barcode_snapshot = unit.barcode || product?.barcode || line.barcode_snapshot;
            } else if (product) {
                line.unit_id = null;
                line.unit_name_snapshot = 'حبة';
                line.conversion_factor = '1.000';
                line.unit_price = toMoney(product.sale_price);
                line.unit_min_cost = null;
            }
        },

        adjustLineQty(index, delta) {
            const line = this.cart[index];
            if (!line) {
                return;
            }
            const current = new PosDecimal(toQty(line.quantity));
            const step = new PosDecimal(String(delta));
            const next = current.add(step).toDecimalPlaces(3, PosDecimal.ROUND_HALF_UP);
            if (next.lte(0)) {
                return;
            }
            line.quantity = next.toFixed(3);
        },

        async removeCartItem(index) {
            const line = this.cart[index];
            if (!line) {
                return;
            }

            // Silent audit event for cart item removal (synced via /api/v1/sync/push -> activity_log)
            const auditUuid = generateUuidV7();
            await posDb.outbox.put({
                client_uuid: auditUuid,
                entity_type: 'audit_events',
                status: 'pending',
                retry_count: 0,
                created_at: new Date().toISOString(),
                last_error: null,
                payload: {
                    client_uuid: auditUuid,
                    event: 'cart_item_removed',
                    description: `حذف الصنف (${line.product_name}) من السلة`,
                    properties: {
                        product_id: line.product_id,
                        variant_id: line.variant_id,
                        unit_id: line.unit_id,
                        product_name: line.product_name,
                        barcode: line.barcode_snapshot,
                        quantity: line.quantity,
                        unit_price: line.unit_price,
                        terminal_code: this.terminalCode,
                        shift_client_uuid: this.activeShift?.client_uuid || null,
                    },
                },
            });

            this.cart.splice(index, 1);
            if (this.selectedLineIndex >= this.cart.length) {
                this.selectedLineIndex = Math.max(0, this.cart.length - 1);
            }

            await this.syncEngine.refreshOutboxState();
            this.$nextTick(() => this.focusBarcodeInput());
        },

        // Held Carts (F4)
        async openHeldCartsModal() {
            this.heldCarts = await posDb.held_carts.orderBy('created_at').reverse().toArray();
            this.modal = 'held';
        },

        async holdCurrentCart() {
            if (this.cart.length === 0) {
                this.showToast('warning', 'السلة فارغة ولا يوجد ما يتم تعليقه.');
                return;
            }
            await posDb.held_carts.add({
                created_at: new Date().toISOString(),
                customer_id: this.selectedCustomer?.id || null,
                customer_client_uuid: this.selectedCustomer?.client_uuid || null,
                customer_name: this.selectedCustomer?.name || 'عميل نقدي',
                order_discount_amount: this.orderDiscountAmount || '0',
                order_notes: this.orderNotes || '',
                items: JSON.parse(JSON.stringify(this.cart)),
                total_amount: this.cartComputation.totalAmount,
            });
            this.cart = [];
            this.selectedCustomer = null;
            this.orderDiscountAmount = '0';
            this.orderNotes = '';
            this.managerOverridePin = '';
            this.heldCarts = await posDb.held_carts.orderBy('created_at').reverse().toArray();
            this.showToast('success', 'تم تعليق الفاتورة بنجاح.');
            this.closeModal();
        },

        async restoreHeldCart(held) {
            if (!held) {
                return;
            }
            if (this.cart.length > 0) {
                await posDb.held_carts.add({
                    created_at: new Date().toISOString(),
                    customer_id: this.selectedCustomer?.id || null,
                    customer_client_uuid: this.selectedCustomer?.client_uuid || null,
                    customer_name: this.selectedCustomer?.name || 'عميل نقدي',
                    order_discount_amount: this.orderDiscountAmount || '0',
                    order_notes: this.orderNotes || '',
                    items: JSON.parse(JSON.stringify(this.cart)),
                    total_amount: this.cartComputation.totalAmount,
                });
            }

            this.cart = (held.items || []).map((i) => ({ ...i }));
            this.orderDiscountAmount = held.order_discount_amount || '0';
            this.orderNotes = held.order_notes || '';
            this.selectedCustomer = held.customer_id || held.customer_client_uuid
                ? (this.customers.find(
                    (c) => (held.customer_client_uuid && c.client_uuid === held.customer_client_uuid)
                        || Number(c.id) === Number(held.customer_id),
                ) || null)
                : null;
            this.selectedLineIndex = 0;

            await posDb.held_carts.delete(held.id);
            this.heldCarts = await posDb.held_carts.orderBy('created_at').reverse().toArray();
            this.closeModal();
            this.showToast('success', 'تم استرجاع السلة المعلقة.');
        },

        async deleteHeldCart(id) {
            await posDb.held_carts.delete(id);
            this.heldCarts = await posDb.held_carts.orderBy('created_at').reverse().toArray();
        },

        // Shift Management
        openShiftModal() {
            this.shiftOpeningCash = '0';
            this.shiftClosingActualCash = '';
            this.shiftNotes = '';
            this.modal = 'shift';
        },

        async openShiftLocal() {
            let openingCash;
            try {
                const dec = new PosDecimal(String(this.shiftOpeningCash || '0'));
                if (dec.lt(0)) {
                    this.showToast('error', 'رصيد الافتتاح لا يمكن أن يكون سالباً.');
                    return;
                }
                openingCash = dec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
            } catch {
                this.showToast('error', 'مبلغ افتتاح الوردية غير صالح.');
                return;
            }

            const shiftUuid = generateUuidV7();
            const nowIso = new Date().toISOString();
            const branchId = this.terminalInfo?.branch_id || this.userInfo?.branch_id || null;
            const terminalId = this.terminalInfo?.id || null;

            const shiftObj = {
                client_uuid: shiftUuid,
                server_id: null,
                terminal_id: terminalId,
                terminal_code: this.terminalCode || 'T01',
                branch_id: branchId,
                opened_at: nowIso,
                opening_cash: openingCash,
                cash_sales_total: '0.0000',
                cash_returns_total: '0.0000',
                cash_in_total: '0.0000',
                cash_out_total: '0.0000',
                expected_cash: openingCash,
                status: 'open',
                notes: this.shiftNotes || null,
            };

            await setMeta('active_shift', shiftObj);
            await posDb.outbox.put({
                client_uuid: shiftUuid,
                entity_type: 'shifts',
                status: 'pending',
                retry_count: 0,
                created_at: nowIso,
                last_error: null,
                payload: {
                    client_uuid: shiftUuid,
                    terminal_code: shiftObj.terminal_code,
                    ...(terminalId ? { terminal_id: terminalId } : {}),
                    ...(branchId ? { branch_id: branchId } : {}),
                    opening_cash: openingCash,
                    opened_at: nowIso,
                    status: 'open',
                    notes: shiftObj.notes,
                },
            });

            this.activeShift = shiftObj;
            await this.syncEngine.refreshOutboxState();
            this.closeModal();
            this.showToast('success', 'تم فتح الوردية محلياً بنجاح.');

            if (this.isOnline) {
                this.syncEngine.pushOutbox().catch(() => {});
            }
        },

        async closeShiftLocal() {
            if (!this.activeShift) {
                return;
            }

            let actualCash;
            try {
                const dec = new PosDecimal(String(this.shiftClosingActualCash || '0'));
                if (dec.lt(0)) {
                    this.showToast('error', 'الجرد الفعلي لا يمكن أن يكون سالباً.');
                    return;
                }
                actualCash = dec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
            } catch {
                this.showToast('error', 'قيمة النقد الفعلي غير صالحة.');
                return;
            }

            const nowIso = new Date().toISOString();
            const expectedCash = toMoney(this.activeShift.expected_cash || '0');
            const difference = subMoney(actualCash, expectedCash);

            const closedSnapshot = {
                ...this.activeShift,
                actual_cash: actualCash,
                difference,
                status: 'closed',
                closed_at: nowIso,
                notes: this.shiftNotes || this.activeShift.notes || null,
            };

            await posDb.outbox.put({
                client_uuid: closedSnapshot.client_uuid,
                entity_type: 'shifts',
                status: 'pending',
                retry_count: 0,
                created_at: closedSnapshot.opened_at || nowIso,
                last_error: null,
                payload: {
                    client_uuid: closedSnapshot.client_uuid,
                    terminal_code: closedSnapshot.terminal_code || this.terminalCode || 'T01',
                    ...(closedSnapshot.terminal_id ? { terminal_id: closedSnapshot.terminal_id } : {}),
                    ...(closedSnapshot.branch_id ? { branch_id: closedSnapshot.branch_id } : {}),
                    opening_cash: toMoney(closedSnapshot.opening_cash || '0'),
                    opened_at: closedSnapshot.opened_at || nowIso,
                    status: 'closed',
                    actual_cash: actualCash,
                    closed_at: nowIso,
                    notes: closedSnapshot.notes,
                },
            });

            await setMeta('active_shift', null);
            this.activeShift = null;
            await this.syncEngine.refreshOutboxState();

            this.dispatchPrintReceipt({
                type: 'shift_close',
                store: this.storeSettings,
                terminal: this.terminalInfo,
                cashier: this.userInfo,
                shift: closedSnapshot,
                printed_at: nowIso,
            });

            this.closeModal();
            this.showToast('success', 'تم إغلاق الوردية وإرسال بيانات تقرير Z للطباعة.');

            if (this.isOnline) {
                this.syncEngine.pushOutbox().catch(() => {});
            }
        },

        // PIN Lock Screen
        async openOrTogglePinLock() {
            if (!this.hasCashierPin) {
                this.lockSetupPinInput = '';
                this.lockError = '';
                this.modal = 'setup_pin';
                return;
            }
            this.lockPinInput = '';
            this.lockError = '';
            this.unlockPasswordMode = false;
            this.isLocked = true;
        },

        async saveQuickPinAndLock() {
            const pin = String(this.lockSetupPinInput || '').trim();
            if (!/^\d{4}$/.test(pin)) {
                this.lockError = 'يرجى إدخال رمز PIN مكون من 4 أرقام.';
                return;
            }
            const hash = await hashPinSafe(pin, this.terminalCode || 'T01');
            await setMeta('cashier_pin_hash', hash);
            this.hasCashierPin = true;
            this.lockSetupPinInput = '';
            this.lockError = '';
            this.modal = null;
            this.lockPinInput = '';
            this.isLocked = true;
        },

        async unlockWithPin() {
            const pin = String(this.lockPinInput || '').trim();
            if (!/^\d{4}$/.test(pin)) {
                this.lockError = 'أدخل 4 أرقام.';
                return;
            }
            const storedHash = await getMeta('cashier_pin_hash', null);
            const candidateHash = await hashPinSafe(pin, this.terminalCode || 'T01');
            if (!storedHash || candidateHash !== storedHash) {
                this.lockError = 'رمز PIN غير صحيح.';
                this.lockPinInput = '';
                return;
            }
            this.isLocked = false;
            this.lockPinInput = '';
            this.lockError = '';
            this.$nextTick(() => this.focusBarcodeInput());
        },

        async unlockWithPasswordOnline() {
            if (!this.isOnline) {
                this.lockError = 'يتطلب التحقق بكلمة المرور اتصالاً بالإنترنت.';
                return;
            }
            const res = await fetch('/api/v1/auth/terminal-login', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    login: this.userInfo?.email || this.userInfo?.phone,
                    password: this.unlockPasswordInput,
                    terminal_code: this.terminalCode,
                }),
            });
            if (!res.ok) {
                this.lockError = 'كلمة المرور غير صحيحة.';
                return;
            }
            const data = await res.json();
            await setMeta('terminal_token', data.token);
            this.terminalToken = data.token;
            this.isLocked = false;
            this.unlockPasswordMode = false;
            this.unlockPasswordInput = '';
            this.lockError = '';
            this.$nextTick(() => this.focusBarcodeInput());
        },

        // Checkout Flows (F2 Cash & F8 Yemeni Multi-Payment)
        async quickCashCheckout() {
            if (this.cart.length === 0) {
                this.showToast('warning', 'أضف صنفاً واحداً على الأقل لإتمام البيع.');
                return;
            }
            if (!this.activeShift) {
                this.openShiftModal();
                this.showToast('warning', 'يرجى فتح الوردية أولاً قبل البيع.');
                return;
            }

            const comp = this.cartComputation;
            if (!comp.valid) {
                this.showToast('error', comp.errors[0] || 'تحقق من بيانات السلة.');
                return;
            }

            if (comp.requiresCostOverride && !this.canOverrideDiscounts() && !this.managerOverridePin) {
                this.overridePinInput = '';
                this.overridePinError = '';
                this.pendingOverrideCallback = async (pin) => {
                    this.managerOverridePin = pin;
                    await this.finalizeSale({
                        payments: [{ method: 'cash', amount: comp.totalAmount, reference_number: null }],
                        creditAmount: '0.0000',
                        managerOverridePin: pin,
                    });
                };
                this.modal = 'override_pin';
                return;
            }

            await this.finalizeSale({
                payments: [{ method: 'cash', amount: comp.totalAmount, reference_number: null }],
                creditAmount: '0.0000',
                managerOverridePin: this.managerOverridePin || null,
            });
        },

        confirmManagerOverrideModal() {
            const pin = String(this.overridePinInput || '').trim();
            if (!/^\d{4}$/.test(pin)) {
                this.overridePinError = 'أدخل رمز المدير المكون من 4 أرقام.';
                return;
            }
            const cb = this.pendingOverrideCallback;
            this.pendingOverrideCallback = null;
            this.modal = null;
            if (cb) {
                cb(pin);
            }
        },

        openPaymentModal() {
            if (this.cart.length === 0) {
                this.showToast('warning', 'السلة فارغة.');
                return;
            }
            if (!this.activeShift) {
                this.openShiftModal();
                this.showToast('warning', 'يرجى فتح الوردية أولاً.');
                return;
            }
            const comp = this.cartComputation;
            if (!comp.valid) {
                this.showToast('error', comp.errors[0] || 'تحقق من بيانات السلة.');
                return;
            }

            this.paymentRows = [
                { method: 'cash', amount: comp.totalAmount, reference_number: '', untouchedDefault: true },
            ];
            this.creditAmountInput = '0';
            this.paymentNotes = this.orderNotes || '';
            this.customerSearchQuery = '';
            this.showNewCustomerForm = false;
            this.newCustomerForm = { name: '', phone: '', credit_limit: '0', address: '' };
            this.paymentOverridePin = this.managerOverridePin || '';
            this.modal = 'payment';
        },

        applyBanknote(denomination) {
            let cashRow = this.paymentRows.find((r) => r.method === 'cash');
            if (!cashRow) {
                cashRow = { method: 'cash', amount: '0', reference_number: '', untouchedDefault: false };
                this.paymentRows.unshift(cashRow);
            }
            if (cashRow.untouchedDefault) {
                cashRow.amount = toMoney(denomination);
                cashRow.untouchedDefault = false;
            } else {
                cashRow.amount = addMoney(cashRow.amount || '0', denomination);
            }
        },

        setExactCash() {
            let cashRow = this.paymentRows.find((r) => r.method === 'cash');
            if (!cashRow) {
                cashRow = { method: 'cash', amount: '0', reference_number: '', untouchedDefault: false };
                this.paymentRows.unshift(cashRow);
            }
            let otherPaid = new PosDecimal('0');
            for (const r of this.paymentRows) {
                if (r !== cashRow) {
                    try {
                        otherPaid = otherPaid.add(new PosDecimal(toMoney(r.amount || '0')));
                    } catch {
                        // ignore invalid row
                    }
                }
            }
            const credit = new PosDecimal(toMoney(this.creditAmountInput || '0'));
            const needed = new PosDecimal(this.cartComputation.totalAmount).sub(otherPaid).sub(credit);
            cashRow.amount = needed.gt(0) ? needed.toFixed(4) : '0.0000';
            cashRow.untouchedDefault = false;
        },

        clearCashPayment() {
            const cashRow = this.paymentRows.find((r) => r.method === 'cash');
            if (cashRow) {
                cashRow.amount = '0';
                cashRow.untouchedDefault = false;
            }
        },

        addPaymentRow(method = 'kuraimi') {
            this.paymentRows.push({
                method,
                amount: '0',
                reference_number: '',
                untouchedDefault: false,
            });
        },

        removePaymentRow(idx) {
            this.paymentRows.splice(idx, 1);
            if (this.paymentRows.length === 0) {
                this.creditAmountInput = this.cartComputation.totalAmount;
            }
        },

        setRemainingAsCredit() {
            let paidSum = new PosDecimal('0');
            for (const r of this.paymentRows) {
                try {
                    const amt = new PosDecimal(toMoney(r.amount || '0'));
                    if (amt.gt(0)) {
                        paidSum = paidSum.add(amt);
                    }
                } catch {
                    // ignore
                }
            }
            const remaining = new PosDecimal(this.cartComputation.totalAmount).sub(paidSum);
            this.creditAmountInput = remaining.gt(0) ? remaining.toFixed(4) : '0.0000';
        },

        async createOfflineCustomer() {
            const name = String(this.newCustomerForm.name || '').trim();
            if (!name) {
                this.showToast('error', 'يرجى إدخال اسم العميل.');
                return;
            }

            let creditLimit = '0.0000';
            try {
                const dec = new PosDecimal(String(this.newCustomerForm.credit_limit || '0'));
                if (dec.lt(0)) {
                    this.showToast('error', 'سقف الدين لا يمكن أن يكون سالباً.');
                    return;
                }
                creditLimit = dec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
            } catch {
                this.showToast('error', 'سقف الدين غير صالح.');
                return;
            }

            const existingOffline = await posDb.customers.where('id').below(0).toArray();
            const minId = existingOffline.reduce((acc, c) => Math.min(acc, Number(c.id)), 0);
            const tempId = minId - 1;
            const clientUuid = generateUuidV7();
            const nowIso = new Date().toISOString();
            const phone = String(this.newCustomerForm.phone || '').trim() || null;
            const address = String(this.newCustomerForm.address || '').trim() || null;

            const customerObj = {
                id: tempId,
                client_uuid: clientUuid,
                name,
                phone,
                whatsapp: phone,
                address,
                credit_limit: creditLimit,
                current_balance: '0.0000',
            };

            await posDb.customers.put(customerObj);
            await posDb.outbox.put({
                client_uuid: clientUuid,
                entity_type: 'customers',
                status: 'pending',
                retry_count: 0,
                created_at: nowIso,
                last_error: null,
                payload: {
                    client_uuid: clientUuid,
                    name,
                    phone,
                    whatsapp: phone,
                    address,
                    credit_limit: creditLimit,
                },
            });

            await this.refreshLocalStateFromDb();
            await this.syncEngine.refreshOutboxState();

            this.selectedCustomer = customerObj;
            this.showNewCustomerForm = false;
            this.newCustomerForm = { name: '', phone: '', credit_limit: '0', address: '' };
            this.showToast('success', `تم إنشاء العميل (${name}) أوفلاين واختياره للفاتورة.`);
        },

        async submitPaymentModal() {
            const comp = this.cartComputation;
            const payCheck = this.paymentSummary;

            if (!comp.valid) {
                this.showToast('error', comp.errors[0] || 'بيانات السلة غير مكتملة.');
                return;
            }
            if (!payCheck.isBalanced) {
                this.showToast('error', payCheck.errors[0] || 'المدفوع + الآجل يجب أن يساوي الإجمالي تماماً.');
                return;
            }
            if (new PosDecimal(payCheck.creditAmount).gt(0) && !this.selectedCustomer) {
                this.showToast('error', 'يرجى اختيار عميل أو إنشاء عميل آجل جديد.');
                return;
            }
            if (comp.requiresCostOverride && !this.canOverrideDiscounts()) {
                if (!/^\d{4}$/.test(String(this.paymentOverridePin || '').trim())) {
                    this.showToast('error', 'البيع أقل من حد التكلفة يتطلب إدخال رمز موافقة المدير (4 أرقام).');
                    return;
                }
            }

            await this.finalizeSale({
                payments: payCheck.payments,
                creditAmount: payCheck.creditAmount,
                managerOverridePin: this.paymentOverridePin || this.managerOverridePin || null,
                notes: this.paymentNotes || this.orderNotes || null,
            });
        },

        async finalizeSale({ payments, creditAmount, managerOverridePin = null, notes = null }) {
            if (!this.activeShift) {
                this.showToast('error', 'لا توجد وردية مفتوحة.');
                return;
            }

            const comp = this.cartComputation;
            if (!comp.valid) {
                this.showToast('error', comp.errors[0] || 'خطأ في حساب السلة.');
                return;
            }

            const payCheck = calculatePaymentBalance(comp.totalAmount, payments, creditAmount);
            if (!payCheck.isBalanced) {
                this.showToast('error', payCheck.errors[0] || 'عدم توازن في الفاتورة.');
                return;
            }

            if (new PosDecimal(payCheck.creditAmount).gt(0) && !this.selectedCustomer) {
                this.showToast('error', 'يلزم تحديد العميل لإتمام البيع الآجل.');
                return;
            }

            const nowIso = new Date().toISOString();
            const orderUuid = generateUuidV7();
            const { invoiceNumber } = await allocateNextInvoiceNumber();

            const paymentRecords = payCheck.payments.map((p) => ({
                client_uuid: generateUuidV7(),
                method: p.method,
                reference_number: p.reference_number || null,
                amount: p.amount,
                paid_at: nowIso,
                notes: p.notes || null,
            }));

            const orderItemsPayload = this.cart.map((line, idx) => {
                const lineCalc = comp.lines[idx];
                return {
                    product_id: Number(line.product_id),
                    variant_id: line.variant_id ? Number(line.variant_id) : null,
                    unit_id: line.unit_id ? Number(line.unit_id) : null,
                    product_name: line.product_name,
                    barcode_snapshot: line.barcode_snapshot,
                    unit_name_snapshot: line.unit_name_snapshot || 'حبة',
                    conversion_factor: lineCalc.conversionFactor,
                    quantity: lineCalc.quantity,
                    base_quantity: lineCalc.baseQuantity,
                    unit_price: lineCalc.unitPrice,
                    discount_amount: lineCalc.discountAmount,
                    tax_rate: lineCalc.taxRate,
                    tax_amount: lineCalc.taxAmount,
                    net_total: lineCalc.lineNet,
                };
            });

            const customerRef = this.selectedCustomer
                ? (Number(this.selectedCustomer.id) > 0
                    ? { customer_id: Number(this.selectedCustomer.id), customer_client_uuid: this.selectedCustomer.client_uuid || null }
                    : { customer_client_uuid: this.selectedCustomer.client_uuid })
                : {};

            const orderPayload = {
                client_uuid: orderUuid,
                shift_client_uuid: this.activeShift.client_uuid,
                ...(this.activeShift.server_id ? { shift_id: this.activeShift.server_id } : {}),
                ...(this.terminalInfo?.id ? { terminal_id: this.terminalInfo.id } : {}),
                ...(this.terminalInfo?.branch_id || this.userInfo?.branch_id
                    ? { branch_id: this.terminalInfo?.branch_id || this.userInfo?.branch_id }
                    : {}),
                ...customerRef,
                invoice_number: invoiceNumber,
                type: 'sale',
                order_discount_amount: comp.orderDiscountAmount,
                ...(comp.requiresCostOverride && managerOverridePin ? { manager_override_pin: managerOverridePin } : {}),
                items: orderItemsPayload.map(({ base_quantity, tax_amount, net_total, ...apiLine }) => apiLine),
                payments: paymentRecords,
                change_amount: payCheck.changeAmount,
                credit_amount: payCheck.creditAmount,
                notes: notes ?? (this.orderNotes || null),
                sold_at: nowIso,
            };

            const customerNameSnapshot = this.selectedCustomer?.name || 'عميل نقدي';
            const customerPhoneSnapshot = this.selectedCustomer?.phone || null;

            await posDb.outbox.put({
                client_uuid: orderUuid,
                entity_type: 'orders',
                status: 'pending',
                retry_count: 0,
                created_at: nowIso,
                last_error: null,
                payload: orderPayload,
            });

            await deductLocalInventory(orderItemsPayload);

            // Update local active shift cash metrics
            if (new PosDecimal(payCheck.netCashReceived).gt(0)) {
                const updatedShift = { ...this.activeShift };
                updatedShift.cash_sales_total = addMoney(updatedShift.cash_sales_total || '0', payCheck.netCashReceived);
                updatedShift.expected_cash = addMoney(updatedShift.expected_cash || '0', payCheck.netCashReceived);
                await setMeta('active_shift', updatedShift);
                this.activeShift = updatedShift;
            }

            // Update local customer balance if credit_amount > 0
            if (this.selectedCustomer && new PosDecimal(payCheck.creditAmount).gt(0)) {
                const updatedCust = {
                    ...this.selectedCustomer,
                    current_balance: addMoney(this.selectedCustomer.current_balance || '0', payCheck.creditAmount),
                };
                await posDb.customers.put(updatedCust);
            }

            // Phase 5 Receipt Printing Contract Event
            this.dispatchPrintReceipt({
                type: 'sale',
                store: this.storeSettings,
                terminal: this.terminalInfo,
                cashier: this.userInfo,
                invoice_number: invoiceNumber,
                client_uuid: orderUuid,
                sold_at: nowIso,
                customer_name: customerNameSnapshot,
                customer_phone: customerPhoneSnapshot,
                subtotal: comp.subtotal,
                item_discount_total: comp.itemDiscountTotal,
                order_discount_amount: comp.orderDiscountAmount,
                tax_total: comp.taxTotal,
                total_amount: comp.totalAmount,
                paid_amount: payCheck.paidAmount,
                credit_amount: payCheck.creditAmount,
                change_amount: payCheck.changeAmount,
                items: orderItemsPayload,
                payments: paymentRecords,
                notes: orderPayload.notes,
            });

            // Auto-clear selected customer and cart immediately after checkout
            this.cart = [];
            this.selectedCustomer = null;
            this.selectedLineIndex = 0;
            this.orderDiscountAmount = '0';
            this.orderNotes = '';
            this.managerOverridePin = '';
            this.paymentOverridePin = '';
            this.modal = null;

            await this.refreshLocalStateFromDb();
            await this.syncEngine.refreshOutboxState();

            this.showToast('success', `تم إصدار الفاتورة ${invoiceNumber} بنجاح.`);
            this.$nextTick(() => this.focusBarcodeInput());

            if (this.isOnline) {
                this.syncEngine.pushOutbox().catch(() => {});
            }
        },

        // Quick Drawer Movements (Offline)
        openDrawerModal() {
            if (!this.activeShift) {
                this.openShiftModal();
                this.showToast('warning', 'حركات الدرج تتطلب فتح وردية أولاً.');
                return;
            }
            this.drawerTab = 'expense';
            this.drawerForm = {
                category: EXPENSE_CATEGORIES[0],
                amount: '',
                payee_id: '',
                method: 'cash',
                reference_number: '',
                customer_id: '',
                notes: '',
            };
            this.modal = 'drawer';
        },

        async submitDrawerMovement() {
            if (!this.activeShift) {
                this.showToast('error', 'لا توجد وردية مفتوحة.');
                return;
            }

            let amount;
            try {
                const dec = new PosDecimal(String(this.drawerForm.amount || '0'));
                if (dec.lte(0)) {
                    this.showToast('error', 'يجب أن يكون المبلغ أكبر من صفر.');
                    return;
                }
                amount = dec.toDecimalPlaces(4, PosDecimal.ROUND_HALF_UP).toFixed(4);
            } catch {
                this.showToast('error', 'قيمة المبلغ غير صالحة.');
                return;
            }

            const uuid = generateUuidV7();
            const nowIso = new Date().toISOString();
            const shiftRef = {
                shift_client_uuid: this.activeShift.client_uuid,
                ...(this.activeShift.server_id ? { shift_id: this.activeShift.server_id } : {}),
            };

            let receiptDetail = null;

            if (this.drawerTab === 'expense') {
                await posDb.outbox.put({
                    client_uuid: uuid,
                    entity_type: 'expenses',
                    status: 'pending',
                    retry_count: 0,
                    created_at: nowIso,
                    last_error: null,
                    payload: {
                        client_uuid: uuid,
                        ...shiftRef,
                        category: this.drawerForm.category || 'نثريات عامة',
                        amount,
                        payment_source: 'cash_drawer',
                        spent_at: nowIso,
                        notes: this.drawerForm.notes || null,
                    },
                });
                const updatedShift = { ...this.activeShift };
                updatedShift.cash_out_total = addMoney(updatedShift.cash_out_total || '0', amount);
                updatedShift.expected_cash = subMoney(updatedShift.expected_cash || '0', amount);
                await setMeta('active_shift', updatedShift);
                this.activeShift = updatedShift;

                receiptDetail = {
                    type: 'drawer_voucher',
                    voucher_type: 'expense',
                    category: this.drawerForm.category,
                    amount,
                    payment_source: 'cash_drawer',
                    notes: this.drawerForm.notes,
                };
            } else if (this.drawerTab === 'employee_advance') {
                const payee = this.employeeQuickPayees.find((e) => Number(e.id) === Number(this.drawerForm.payee_id));
                if (!payee) {
                    this.showToast('error', 'يرجى اختيار العامل / الموظف.');
                    return;
                }
                await posDb.outbox.put({
                    client_uuid: uuid,
                    entity_type: 'employee_advances',
                    status: 'pending',
                    retry_count: 0,
                    created_at: nowIso,
                    last_error: null,
                    payload: {
                        client_uuid: uuid,
                        ...shiftRef,
                        employee_id: Number(payee.id),
                        type: 'advance',
                        amount,
                        payment_source: 'cash_drawer',
                        transaction_date: nowIso,
                        notes: this.drawerForm.notes || 'سلفة من درج الكاشير',
                    },
                });
                const updatedShift = { ...this.activeShift };
                updatedShift.cash_out_total = addMoney(updatedShift.cash_out_total || '0', amount);
                updatedShift.expected_cash = subMoney(updatedShift.expected_cash || '0', amount);
                await setMeta('active_shift', updatedShift);
                this.activeShift = updatedShift;

                receiptDetail = {
                    type: 'drawer_voucher',
                    voucher_type: 'employee_advance',
                    payee_name: payee.name,
                    amount,
                    payment_source: 'cash_drawer',
                    notes: this.drawerForm.notes,
                };
            } else if (this.drawerTab === 'supplier_payment') {
                const supplier = this.supplierQuickPayees.find((s) => Number(s.id) === Number(this.drawerForm.payee_id));
                if (!supplier) {
                    this.showToast('error', 'يرجى اختيار المورد.');
                    return;
                }
                await posDb.outbox.put({
                    client_uuid: uuid,
                    entity_type: 'supplier_payments',
                    status: 'pending',
                    retry_count: 0,
                    created_at: nowIso,
                    last_error: null,
                    payload: {
                        client_uuid: uuid,
                        ...shiftRef,
                        supplier_id: Number(supplier.id),
                        method: this.drawerForm.method || 'cash',
                        payment_source: 'cash_drawer',
                        reference_number: this.drawerForm.reference_number || null,
                        amount,
                        paid_at: nowIso,
                        notes: this.drawerForm.notes || 'دفعة للمورد من درج الكاشير',
                    },
                });
                const updatedShift = { ...this.activeShift };
                updatedShift.cash_out_total = addMoney(updatedShift.cash_out_total || '0', amount);
                updatedShift.expected_cash = subMoney(updatedShift.expected_cash || '0', amount);
                await setMeta('active_shift', updatedShift);
                this.activeShift = updatedShift;

                receiptDetail = {
                    type: 'drawer_voucher',
                    voucher_type: 'supplier_payment',
                    payee_name: supplier.name,
                    amount,
                    method: this.drawerForm.method || 'cash',
                    payment_source: 'cash_drawer',
                    reference_number: this.drawerForm.reference_number || null,
                    notes: this.drawerForm.notes,
                };
            } else if (this.drawerTab === 'customer_settlement') {
                const customer = this.customers.find((c) => Number(c.id) === Number(this.drawerForm.customer_id));
                if (!customer) {
                    this.showToast('error', 'يرجى اختيار العميل.');
                    return;
                }
                const method = this.drawerForm.method || 'cash';
                await posDb.outbox.put({
                    client_uuid: uuid,
                    entity_type: 'payments',
                    status: 'pending',
                    retry_count: 0,
                    created_at: nowIso,
                    last_error: null,
                    payload: {
                        client_uuid: uuid,
                        ...shiftRef,
                        ...(Number(customer.id) > 0 ? { customer_id: Number(customer.id) } : {}),
                        ...(customer.client_uuid ? { customer_client_uuid: customer.client_uuid } : {}),
                        method,
                        reference_number: this.drawerForm.reference_number || null,
                        amount,
                        paid_at: nowIso,
                        notes: this.drawerForm.notes || 'سند قبض عميل من الكاشير',
                    },
                });

                await posDb.customers.put({
                    ...customer,
                    current_balance: subMoney(customer.current_balance || '0', amount),
                });

                if (method === 'cash') {
                    const updatedShift = { ...this.activeShift };
                    updatedShift.cash_in_total = addMoney(updatedShift.cash_in_total || '0', amount);
                    updatedShift.expected_cash = addMoney(updatedShift.expected_cash || '0', amount);
                    await setMeta('active_shift', updatedShift);
                    this.activeShift = updatedShift;
                }

                receiptDetail = {
                    type: 'drawer_voucher',
                    voucher_type: 'customer_settlement',
                    payee_name: customer.name,
                    amount,
                    method,
                    reference_number: this.drawerForm.reference_number || null,
                    notes: this.drawerForm.notes,
                };
            }

            if (receiptDetail) {
                this.dispatchPrintReceipt({
                    ...receiptDetail,
                    client_uuid: uuid,
                    store: this.storeSettings,
                    terminal: this.terminalInfo,
                    cashier: this.userInfo,
                    created_at: nowIso,
                });
            }

            await this.refreshLocalStateFromDb();
            await this.syncEngine.refreshOutboxState();
            this.closeModal();
            this.showToast('success', 'تم تسجيل حركة الدرج بنجاح.');

            if (this.isOnline) {
                this.syncEngine.pushOutbox().catch(() => {});
            }
        },

        // Emergency AES-GCM Encrypted Backup
        openBackupModal() {
            this.backupPassphrase = '';
            this.backupStatusMsg = '';
            this.modal = 'backup';
        },

        async downloadEncryptedBackup() {
            try {
                const { filename, jsonString, outboxCount } = await this.syncEngine.exportEncryptedBackup(this.backupPassphrase);
                const blob = new Blob([jsonString], { type: 'application/json;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                this.backupStatusMsg = `تم تنزيل النسخة المشفرة (${outboxCount} عملية معلقة) بنجاح.`;
            } catch (err) {
                this.showToast('error', err?.message || 'تعذر تصدير النسخة المشفرة.');
            }
        },

        async openOutboxModal() {
            this.pendingOutboxItems = await posDb.outbox.orderBy('created_at').toArray();
            this.modal = 'outbox';
        },

        dispatchPrintReceipt(detail) {
            window.dispatchEvent(new CustomEvent('pos:print-receipt', { detail }));
        },

        closeModal() {
            this.modal = null;
            this.pendingOverrideCallback = null;
            this.$nextTick(() => this.focusBarcodeInput());
        },

        // Global Keyboard Shortcuts
        handleGlobalKeydown(e) {
            if (!this.terminalToken || this.isLocked) {
                return;
            }

            if (e.key === 'Escape' && this.modal) {
                e.preventDefault();
                this.closeModal();
                return;
            }

            if (e.key === 'F2') {
                e.preventDefault();
                this.quickCashCheckout();
                return;
            }

            if (e.key === 'F4') {
                e.preventDefault();
                this.openHeldCartsModal();
                return;
            }

            if (e.key === 'F8') {
                e.preventDefault();
                this.openPaymentModal();
                return;
            }

            if (this.modal) {
                return;
            }

            const activeEl = document.activeElement;
            const tag = activeEl?.tagName || '';
            const isBarcodeBox = activeEl === this.$refs.barcodeInput;
            const allowPlusMinus = (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT')
                || (isBarcodeBox && String(this.barcodeInput || '') === '');

            if (allowPlusMinus && this.cart.length > 0) {
                if (e.key === '+' || e.key === '=' || e.code === 'NumpadAdd') {
                    e.preventDefault();
                    this.adjustLineQty(this.selectedLineIndex, 1);
                } else if (e.key === '-' || e.code === 'NumpadSubtract') {
                    e.preventDefault();
                    this.adjustLineQty(this.selectedLineIndex, -1);
                }
            }
        },

        showToast(type, message) {
            this.toast = { show: true, type, message };
            if (this._toastTimer) {
                clearTimeout(this._toastTimer);
            }
            this._toastTimer = setTimeout(() => {
                this.toast.show = false;
            }, 4000);
        },
    };
}

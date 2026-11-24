import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import JsBarcode from 'jsbarcode';
import QRCode from 'qrcode';
import { createPosApp } from './pos/pos-app.js';

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.JsBarcode = JsBarcode;
window.QRCode = QRCode;
window.Alpine = Alpine;

Alpine.data('posApp', createPosApp);

Alpine.start();

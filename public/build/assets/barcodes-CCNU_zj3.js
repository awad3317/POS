import{m as n,a as y,b as v}from"./module.esm-kwW7fiIL.js";import{J as m,p as x,f as S}from"./pos-print-BoCDDSly.js";n.plugin(y);n.plugin(v);window.JsBarcode=m;window.Alpine=n;function c(a){return String(a??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;")}function f(a,e=28,t=1.4,i=10){const s=document.createElementNS("http://www.w3.org/2000/svg","svg"),r=String(a??"").trim(),l=/^\d{13}$/.test(r);try{m(s,r,{format:l?"EAN13":"CODE128",width:t,height:e,displayValue:!0,fontSize:i,margin:2,background:"#ffffff",lineColor:"#000000"})}catch{m(s,r,{format:"CODE128",width:t,height:e,displayValue:!0,fontSize:i,margin:2,background:"#ffffff",lineColor:"#000000"})}return s.outerHTML}const g={"50x25mm":{widthMm:50,heightMm:25,svgHeight:24,barWidth:1.25,fontSize:9},"40x30mm":{widthMm:40,heightMm:30,svgHeight:26,barWidth:1.15,fontSize:9},"38x25mm":{widthMm:38,heightMm:25,svgHeight:22,barWidth:1.05,fontSize:8},a4:{widthMm:48,heightMm:26,svgHeight:24,barWidth:1.2,fontSize:9}};n.data("barcodeLabelsApp",(a={})=>({store:a.store||{},searchUrl:a.searchUrl||"/barcodes/search",query:"",loading:!1,searchItems:a.initialItems||[],selectedLabels:[],paperSize:"50x25mm",showStoreName:!0,showProductName:!0,showPrice:!0,_debounceTimer:null,init(){this.searchItems.length>0&&this.selectedLabels.length===0&&this.addLabel(this.searchItems[0],1)},fmtPrice(e){var s,r;const t=Number(((s=this.store)==null?void 0:s.decimal_places)??0),i=((r=this.store)==null?void 0:r.currency_symbol)||"ر.ي";return`${S(e||"0",t)} ${i}`},renderPreviewSvg(e){return e?f(e,26,1.25,9):""},onSearchInput(){this._debounceTimer&&clearTimeout(this._debounceTimer),this._debounceTimer=setTimeout(()=>{this.fetchSearch()},180)},async fetchSearch(){this.loading=!0;try{const e=`${this.searchUrl}?q=${encodeURIComponent(String(this.query||"").trim())}`,t=await fetch(e,{headers:{Accept:"application/json"}});if(t.ok){const i=await t.json();this.searchItems=i.items||[]}}finally{this.loading=!1}},addLabel(e,t=1){if(!e||!e.barcode)return;const i=this.selectedLabels.find(s=>s.key===e.key);i?i.copies=Math.min(500,Number(i.copies||1)+t):this.selectedLabels.push({...e,copies:t})},removeLabel(e){this.selectedLabels.splice(e,1)},totalStickerCount(){return this.selectedLabels.reduce((e,t)=>e+Math.max(1,Number(t.copies||1)),0)},async printLabels(){var d;if(this.selectedLabels.length===0)return;const e=g[this.paperSize]||g["50x25mm"],t=this.paperSize==="a4",i=((d=this.store)==null?void 0:d.name)||"Yemen POS",s=[];for(const o of this.selectedLabels){const p=Math.max(1,Math.min(500,Number.parseInt(String(o.copies||1),10)||1)),u=f(o.barcode,e.svgHeight,e.barWidth,e.fontSize),b=o.subtitle?`${o.name} (${o.subtitle})`:o.name,w=`
                <div class="sticker">
                    ${this.showStoreName?`<div class="store">${c(i)}</div>`:""}
                    ${this.showProductName?`<div class="title">${c(b)}</div>`:""}
                    <div class="barcode">${u}</div>
                    ${this.showPrice?`<div class="price">${c(this.fmtPrice(o.price))}</div>`:""}
                </div>
            `;for(let h=0;h<p;h+=1)s.push(w)}const l=`<!DOCTYPE html>
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
    ${t?`
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
            `:`
                @page { size: ${e.widthMm}mm ${e.heightMm}mm; margin: 0; }
                body { width: ${e.widthMm}mm; }
                .sheet { display: block; }
                .sticker {
                    width: ${e.widthMm}mm;
                    height: ${e.heightMm}mm;
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
            `}
    .store { font-size: 7.5px; font-weight: 700; line-height: 1.1; max-width: 100%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    .title { font-size: 8px; font-weight: 600; line-height: 1.15; max-width: 100%; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; margin: 0.5mm 0; }
    .barcode { max-width: 100%; display: flex; justify-content: center; }
    .barcode svg { max-width: 100%; height: auto; }
    .price { font-size: 9px; font-weight: 700; line-height: 1.1; }
</style>
</head>
<body><div class="sheet">${s.join("")}</div></body>
</html>`;await x(l)}}));n.start();

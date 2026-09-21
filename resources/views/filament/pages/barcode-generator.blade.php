<x-filament-panels::page>
    {{-- تحميل الخط مرة واحدة فقط لكامل الصفحة --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">

    <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); direction: rtl;">
        
        <div style="margin-bottom: 24px;">
            {{ $this->form }}
        </div>

        @if($product_id && $product_barcode)
            <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 24px 0;">

            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f3f4f6; padding: 32px; border-radius: 8px; border: 2px dashed #d1d5db;">
                
                <div id="print-area" class="barcode-grid">
                    @php 
                        $qty = min(max(1, (int) $print_qty), 500); 
                    @endphp

                    @for ($i = 0; $i < $qty; $i++)
                        <div class="barcode-ticket">
                            <div class="p-name" title="{{ $product_name }}">{{ $product_name }}</div>
                            
                            {{-- كود 128 بدون نجوم، مع هوامش مناسبة للقارئ --}}
                            @php
                                $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
                            @endphp

                            <div style="margin: 4px 0; display: flex; justify-content: center;">
                                {!! $generator->getBarcode($product_barcode, $generator::TYPE_CODE_128, 1.5, 45) !!}
                            </div>
                            
                            <div class="b-text">{{ $product_barcode }}</div>
                            <div class="p-price">السعر: {{ $product_price }}</div>
                        </div>
                    @endfor
                </div>

                <button type="button" onclick="window.print()" style="margin-top: 24px; background-color: #22c55e; color: white; padding: 12px 32px; font-size: 16px; font-weight: bold; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(34, 197, 94, 0.2);">
                    🖨️ اضغط هنا لطباعة الكروت المتكررة فوراً
                </button>
            </div>
        @endif
    </div>

    <style>
        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 15px;
            width: 100%;
            justify-content: center;
        }

        .barcode-ticket {
            background: white; 
            padding: 12px 8px; 
            border-radius: 6px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); 
            text-align: center; 
            border: 1px solid #e5e7eb;
            box-sizing: border-box;
            page-break-inside: avoid;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .p-name { 
            font-size: 13px; 
            font-weight: bold; 
            color: #111827; 
            margin-bottom: 4px; 
            max-width: 100%;
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
        }

        /* تنسيق باركود 128 مع المحافظة على مساحة بيضاء على الأطراف لضمان قراءة الماسح */
        .b-lines { 
            font-family: 'Libre Barcode 128', cursive; 
            font-size: 42px; 
            margin: 0; 
            padding: 2px 10px; 
            line-height: 1; 
            color: black; 
            white-space: nowrap; 
            display: block;
            letter-spacing: 0;
        }

        .b-text { 
            font-size: 11px; 
            font-weight: 600; 
            letter-spacing: 1px;
            color: #4b5563; 
            margin-top: 2px; 
        }

        .p-price { 
            font-size: 13px; 
            font-weight: bold; 
            color: #059669; 
            margin-top: 4px; 
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100% !important;
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important; 
                gap: 10px !important;
                padding: 10px !important;
                margin: 0 !important;
            }
            .barcode-ticket {
                border: 1px dashed #bbb !important;
                box-shadow: none !important;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</x-filament-panels::page>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page {
            margin: 0px;
        }
        body {
            font-family: 'Amiri', 'DejaVu Sans', "Times New Roman", sans-serif;
            font-size: 13px;
            margin: 10px;
            padding: 0;
            direction: rtl;
            text-align: right;
            color: #000;
        }
        .text-left {
            text-align: left !important;
        }
        .text-center {
            text-align: center !important;
        }
        .text-right {
            text-align: right !important;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 5px;
        }
        .invoice-header h1 {
            font-size: 18px;
            margin: 5px 0 2px 0;
            font-weight: bold;
        }
        .invoice-header p {
            font-size: 12px;
            margin: 2px 0;
        }
        .invoice-title {
            font-size: 15px;
            font-weight: bold;
            margin: 8px 0;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 3px 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 5px;
        }
        .meta-table td {
            padding: 1px 0;
            font-size: 12px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .main-table th {
            border-bottom: 1px solid #000;
            padding: 4px 2px;
            font-size: 12px;
            font-weight: bold;
        }
        .main-table td {
            padding: 4px 2px;
            font-size: 12px;
            vertical-align: top;
        }
        .border-b-dashed {
            border-bottom: 1px dashed #000;
        }
        .summary-table {
            width: 100%;
            margin-top: 5px;
            border-top: 1px solid #000;
        }
        .summary-table td {
            padding: 3px 2px;
            font-size: 13px;
        }
        .grand-total-row td {
            font-size: 15px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 2px;
        }
        .discount-text {
            color: #c00;
        }
        .invoice-footer {
            text-align: center;
            font-size: 12px;
            margin-top: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="invoice-header">
        <h1>{{ $site_name }}</h1>
        @if($site_description)
            <p>{{ $site_description }}</p>
        @endif
        <div class="invoice-title text-center">فاتورة مبيعات</div>
    </div>

    <table class="meta-table">
        <tr>
            <td class="text-right"><strong>رقم الفاتورة:</strong> #{{ str_pad($invoiceNumber, 6, '0', STR_PAD_LEFT) }}</td>
            <td class="text-left"><strong>التاريخ:</strong> {{ $date }}</td>
        </tr>
        <tr>
            <td class="text-right"><strong>الوقت:</strong> {{ $time }}</td>
            <td></td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th class="text-right" style="width: 35%;">المنتج</th>
                <th class="text-center" style="width: 15%;">السعر</th>
                <th class="text-center" style="width: 15%;">الخصم</th>
                <th class="text-center" style="width: 10%;">الكمية</th>
                <th class="text-left" style="width: 25%;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $i = 0; 
                $total_price = 0;
                $total_discount = 0;
                $grand_total = 0;
            @endphp
            @foreach($items as $item)
                @php 
                    $i++; 
                    $discount = $item->discount ?? 0;
                    $item_total = $item->price * $item->quantity;
                    $discount_amount = $discount * $item->quantity;
                    $item_total_after_discount = ($item->price - $discount) * $item->quantity;
                    $total_price += $item_total;
                    $total_discount += $discount_amount;
                    $grand_total += $item_total_after_discount;
                @endphp
                <tr>
                    <td class="text-right">
                        <div>{{ $item->product->name }}</div>
                    </td>
                    <td class="text-center">{{ number_format($item['price'], 2) }}</td>
                    <td class="text-center discount-text">{{ $discount > 0 ? number_format($discount, 2) : '-' }}</td>
                    <td class="text-center">{{ $item['quantity'] }}</td>
                    <td class="text-left">{{ number_format($item_total_after_discount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" class="border-b-dashed" style="padding: 0; height: 0;"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td class="text-right">الإجمالي الصافي:</td>
            <td class="text-left">{{ number_format($total_price, 2) }}</td>
        </tr>

        @if ($total_discount > 0)
            <tr>
                <td class="text-right discount-text">إجمالي الخصومات:</td>
                <td class="text-left discount-text">- {{ number_format($total_discount, 2) }}</td>
            </tr>
        @endif

        <tr class="grand-total-row">
            <td class="text-right">المجموع الكلي:</td>
            <td class="text-left">{{ number_format($grand_total, 2) }}</td>
        </tr>
    </table>

    <div class="invoice-footer">
        <p>شكرًا لزيارتكم وثقتكم بنا!</p>
    </div>

</body>
</html>
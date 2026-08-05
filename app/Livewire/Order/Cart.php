<?php

namespace App\Livewire\Order;

use Livewire\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Livewire\Attributes\On; 

class Cart extends Component
{
    public $cartItems = [];

    private $currency_symbol;

    public $orderId;

    public function mount($orderId)
    {
        $this->orderId = $orderId;  

        $this->cartItems = OrderItem::where('order_id', $orderId)            
                            ->orderBy('id', 'DESC')
                            ->get();    
        $this->currency_symbol = config('settings.currency_symbol');
    }

    public function render()
    {
        $this->currency_symbol = config('settings.currency_symbol');
        return view('livewire.order.cart', ['cartItems' => $this->cartItems, 'currency_symbol' => $this->currency_symbol]);
    }

    #[On('cartUpdated')]
    public function updateCart()
    {
        $this->recalculateAndValidateOrder();
    }

    #[On('cartUpdatedFromItem')] 
    public function cartUpdatedFromItem()
    {
        $this->recalculateAndValidateOrder();
    }

    /**
     * 🛡️ دالة الحماية وإعادة حساب المجاميع الصارمة لقاعدة البيانات
     */
    private function recalculateAndValidateOrder()
{
    $items = OrderItem::where('order_id', $this->orderId)->orderBy('id', 'DESC')->get();
    
    $total_price = 0;      
    $total_discount = 0;   

    foreach ($items as $item) {
        // 1. جلب المنتج وتأمين التحقق من معرف المنتج الصحيح في الـ OrderItem
        $product = Product::find($item->product_id); 
        
        // 🛡️ إذا لم يجد المنتج في الداتابيز، نضع سعر شراء مرتفع جداً كحماية مؤقتة لمنع الخصم العشوائي
        $purchasePrice = $product ? floatval($product->purchase_price) : floatval($item->price);

        $discount = floatval($item->discount ?? 0);
        $finalPricePerItem = floatval($item->price) - $discount;

        // 🛑 جدار الحماية: إذا أصبح سعر البيع بعد الخصم أقل من سعر الشراء (التكلفة)
        if ($finalPricePerItem < $purchasePrice) {
            
            // ❌ خيار الرفض التام والتصفير: نلغي الخصم تماماً لحماية أرباحك
            $discount = 0.00;
            
            $item->discount = $discount;
            $item->save();

            session()->flash('error', "عذراً! تم رفض الخصم تلقائياً لأن سعر البيع النهائي أقل من سعر شراء المنتج التكليفي ({$purchasePrice} ر.ي).");
        }

        // إعادة الحسابات بناءً على القيمة الآمنة والمعتمدة
        $total_price += (floatval($item->price) - $discount) * $item->quantity;
        $total_discount += ($discount * $item->quantity);
    }

    // 2. تحديث جدول الـ orders بالحقول الصافية والآمنة
    $order = Order::find($this->orderId);
    if ($order) {
        $order->total_price = $total_price;
        $order->discount_price = $total_discount; 
        $order->save();
    }

    $this->cartItems = OrderItem::where('order_id', $this->orderId)->orderBy('id', 'DESC')->get();
}

    public function checkout()
    { 
        return $this->redirect(url('admin/orders'));
    }
}
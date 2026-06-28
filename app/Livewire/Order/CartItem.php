<?php

namespace App\Livewire\Order;

use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\On; 

class CartItem extends Component
{
    public $cartItem;

    public $currency_symbol;

    public $quantity;
    public $discount;

    public $orderId;


    public function mount($cartItem, $orderId)
    {  
        $this->orderId = $orderId;
        $this->cartItem = $cartItem;
        $this->quantity = $cartItem->quantity;
        $this->discount = $cartItem->discount ?? 0;
    }

    #[On('cartUpdated')]
    public function cartUpdated(){
        $this->cartItem->refresh(); // 👈 تحديث كائن الموديل لضمان قراءة البيانات المتزامنة
        $this->quantity = $this->cartItem->quantity;
        $this->discount = $this->cartItem->discount ?? 0;
    }


    public function removeFromCart()
    {   
        $product = Product::find( $this->cartItem->product_id );
        if ($product) {
            $product->quantity = $product->quantity + $this->quantity;
            $product->save();
        }
        
        $this->quantity = 0;
        $this->cartItem->delete();
        $this->dispatch('cartUpdatedFromItem');
    }

    public function updatedDiscount($value)
    {
        $discountValue = floatval($value);
        
        // جلب المنتج للتأكد من سعر الشراء (التكلفة)
        $product = Product::find($this->cartItem->product_id);
        $purchasePrice = $product ? floatval($product->purchase_price) : 0; 

        // حساب سعر البيع الصافي المقترح بعد الخصم
        $finalPricePerItem = floatval($this->cartItem->price) - $discountValue;

        // 🛡️ إذا أصبح السعر النهائي أقل من سعر تكلفة شراء المنتج، نرفض العملية تماماً
        if ($finalPricePerItem < $purchasePrice) {
            
            // ❌ الرفض التام: إلغاء الخصم وإعادته إلى 0 حماية لأرباحك من الخسارة
            $this->discount = 0.00;
            
            session()->flash('error', "عذراً! تم رفض الخصم الممرر تلقائياً لأن سعر البيع النهائي أقل من سعر تكلفة المنتج ({$purchasePrice} ر.ي).");
        } else {
            $this->discount = $discountValue;
        }

        // حفظ القيمة المعتمدة والآمنة في داتابيز عناصر الطلب
        $this->cartItem->discount = $this->discount;
        $this->cartItem->save();

        // 🔄 تحديث الكائن فوراً في الكومبوننت لمنع الـ Blade من تمرير البيانات القديمة
        $this->cartItem->refresh();

        // إشعار السلة الرئيسية لإعادة حساب المجاميع الكلية وحفظ الفاتورة بالصافي الجديد
        $this->dispatch('cartUpdatedFromItem');
    }

    public function updatedQuantity()
    {
        if ($this->quantity > 0) {
            $product = Product::find( $this->cartItem->product_id );
            if ($product) {
                $product->quantity = $product->quantity + $this->cartItem->quantity;

                if( $product->quantity <  $this->quantity ){
                    $this->quantity = $product->quantity;
                }

                $product->save();  

                $this->cartItem->quantity = $this->quantity;
                $this->cartItem->save();

                $product->quantity = $product->quantity - $this->quantity;
                $product->save();
            }
        } 
        if ( is_numeric($this->quantity) && $this->quantity <= 0){
            $this->quantity = 1;
        }
        $this->dispatch('cartUpdatedFromItem');
    }

    public function render()
    {      
        return view('livewire.order.cart-item');
    }
}
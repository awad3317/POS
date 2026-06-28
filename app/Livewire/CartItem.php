<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Component;
use Livewire\Attributes\On; 

class CartItem extends Component
{
    public $cartItem;

    public $currency_symbol;

    public $quantity;
    public $discount;

    public function mount($cartItem)
    {
        $this->cartItem = $cartItem;
        $this->quantity = $cartItem->quantity;
        $this->discount = $cartItem->discount ?? 0;
    }

    #[On('cartUpdated')]
    public function cartUpdated(){
        $this->quantity = $this->cartItem->quantity;
    }

    public function removeFromCart()
    {
        $this->quantity = 0;
        $this->cartItem->delete();
        $this->dispatch('cartUpdatedFromItem');
    }

    public function updated(){
        if ($this->quantity > 0) {  
            $product = Product::find( $this->cartItem->product_id );
            if( $product->quantity <  $this->quantity ){  
                $this->quantity = $product->quantity;
            }
            $this->cartItem->quantity = $this->quantity;
            $this->cartItem->save();
            $this->dispatch('cartUpdatedFromItem');
        } 
    }
    public function updatedDiscount($value)
    {
        $discountValue = floatval($value);
        
        // التحقق من ألا يقل السعر النهائي عن سعر تكلفة شراء المنتج
        // قمنا بجلب الـ purchase_price من علاقة المنتج (Product) المربوط بالـ cartItem
        $product = Product::find($this->cartItem->product_id);
        $purchasePrice = $product ? $product->purchase_price : 0; 

        // حساب سعر البيع الصافي المقترح بعد الخصم
        $finalPricePerItem = $this->cartItem->price - $discountValue;

        if ($finalPricePerItem < $purchasePrice) {
            // إذا تجاوز الحد، نثبّت الخصم على أقصى قيمة مسموحة قانونياً
            $this->discount = max(0, $this->cartItem->price - $purchasePrice);
            
            // إرسال التنبيه ليعرض في الأعلى
            session()->flash('error', "عذراً! لا يمكن تجاوز سعر تكلفة شراء المنتج ({$purchasePrice}).");
        } else {
            $this->discount = $discountValue;
        }

        // حفظ قيمة الخصم المعتمدة في السجل الحالي بالسلة
        $this->cartItem->discount = $this->discount;
        $this->cartItem->save();

        // إشعار السلة الرئيسية لإعادة حساب المجاميع الكلية فوراً
        $this->dispatch('cartUpdatedFromItem');
    }
    public function updatedQuantity()
    {
        if ($this->quantity > 0) {  
            $product = Product::find( $this->cartItem->product_id );
            if( $product && $product->quantity < $this->quantity ){  
                $this->quantity = $product->quantity;
            }
            $this->cartItem->quantity = $this->quantity;
            $this->cartItem->save();
            $this->dispatch('cartUpdatedFromItem');
        } 
    }

    public function render()
    {  
        return view('livewire.cart-item');
    }
}

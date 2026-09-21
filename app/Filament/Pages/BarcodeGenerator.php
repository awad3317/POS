<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BarcodeGenerator extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'منصة طباعة الباركود';
    protected static ?string $title = 'توليد وطباعة الباركود للمنتجات';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.barcode-generator';

    public $product_id = null;
    public $print_qty = 1;
    public $product_name = null;
    public $product_barcode = null;
    public $product_price = null;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('ابحث عن المنتج واختاره هنا:')
                ->placeholder('اكتب اسم المنتج أو الباركود للبحث السريع...')
                // جلب الـ id والاسم فقط بدلاً من تحميل كامل السجلات في الذاكرة
                ->options(Product::query()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live(),
            TextInput::make('print_qty')
                ->label('عدد الكروت المطلوب طباعتها:')
                ->numeric()
                ->minValue(1)
                ->maxValue(500)
                ->default(1)
                ->required()
                ->live(debounce: 300) // تأخير بسيط لتجنب كثرة إعادة الرسم أثناء الكتابة السريعة
        ]);
    }

    public function updatedProductId($value): void
    {
        if ($value) {
            // جلب الأعمدة المطلوبة فقط من قاعدة البيانات
            $product = Product::select(['id', 'name', 'barcode', 'price'])->find($value);
            
            if ($product) {
                $this->product_name = $product->name;
                $this->product_barcode = $product->barcode;
                $this->product_price = number_format((float) $product->price, 2);
                return;
            }
        }
        
        $this->reset(['product_name', 'product_barcode', 'product_price']);
    }
}
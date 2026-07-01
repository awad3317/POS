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

    public $product_id;
    public $print_qty = 1; // القيمة الافتراضية كرت واحد
    public $product_name;
    public $product_barcode;
    public $product_price;

    public function mount()
    {
        $this->product_id = null;
        $this->print_qty = 1;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('ابحث عن المنتج واختاره هنا:')
                ->placeholder('اكتب اسم المنتج أو الباركود للبحث السريع...')
                ->options(Product::all()->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live(),

            TextInput::make('print_qty')
                ->label('عدد الكروت المطلوب طباعتها:')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required()
                ->live() // تحديث التكرار فوراً في المعاينة
        ]);
    }

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            if ($product) {
                $this->product_name = $product->name;
                $this->product_barcode = $product->barcode;
                $this->product_price = number_format($product->price, 2);
                return;
            }
        }
        
        $this->product_name = null;
        $this->product_barcode = null;
        $this->product_price = null;
    }
}
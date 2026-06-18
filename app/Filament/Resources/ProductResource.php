<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextInputColumn;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    // اسم القسم والصفحات بالعربية
    protected static ?string $navigationLabel = 'المنتجات والمخزن';
    protected static ?string $pluralModelLabel = 'المنتجات';
    protected static ?string $modelLabel = 'منتج';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                    TextInput::make('name')
                        ->label('اسم المنتج (الحذاء / الملبس)')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('barcode')
                        ->label('الباركود (Barcode)')
                        ->required()
                        ->unique(Product::class, 'barcode', ignoreRecord: true),
                    TextInput::make('price')
                        ->label('سعر البيع')
                        ->numeric()
                        ->required(),
                    TextInput::make('quantity')
                        ->label('الكمية المتوفرة بالمخزن')
                        ->numeric()
                        ->minValue(0)
                        ->default(1)
                        ->required(),
                    TextInput::make('tax')
                        ->label('الضريبة (%)')
                        ->suffixIcon('heroicon-o-information-circle')  
                        ->helperText('مثال: اكتب 5 لضريبة القيمة المضافة بنسبة 5%.')
                        ->numeric()
                        ->default(0.00),
                    FileUpload::make('image')
                        ->label('صورة المنتج')
                        ->disk('public_uploads') 
                        ->panelLayout('grid') 
                        ->visibility('public'),
                    Toggle::make('status')
                        ->label('حالة المنتج (نشط)')
                        ->default(true)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المنتج')
                    ->width(250)
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('public_uploads')  
                    ->size(50)  
                    ->square(),
                TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable(),
                TextInputColumn::make('quantity')
                    ->label('الكمية')
                    ->type('number')  
                    ->sortable() 
                    ->width(10)
                    ->rules(['required', 'integer', 'min:0']),
                TextColumn::make('price')
                    ->label('السعر')
                    ->sortable(),              
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ])->label('إجراءات جماعية'),
                ExportBulkAction::make()
                    ->label('تصدير للمخزن (Excel)')
                    ->exports([
                        ExcelExport::make()
                            ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                            ->withWriterType(Excel::CSV)
                            ->withColumns([
                                Column::make('name')->heading('اسم المنتج'),
                                Column::make('barcode')->heading('الباركود'),
                                Column::make('price')->heading('السعر'),
                                Column::make('tax')->heading('الضريبة'),
                                Column::make('quantity')->heading('الكمية'),
                            ])
                ])
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            // 'create' => Pages\CreateProduct::route('/create'),
            // 'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
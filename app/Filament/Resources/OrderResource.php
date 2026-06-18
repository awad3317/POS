<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Setting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    // اسم القسم والصفحات بالعربية
    protected static ?string $navigationLabel = 'الطلبات والمبيعات';
    protected static ?string $pluralModelLabel = 'الطلبات';
    protected static ?string $modelLabel = 'طلب';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency_symbol = config('settings.currency_symbol');

        return $table
            ->columns([
                TextColumn::make('id')->label('رقم الطلب (ID)')->sortable(),
                TextColumn::make('customer.first_name')
                    ->label('اسم العميل')
                    ->searchable()
                    ->formatStateUsing(fn ($record) => $record->customer->first_name . ' ' . $record->customer->last_name),
                TextColumn::make('total_price')
                    ->label('إجمالي السعر')
                    ->formatStateUsing(fn ($record) => $currency_symbol.$record->total_price)->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->sortable()
                    ->dateTime(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                // فلتر البحث بالتاريخ
                Filter::make('created_at')
                ->label('تصفية حسب التاريخ')
                ->schema([
                    DatePicker::make('start_date')
                        ->label('من تاريخ'),
                    DatePicker::make('end_date')
                        ->label('إلى تاريخ'),
                ])
                ->query(function ($query, array $data) {
                    return $query
                        ->when($data['start_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['end_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                }) 
                ->indicateUsing(function (array $data) {
                    $indicators = [];
        
                    if (!empty($data['start_date'])) {
                        $indicators[] = 'من تاريخ: ' . $data['start_date'];
                    }
        
                    if (!empty($data['end_date'])) {
                        $indicators[] = 'إلى تاريخ: ' . $data['end_date'];
                    }
        
                    return $indicators;
                }),
            ])
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                    ExportBulkAction::make()
                        ->label('تصدير التقارير المحددة (Excel)')
                        ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                            ->withWriterType(Excel::CSV)
                            ->withColumns([
                                Column::make('customer.phone')->heading('رقم الجوال'),
                                Column::make('customer.email')->heading('البريد الإلكتروني'),
                                Column::make('customer.address')->heading('العنوان'),
                                Column::make('updated_at')->heading('تاريخ التحديث'),
                            ])
                    ])
                ])->label('إجراءات جماعية'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListOrders::route('/'),
            // 'create' => Pages\CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
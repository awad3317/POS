<?php

namespace App\Filament\Resources\ProductResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Throwable;
use Filament\Actions\CreateAction;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use Filament\Notifications\Notification;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. تعريب زر استيراد المنتجات والنافذة الخاصة به
            Action::make('Import Products')
            ->label('استيراد منتجات')
            ->icon('heroicon-o-arrow-up-tray')
            ->schema([
                FileUpload::make('file')
                    ->label('اختر ملف البيانات (CSV)')
                    ->disk('public_uploads')
                    ->directory('imports')
                    ->acceptedFileTypes([
                        'text/csv', 
                        'text/plain',  
                    ])
                    ->required(),
            ])
            ->action(function (array $data) {
    
                try {
                    Excel::import(new ProductsImport, public_path('uploads/'.$data['file']));
                } catch (Throwable $e) {
                    // رسالة تنبيه الفشل بالعربية
                    Notification::make()
                        ->title('فشل الاستيراد!')
                        ->body($e->getMessage())  
                        ->danger()
                        ->send();
                        unlink(public_path('uploads/'.$data['file']));
                    return;
                }
                // رسالة تنبيه النجاح بالعربية
                Notification::make()
                    ->title('تم استيراد المنتجات بنجاح!')
                    ->success()
                    ->send();
                    unlink(public_path('uploads/'.$data['file']));

            }),

            // 2. تعريب زر التصدير وأعمدة ملف التصدير للـ Excel / CSV
            ExportAction::make() 
            ->label('تصدير المخزن')
            ->exports([
                ExcelExport::make()
                    ->withFilename(fn ($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                    ->withWriterType(\Maatwebsite\Excel\Excel::CSV)
                    ->withColumns([
                        Column::make('name')->heading('اسم المنتج'),
                        Column::make('barcode')->heading('الباركود'),
                        Column::make('price')->heading('السعر'),
                        Column::make('quantity')->heading('الكمية'),
                    ])
            ]),

            // 3. تعريب زر إضافة منتج جديد
            CreateAction::make()
                ->label('إضافة منتج جديد')
                ->color('success'),
        ];
    }
}
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;

class Pos extends Page
{
    // أيقونة شاشة البيع
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-plus-circle';

    // مسار ملف الواجهة الـ Blade الخاص بنقطة البيع
    protected string $view = 'filament.pages.pos';

    // اسم الصفحة في القائمة الجانبية بالعربية
    protected static ?string $navigationLabel = 'شاشة البيع (POS)';

    // عنوان الصفحة الرئيسي عند الدخول إليها
    public function getTitle(): string
    {
        return 'نقطة البيع';  
    }
}
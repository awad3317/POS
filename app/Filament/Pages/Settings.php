<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Pages\Page;
use App\Models\Setting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class Settings extends Page
{
    // أيقونة الترس للإعدادات
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings';

    // اسم الصفحة في القائمة الجانبية بالعربية
    protected static ?string $navigationLabel = 'الإعدادات العامة';

    // عنوان الصفحة الرئيسي في الأعلى
    public function getTitle(): string
    {
        return 'إعدادات النظام';
    }

    public array $settings = [];

    public function mount(): void
    { 
        $this->settings = Setting::pluck('value', 'key')->toArray();
        $this->settings['currency_symbol'] = $this->settings['currency_symbol'] ?? '$';
    }

    public function save(): void
    {
        foreach ($this->settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // رسالة التنبيه بنجاح الحفظ بالعربية
        session()->flash('success', 'تم تحديث الإعدادات بنجاح!');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('settings.site_name')
                ->label('اسم المتجر / المحل')
                ->required(),
            TextInput::make('settings.site_email')
                ->label('البريد الإلكتروني للمحل')
                ->email(),
            Textarea::make('settings.site_description')
                ->label('وصف المحل (يظهر في التقارير أحياناً)')
                ->rows(3),
            TextInput::make('settings.currency_symbol')
                ->default('$')
                ->label('رمز العملة'),
        ]);
    }
}
<?php 

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;


class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // اسم القسم في القائمة الجانبية بالعربية
    protected static ?string $navigationLabel = 'العملاء';
    
    // عنوان الصفحة عند الدخول إليها
    protected static ?string $pluralModelLabel = 'العملاء';
    protected static ?string $modelLabel = 'عميل';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('الاسم الأول')
                    ->required()
                    ->maxLength(20),
                TextInput::make('last_name')
                    ->label('الاسم الأخير')
                    ->maxLength(20),
                TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->nullable(),
                TextInput::make('phone')
                    ->label('رقم الهاتف')
                    ->tel()
                    ->nullable(),
                Textarea::make('address')
                    ->label('العنوان')
                    ->nullable(),
                FileUpload::make('avatar')
                    ->label('الصورة الشخصية')
                    ->image()
                    ->visibility('public')
                    ->disk('public_uploads')
                    ->directory('avatars')
                    ->nullable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('الاسم الأول')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('الاسم الأخير')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->searchable(),
                TextColumn::make('orders_count')
                    ->label('عدد الطلبات')
                    ->counts('orders')  
                    ->sortable(),                
                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->sortable()
                    ->dateTime(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()->label('تعديل'),
                DeleteAction::make()->label('حذف'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()->label('حذف المحدد'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            // 'create' => Pages\CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
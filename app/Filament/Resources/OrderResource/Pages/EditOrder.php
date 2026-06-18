<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.pages.edit-order';


    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة')
                ->livewireClickHandlerEnabled(false)
                ->extraAttributes(fn(Order $record) => [
                    'class' => 'md:flex hidden',
                    'x-on:click' => new HtmlString("printJS({ printable:'" . url('print/'.$record->id) . "', type: 'pdf' })")
                ])
                ->icon('heroicon-o-printer')
                ->color('success'),
            Action::make('print')
                ->label('معاينة')
                ->url(fn($record)=> "/print/" . $record->id, shouldOpenInNewTab:true)
                ->icon('heroicon-o-document-text')
                ->color('success'),
            DeleteAction::make()
        ];
    }
}

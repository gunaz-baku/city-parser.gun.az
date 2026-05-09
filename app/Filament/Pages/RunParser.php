<?php

namespace App\Filament\Pages;

use App\Jobs\StartParserJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class RunParser extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlay;

    protected static string|\UnitEnum|null $navigationGroup = 'Parser';

    protected static ?int $navigationSort = -10;

    protected static ?string $navigationLabel = 'Run parser';

    protected static ?string $title = 'Run parser';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'parser_type' => 'wolt',
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parser_type')
                    ->label('Parser type')
                    ->options([
                        'wolt' => 'Wolt',
                        'bina' => 'Bina',
                    ])
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('run-parser-form')
                    ->livewireSubmitHandler('queueParser')
                    ->footer([
                        Actions::make([
                            Action::make('queueParser')
                                ->label('Queue parser run')
                                ->submit('queueParser'),
                        ])
                            ->alignment(static::$formActionsAlignment),
                    ]),
            ]);
    }

    public function queueParser(): void
    {
        $data = $this->form->getState();

        $type = $data['parser_type'] ?? null;

        if (! in_array($type, ['wolt', 'bina'], true)) {
            Notification::make()
                ->title('Invalid parser type')
                ->danger()
                ->send();

            return;
        }

        StartParserJob::dispatch($type, 'manual');

        Notification::make()
            ->title('Parser run queued')
            ->body('The job was sent to the queue. Watch progress under Parser runs.')
            ->success()
            ->send();
    }
}

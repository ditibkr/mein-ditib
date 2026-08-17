<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\NewsletterResource\Pages;
use App\Jobs\SendNewsletterJob;
use App\Models\Newsletter;
use App\Services\NewsletterService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Kommunikation';

    protected static ?string $modelLabel = 'Newsletter';

    protected static ?string $pluralModelLabel = 'Newsletter';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Newsletter')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Interner Titel')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'entwurf' => 'Entwurf',
                            'bereit' => 'Bereit zum Versand',
                            'geplant' => 'Geplant',
                            'versendet' => 'Versendet',
                        ])
                        ->default('entwurf')
                        ->disabled(fn ($record) => $record?->isSent()),
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label('Geplant für')
                        ->visible(fn ($get) => $get('status') === 'geplant'),
                ])->columns(2),

            Forms\Components\Tabs::make('Inhalt')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Deutsch')
                        ->schema([
                            Forms\Components\TextInput::make('subject_de')
                                ->label('Betreff (DE)')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\RichEditor::make('body_de')
                                ->label('Inhalt (DE)')
                                ->required()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline',
                                    'bulletList', 'orderedList',
                                    'link', 'h2', 'h3',
                                ]),
                        ]),
                    Forms\Components\Tabs\Tab::make('Türkisch')
                        ->schema([
                            Forms\Components\TextInput::make('subject_tr')
                                ->label('Betreff (TR)')
                                ->maxLength(255),
                            Forms\Components\RichEditor::make('body_tr')
                                ->label('Inhalt (TR)')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline',
                                    'bulletList', 'orderedList',
                                    'link', 'h2', 'h3',
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'entwurf',
                        'warning' => ['bereit', 'geplant', 'wird_versendet'],
                        'success' => 'versendet',
                        'danger' => 'fehlgeschlagen',
                    ]),
                Tables\Columns\TextColumn::make('recipient_count')
                    ->label('Empfänger')
                    ->numeric(),
                Tables\Columns\TextColumn::make('sent_count')
                    ->label('Versendet')
                    ->numeric(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Versendet am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'entwurf' => 'Entwurf',
                        'versendet' => 'Versendet',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('prepare')
                    ->label('Empfänger laden')
                    ->icon('heroicon-o-users')
                    ->visible(fn (Newsletter $record) => $record->isDraft())
                    ->action(function (Newsletter $record) {
                        $count = app(NewsletterService::class)->prepare($record);
                        Notification::make()
                            ->title("{$count} Empfänger geladen")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('send')
                    ->label('Jetzt senden')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Newsletter $record) => $record->status === 'bereit')
                    ->action(function (Newsletter $record) {
                        SendNewsletterJob::dispatch($record);
                        Notification::make()
                            ->title('Versand wird gestartet...')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
        ];
    }
}

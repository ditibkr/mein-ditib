<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Mitglieder';

    protected static ?string $modelLabel = 'Mitglied';

    protected static ?string $pluralModelLabel = 'Mitglieder';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Stammdaten')
                ->schema([
                    Forms\Components\TextInput::make('member_number')
                        ->label('Mitgliedsnummer')
                        ->disabled()
                        ->placeholder('Wird automatisch generiert'),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'aktiv' => 'Aktiv',
                            'ruhend' => 'Ruhend',
                            'ausgetreten' => 'Ausgetreten',
                            'ausgeschlossen' => 'Ausgeschlossen',
                        ])
                        ->default('aktiv')
                        ->required(),
                    Forms\Components\TextInput::make('first_name')
                        ->label('Vorname')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('last_name')
                        ->label('Nachname')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Select::make('gender')
                        ->label('Geschlecht')
                        ->options([
                            'maennlich' => 'Männlich',
                            'weiblich' => 'Weiblich',
                            'divers' => 'Divers',
                        ]),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Geburtsdatum')
                        ->displayFormat('d.m.Y'),
                    Forms\Components\TextInput::make('birth_place')
                        ->label('Geburtsort')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('nationality')
                        ->label('Staatsangehörigkeit')
                        ->maxLength(50),
                ])->columns(2),

            Forms\Components\Section::make('Kontakt')
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('E-Mail')
                        ->email()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('mobile')
                        ->label('Mobil')
                        ->tel()
                        ->maxLength(50),
                ])->columns(3),

            Forms\Components\Section::make('Adresse')
                ->schema([
                    Forms\Components\TextInput::make('street')
                        ->label('Straße')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('house_number')
                        ->label('Hausnummer')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('zip_code')
                        ->label('PLZ')
                        ->maxLength(10),
                    Forms\Components\TextInput::make('city')
                        ->label('Ort')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('country')
                        ->label('Land')
                        ->default('Deutschland')
                        ->maxLength(50),
                ])->columns(2),

            Forms\Components\Section::make('Mitgliedschaft')
                ->schema([
                    Forms\Components\Select::make('category')
                        ->label('Kategorie')
                        ->options([
                            'vollmitglied' => 'Vollmitglied',
                            'foerdermitglied' => 'Fördermitglied',
                            'ehrenmitglied' => 'Ehrenmitglied',
                            'jugend' => 'Jugend',
                        ])
                        ->default('vollmitglied')
                        ->required(),
                    Forms\Components\DatePicker::make('membership_start')
                        ->label('Eintrittsdatum')
                        ->displayFormat('d.m.Y'),
                    Forms\Components\DatePicker::make('membership_end')
                        ->label('Austrittsdatum')
                        ->displayFormat('d.m.Y'),
                    Forms\Components\TextInput::make('membership_fee')
                        ->label('Beitrag (€)')
                        ->numeric()
                        ->default(0)
                        ->step(0.01),
                    Forms\Components\Select::make('fee_interval')
                        ->label('Zahlungsintervall')
                        ->options([
                            'monatlich' => 'Monatlich',
                            'quartal' => 'Quartalsweise',
                            'halbjaehrlich' => 'Halbjährlich',
                            'jaehrlich' => 'Jährlich',
                        ])
                        ->default('monatlich'),
                    Forms\Components\Select::make('language_preference')
                        ->label('Sprache')
                        ->options(['de' => 'Deutsch', 'tr' => 'Türkisch'])
                        ->default('de'),
                ])->columns(2),

            Forms\Components\Section::make('DSGVO & Notizen')
                ->schema([
                    Forms\Components\Toggle::make('gdpr_consent')
                        ->label('DSGVO-Einwilligung erteilt'),
                    Forms\Components\Textarea::make('notes')
                        ->label('Notizen')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_number')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name'])
                    ->getStateUsing(fn (Member $record) => $record->last_name . ', ' . $record->first_name),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ort')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'aktiv',
                        'warning' => 'ruhend',
                        'danger' => ['ausgetreten', 'ausgeschlossen'],
                    ]),
                Tables\Columns\BadgeColumn::make('category')
                    ->label('Kategorie'),
                Tables\Columns\TextColumn::make('membership_start')
                    ->label('Eintrittsdatum')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'aktiv' => 'Aktiv',
                        'ruhend' => 'Ruhend',
                        'ausgetreten' => 'Ausgetreten',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategorie')
                    ->options([
                        'vollmitglied' => 'Vollmitglied',
                        'foerdermitglied' => 'Fördermitglied',
                        'ehrenmitglied' => 'Ehrenmitglied',
                        'jugend' => 'Jugend',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'view' => Pages\ViewMember::route('/{record}'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}

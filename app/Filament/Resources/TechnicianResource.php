<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechnicianResource\Pages;
use App\Filament\Resources\TechnicianResource\RelationManagers;
use App\Models\Technician;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Textarea;

class TechnicianResource extends Resource
{
    protected static ?string $model = Technician::class;

    protected static ?string $navigationIcon = 'heroicon-m-bolt';

    protected static ?string $navigationGroup = 'Purchase Service Management';

    protected static ?int $navigationSort = 18;

    public static function getModelLabel(): string
    {
        return 'Technician';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Technicians';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Technician Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Technician Code')
                            ->default(fn () => Technician::generateCode())
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Full Name')
                            ->placeholder('Enter Full Name')
                            ->hint('Example: James Curry')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->label('Phone')
                            ->placeholder('Enter Phone Number')
                            ->hint('Example: 08123456789')
                            ->maxLength(15),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->label('Email Address')
                            ->placeholder('Enter Email Address')
                            ->hint('Example: james@example.com')
                            ->maxLength(255),
                        TextArea::make('address')
                            ->label('Address')
                            ->placeholder('Enter Address')
                            ->hint('Example: 123 Main St')
                            ->rows(3)
                            ->columnSpanFull()
                            ->required()
                            ->maxLength(255),
                    ]),
                Forms\Components\Section::make('Purchase Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Service Fee')
                            ->required()
                            ->numeric()
                            ->columnSpanFull()
                            ->placeholder('Enter Service Fee')
                            ->hint('Example: 10000')
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('piutang')
                            ->label('Receivables')
                            ->hidden(fn (string $context) => $context === 'create')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('total_po')
                            ->label('Total Purchase Order')
                            ->numeric()
                            ->prefix('Rp')
                            ->hidden(fn (string $context) => $context === 'create')
                            ->required()
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Technician Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Service Fee')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('piutang')
                    ->label('Receivables')
                    ->color(fn ($state) => $state <= 0 ? 'success' : 'danger')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('total_po')
                    ->label('Total PO')
                    ->formatStateUsing(function ($state) {
                        return 'Rp ' . number_format($state, 0, ', ', '.');
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListTechnicians::route('/'),
            'create' => Pages\CreateTechnician::route('/create'),
            'view' => Pages\ViewTechnician::route('/{record}'),
            'edit' => Pages\EditTechnician::route('/{record}/edit'),
        ];
    }
}

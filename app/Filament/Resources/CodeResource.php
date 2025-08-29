<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CodeResource\Pages;
use App\Filament\Resources\CodeResource\RelationManagers;
use App\Models\Code;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Textarea;

class CodeResource extends Resource
{
    protected static ?string $model = Code::class;

     protected static ?string $navigationIcon = 'heroicon-c-qr-code';

    protected static ?string $navigationGroup = 'Stock Management';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'Code';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Codes';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Code Form')
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->placeholder('Enter Code')
                            ->unique(ignoreRecord: true)
                            ->hint('Example: SYR')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Category')
                            ->label('Category')
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->placeholder('Select Type')
                            ->options([
                                'Product' => 'Product',
                                'Service' => 'Service',
                            ])
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextArea::make('description')
                            ->columnSpanFull()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCodes::route('/'),
            'create' => Pages\CreateCode::route('/create'),
            'view' => Pages\ViewCode::route('/{record}'),
            'edit' => Pages\EditCode::route('/{record}/edit'),
        ];
    }
}

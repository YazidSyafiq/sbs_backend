<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-s-user-circle';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Profile Form')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('Full Name')
                            ->placeholder('Enter Full Name')
                            ->hint('Example: James Curry')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('telepon')
                            ->tel()
                            ->required()
                            ->label('Phone')
                            ->placeholder('Enter Phone Number')
                            ->hint('Example: 08123456789')
                            ->maxLength(15),
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Upload Photo')
                            ->maxSize(3072)
                            ->disk('public')
                            ->columnSpanFull()
                            ->directory('profile')
                            ->image(),
                    ]),
                Forms\Components\Section::make('Account Form')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->label('Email Address')
                            ->placeholder('Enter Email Address')
                            ->hint('Example: james@example.com')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->label('Password')
                            ->placeholder('Enter Password')
                            ->hint('Example: !234sG7B')
                            ->revealable(fn (string $context): bool => $context !== 'view')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->maxLength(255),
                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->placeholder('Select Role')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->live()
                            ->disabled(fn () => Auth::user()->hasRole('User')),
                        Forms\Components\Select::make('branch_id')
                            ->relationship('branch', 'name')
                            ->placeholder('Select Branch')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->disabled(fn () => Auth::user()->hasRole('User'))
                            ->hidden(function (Get $get) {
                                $selectedRoles = $get('roles');

                                if (empty($selectedRoles)) {
                                    return true;
                                }

                                $userRole = Role::where('name', 'User')->first();

                                if (is_array($selectedRoles)) {
                                    return !in_array($userRole->id, $selectedRoles);
                                }

                                return $selectedRoles != $userRole->id;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $is_user = Auth::user()->hasRole('User');

                if ($is_user) {
                    $query->where('id', Auth::user()->id);
                }
            })
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Photo')
                    ->circular()
                    ->getStateUsing(fn ($record) => $record->image_url ?? url('default/images/default_images.jpg')),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telepon')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        if ($record->branch_id && $record->branch) {
                            return $record->branch->name;
                        }
                        return 'Central';
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

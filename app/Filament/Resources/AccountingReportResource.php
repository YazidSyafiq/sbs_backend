<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountingReportResource\Pages;
use App\Models\AccountingReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;

class AccountingReportResource extends Resource
{
    protected static ?string $model = AccountingReport::class;

    protected static ?string $navigationIcon = 'heroicon-c-calculator';

    protected static ?string $navigationGroup = 'Accounting and Report';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return 'Accounting Report';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Accounting Reports';
    }

    // Disable table completely
    public static function table(Table $table): Table
    {
        return $table->paginated(false); // Empty query
    }

    // Disable CRUD operations
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
    public static function canView($record): bool { return false; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountingReports::route('/'),
        ];
    }
}

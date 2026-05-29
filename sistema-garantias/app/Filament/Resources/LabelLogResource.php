<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabelLogResource\Pages;
use App\Models\LabelLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LabelLogResource extends Resource
{
    protected static ?string $model = LabelLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Bitácora de etiquetas';
    protected static ?string $modelLabel = 'Registro';
    protected static ?string $pluralModelLabel = 'Bitácora de etiquetas';
    protected static string|\UnitEnum|null $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', LabelLog::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label.serial')
                    ->label('Serial')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('labelBatch.internal_batch_code')
                    ->label('Lote')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Acción')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Acción')
                    ->options(fn(): array => LabelLog::distinct()->pluck('action', 'action')->toArray()),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name'),
            ])
            ->actions([])
            ->bulkActions([])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelLogs::route('/'),
        ];
    }
}

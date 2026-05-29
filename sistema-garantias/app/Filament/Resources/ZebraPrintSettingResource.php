<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ZebraPrintSettingResource\Pages;
use App\Models\ZebraPrintSetting;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ZebraPrintSettingResource extends Resource
{
    protected static ?string $model = ZebraPrintSetting::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationLabel = 'Configuración Zebra';
    protected static ?string $modelLabel = 'Configuración Zebra';
    protected static ?string $pluralModelLabel = 'Configuraciones Zebra';
    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', ZebraPrintSetting::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos de la impresora')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre de la configuración')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('printer_model')
                            ->label('Modelo de impresora')
                            ->default('Zebra ZT411')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Select::make('dpi')
                            ->label('DPI')
                            ->options([
                                203 => '203 DPI',
                                300 => '300 DPI',
                                600 => '600 DPI',
                            ])
                            ->default(203)
                            ->required(),

                        Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->default(true),
                    ])->columns(2),

                Section::make('Tamaño de etiqueta')
                    ->schema([
                        Forms\Components\TextInput::make('label_width_mm')
                            ->label('Ancho (mm)')
                            ->numeric()
                            ->required()
                            ->helperText('Ejemplo: 100'),

                        Forms\Components\TextInput::make('label_height_mm')
                            ->label('Alto (mm)')
                            ->numeric()
                            ->required()
                            ->helperText('Ejemplo: 350'),

                        Forms\Components\TextInput::make('label_gap_mm')
                            ->label('Separación entre etiquetas (mm)')
                            ->numeric()
                            ->nullable()
                            ->helperText('Ejemplo: 3'),

                        Forms\Components\TextInput::make('width_dots')
                            ->label('Ancho en puntos')
                            ->numeric()
                            ->required()
                            ->helperText('203 DPI: mm x 8'),

                        Forms\Components\TextInput::make('height_dots')
                            ->label('Alto en puntos')
                            ->numeric()
                            ->required()
                            ->helperText('203 DPI: mm x 8'),
                    ])->columns(2),

                Section::make('Ajustes de impresión')
                    ->schema([
                        Forms\Components\TextInput::make('margin_x')
                            ->label('Margen X')
                            ->numeric()
                            ->default(20)
                            ->required(),

                        Forms\Components\TextInput::make('margin_y')
                            ->label('Margen Y')
                            ->numeric()
                            ->default(20)
                            ->required(),

                        Forms\Components\TextInput::make('qr_size')
                            ->label('Tamaño QR')
                            ->numeric()
                            ->default(6)
                            ->required()
                            ->helperText('Valor entre 1 y 10'),

                        Forms\Components\TextInput::make('barcode_height')
                            ->label('Altura código de barras')
                            ->numeric()
                            ->default(120)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('printer_model')
                    ->label('Impresora')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('dpi')
                    ->label('DPI')
                    ->suffix(' DPI')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('label_width_mm')
                    ->label('Ancho')
                    ->suffix(' mm'),

                Tables\Columns\TextColumn::make('label_height_mm')
                    ->label('Alto')
                    ->suffix(' mm'),

                Tables\Columns\TextColumn::make('qr_size')
                    ->label('QR')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(ZebraPrintSetting $record): bool => Auth::user()?->can('update', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListZebraPrintSettings::route('/'),
            'create' => Pages\CreateZebraPrintSetting::route('/create'),
            'edit'   => Pages\EditZebraPrintSetting::route('/{record}/edit'),
        ];
    }
}

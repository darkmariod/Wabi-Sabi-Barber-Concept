<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabelBatchResource\Pages;
use App\Models\LabelBatch;
use App\Models\LabelLog;
use App\Models\Product;
use App\Models\User;
use App\Services\LabelPdfService;
use App\Services\SerialGeneratorService;
use App\Services\ZebraZplService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LabelBatchResource extends Resource
{
    protected static ?string $model = LabelBatch::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Lotes de etiquetas';
    protected static ?string $modelLabel = 'Lote';
    protected static ?string $pluralModelLabel = 'Lotes de etiquetas';
    protected static string|\UnitEnum|null $navigationGroup = 'Etiquetas';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', LabelBatch::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Datos del lote')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('internal_batch_code')
                            ->label('Código interno del lote')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_batch_number')
                            ->label('Número de lote del cliente')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('customer_batch_date')
                            ->label('Fecha de lote del cliente')
                            ->nullable(),
                    ])->columns(2),

                Section::make('Detalles de generación')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\TextInput::make('operator')
                            ->label('Operador')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\Select::make('generated_by_user_id')
                            ->label('Generado por')
                            ->relationship('generatedBy', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\DateTimePicker::make('generated_at')
                            ->label('Fecha de generación'),
                    ])->columns(2),

                Section::make('Series')
                    ->schema([
                        Forms\Components\TextInput::make('serial_from')
                            ->label('Serial inicial')
                            ->nullable()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('serial_to')
                            ->label('Serial final')
                            ->nullable()
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Estado y observaciones')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active'    => 'Activo',
                                'anulled'   => 'Anulado',
                                'generated' => 'Generado',
                            ])
                            ->default('active')
                            ->required(),

                        Forms\Components\Textarea::make('observations')
                            ->label('Observaciones')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('internal_batch_code')
                    ->label('Código interno')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('operator')
                    ->label('Operador')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_from')
                    ->label('Serial inicial')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_to')
                    ->label('Serial final')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active'   => 'success',
                        'anulled'  => 'danger',
                        'generated' => 'warning',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active'   => 'Activo',
                        'anulled'  => 'Anulado',
                        'generated' => 'Generado',
                        default    => $state,
                    }),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active'   => 'Activo',
                        'anulled'  => 'Anulado',
                        'generated' => 'Generado',
                    ]),
                Tables\Filters\TernaryFilter::make('trashed')
                    ->label('Eliminados'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('update', $record) ?? false),

                Action::make('generar')
                    ->label('Generar etiquetas')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn(LabelBatch $record): bool =>
                        ($record->status === 'active' || $record->status === 'generated')
                        && (Auth::user()?->can('generateLabels', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $service = app(SerialGeneratorService::class);
                        $result = $service->generateLabelsForBatch($record);

                        if (!$result) {
                            Notification::make()
                                ->title('El lote ya tiene etiquetas generadas')
                                ->body('Este lote ya fue generado anteriormente')
                                ->danger()
                                ->seconds(5)
                                ->send();
                            return;
                        }

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'generated',
                            'description'    => 'Etiquetas generadas para lote ' . $record->internal_batch_code,
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        $count = $record->fresh()->labels()->count();

                        Notification::make()
                            ->title('Etiquetas generadas')
                            ->body("Se generaron {$count} etiquetas para el lote {$record->internal_batch_code}")
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                Action::make('descargar_zpl')
                    ->label('Descargar ZPL')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadZpl', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $service = app(ZebraZplService::class);
                        $zpl = $service->generateForBatch($record);
                        $filename = $service->getFilenameForBatch($record);

                        Notification::make()
                            ->title('Descargando ZPL')
                            ->body("Archivo ZPL preparado para el lote {$record->internal_batch_code}")
                            ->info()
                            ->seconds(5)
                            ->send();

                        return response()->streamDownload(function () use ($zpl) {
                            echo $zpl;
                        }, $filename);
                    }),

                Action::make('descargar_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->visible(fn(LabelBatch $record): bool =>
                        $record->status === 'generated'
                        && (Auth::user()?->can('downloadPdf', $record) ?? false)
                    )
                    ->action(function (LabelBatch $record) {
                        $service = app(LabelPdfService::class);
                        $pdf = $service->generateForBatch($record);
                        $filename = $service->getFilenameForBatch($record);

                        Notification::make()
                            ->title('Descargando PDF')
                            ->body("Archivo PDF preparado para el lote {$record->internal_batch_code}")
                            ->info()
                            ->seconds(5)
                            ->send();

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf;
                        }, $filename);
                    }),

                Action::make('anular')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('annul', $record) ?? false)
                    ->action(function (LabelBatch $record) {
                        $oldStatus = $record->status;
                        $record->update(['status' => 'anulled']);

                        if ($record->labels()->count() > 0) {
                            $record->labels()->update(['status' => 'anulled']);
                        }

                        LabelLog::create([
                            'label_batch_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'anulled',
                            'description'    => 'Lote anulado: ' . $record->internal_batch_code,
                            'old_data'       => ['status' => $oldStatus],
                            'new_data'       => ['status' => 'anulled'],
                            'ip'             => request()->ip(),
                            'created_at'     => now(),
                        ]);

                        Notification::make()
                            ->title('Lote anulado exitosamente')
                            ->success()
                            ->seconds(5)
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn(LabelBatch $record): bool => Auth::user()?->can('delete', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLabelBatches::route('/'),
            'create' => Pages\CreateLabelBatch::route('/create'),
            'edit'   => Pages\EditLabelBatch::route('/{record}/edit'),
        ];
    }
}

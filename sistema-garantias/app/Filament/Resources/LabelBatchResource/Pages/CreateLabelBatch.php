<?php

namespace App\Filament\Resources\LabelBatchResource\Pages;

use App\Filament\Resources\LabelBatchResource;
use App\Models\LabelLog;
use App\Services\SerialGeneratorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLabelBatch extends CreateRecord
{
    protected static string $resource = LabelBatchResource::class;

    protected function getCreateFormAction(): \Filament\Actions\Action
    {
        return parent::getCreateFormAction()->label('Crear');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $batch = $this->record;

        $service = app(SerialGeneratorService::class);
        $result = $service->generateLabelsForBatch($batch);

        if ($result) {
            LabelLog::create([
                'label_batch_id' => $batch->id,
                'user_id'        => auth()->id(),
                'action'         => 'generated',
                'description'    => 'Etiquetas generadas automáticamente para lote ' . $batch->internal_batch_code,
                'ip'             => request()->ip(),
                'created_at'     => now(),
            ]);
        }
    }
}

<?php

namespace App\Actions\Sanitization;

use App\Models\ClientAudit;
use App\Models\ReferenceClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreClientAuditAction
{
    /**
     * @param int|null $userId
     * @param array $data
     * @param UploadedFile[] $photos
     * @return ClientAudit
     */
    public function execute(?int $userId, array $data, array $photos = []): ClientAudit
    {
        return DB::transaction(function () use ($userId, $data, $photos) {
            $storedPaths = [];

            foreach ($photos as $photo) {
                // Guarda en storage/app/public/audits/{client_id}/
                $path = $photo->store("audits/{$data['client_id']}", 'public');
                $storedPaths[] = $path;
            }

            $audit = ClientAudit::create([
                'user_id' => $userId,
                'client_id' => $data['client_id'],
                'audit_status' => $data['audit_status'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy' => $data['accuracy'] ?? 0.0,
                'comments' => $data['comments'] ?? null,
                'photo_paths' => $storedPaths,
                'audited_at' => $data['audited_at'],
            ]);

            // Marcar el cliente como auditado en la base maestra
            ReferenceClient::where('id_cliente', $data['client_id'])
                ->update(['is_audited' => true]);

            return $audit;
        });
    }
}
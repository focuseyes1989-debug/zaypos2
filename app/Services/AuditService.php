<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use JsonException;

final class AuditService
{
    /** @param array<string, mixed> $metadata */
    public function record(string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
    {
        try {
            $metadataJson = $metadata === []
                ? null
                : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $metadataJson = null;
        }

        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs
                (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at)
             VALUES
                (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'metadata' => $metadataJson,
        ]);
    }
}

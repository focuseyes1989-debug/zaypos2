<?php

declare(strict_types=1);

use PDO;

return [
    'name' => '202608070003_harden_authentication',
    'up' => static function (PDO $database): void {
        $database->exec(
            "ALTER TABLE users
             ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at"
        );
        $database->exec(
            "ALTER TABLE user_sessions
             ADD COLUMN absolute_expires_at DATETIME NULL AFTER expires_at,
             ADD COLUMN revoked_at DATETIME NULL AFTER absolute_expires_at,
             ADD COLUMN user_agent_hash CHAR(64) NULL AFTER user_agent"
        );
        $database->exec(
            "UPDATE user_sessions
             SET absolute_expires_at = COALESCE(absolute_expires_at, expires_at),
                 user_agent_hash = COALESCE(user_agent_hash, SHA2(COALESCE(user_agent, ''), 256))"
        );
        $database->exec(
            "ALTER TABLE user_sessions
             ADD INDEX idx_user_sessions_validity
             (user_id, revoked_at, expires_at, absolute_expires_at)"
        );
    },
];

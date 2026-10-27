<?php

declare(strict_types=1);

/** A2-08 local evidence helper: prints the consumer audit-event row count. */

$db = new PDO('sqlite:' . __DIR__ . '/app/database/database.sqlite');
echo (int) $db->query('select count(*) from surfacerelay_audit_events')->fetchColumn();

<?php
declare(strict_types=1);

/** Одна транзакция: журнал с триггером -> проверка -> изменение команды. */
function userOperation(int $userId, string $uid, string $action, ?int $command = null): ?array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $d = query('SELECT id FROM devices WHERE uid=? FOR UPDATE', [$uid])->fetch();
        if (!$d || !query('SELECT role FROM device_access WHERE user_id=? AND device_id=?',
                [$userId, $d['id']])->fetch()) {
            $pdo->rollBack(); return null;
        }
        query('INSERT INTO user_events (user_id,device_id,action,command) VALUES (?,?,?,?)',
            [$userId, $d['id'], $action, $command]);
        $event = query('SELECT outcome FROM user_events WHERE id=?', [$pdo->lastInsertId()])->fetch();
        if ($event['outcome'] !== 'allowed') {
            // COMMIT сохраняет и событие, и блокировку. Здесь нельзя rollback/SIGNAL.
            $pdo->commit();
            return ['outcome' => $event['outcome']];
        }
        if ($action === 'control') {
            query('UPDATE device_state SET command=?,command_at=UTC_TIMESTAMP(6) WHERE device_id=?',
                [$command, $d['id']]);
        }
        $state = query('SELECT * FROM v_device_dashboard WHERE user_id=? AND id=?',
            [$userId, $d['id']])->fetch();
        $history = [];
        if ($action === 'history') {
            $history = query('SELECT e.happened_at,u.username,e.action,e.command,e.outcome
                FROM user_events e JOIN users u ON u.id=e.user_id
                WHERE e.device_id=? ORDER BY e.id DESC LIMIT 100', [$d['id']])->fetchAll();
        }
        $pdo->commit();
        return ['outcome' => 'allowed', 'state' => $state, 'history' => $history];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function deviceOperation(string $uid, string $token, array $data): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $d = query('SELECT id,token_hash FROM devices WHERE uid=? FOR UPDATE', [$uid])->fetch();
        if (!$d || !hash_equals($d['token_hash'], hash('sha256', $token))) {
            $pdo->rollBack(); return ['code' => 401, 'text' => 'Неверные реквизиты устройства'];
        }
        query('INSERT INTO device_events (device_id) VALUES (?)', [$d['id']]);
        $event = query('SELECT outcome FROM device_events WHERE id=?', [$pdo->lastInsertId()])->fetch();
        if ($event['outcome'] !== 'allowed') {
            $pdo->commit(); return ['code' => 429, 'text' => 'Устройство временно заблокировано'];
        }
        if (array_key_exists('Term', $data)) {
            query('UPDATE device_state SET temperature=?,temperature_at=UTC_TIMESTAMP(6) WHERE device_id=?',
                [$data['Term'] === null ? null : $data['Term'] / 10, $d['id']]);
        }
        if (array_key_exists('Rele', $data)) {
            query('UPDATE device_state SET relay=?,relay_at=UTC_TIMESTAMP(6) WHERE device_id=?',
                [$data['Rele'], $d['id']]);
        }
        query('UPDATE devices SET last_seen=UTC_TIMESTAMP(6) WHERE id=?', [$d['id']]);
        $state = query('SELECT command FROM device_state WHERE device_id=?', [$d['id']])->fetch();
        $pdo->commit();
        return ['code' => 200, 'text' => 'COMMAND ' . ($state['command'] ?? '?') . ' EOC'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

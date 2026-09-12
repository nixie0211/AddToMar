<?php

declare(strict_types=1);

require_once __DIR__ . '/pharmacy-accounts.php';
require_once __DIR__ . '/database.php';

function pharmacy_reports_storage_path(): string
{
    return dirname(__DIR__) . '/data/pharmacy-reports.json';
}

function pharmacy_reports_load_all(): array
{
    try {
        $pdo = caps_db();
        $rows = $pdo->query('SELECT * FROM pharmacy_reports ORDER BY created_at DESC')->fetchAll();
        if ($rows !== []) {
            return pharmacy_reports_normalize($rows);
        }

        // One-time compatibility migration for reports created before the table existed.
        $legacy = pharmacy_reports_load_legacy();
        if ($legacy !== []) {
            pharmacy_reports_save_database($legacy, $pdo);
            return pharmacy_reports_normalize($legacy);
        }
    } catch (Throwable) {
        // Keep the application usable if MySQL is temporarily unavailable.
    }

    return pharmacy_reports_normalize(pharmacy_reports_load_legacy());
}

function pharmacy_reports_load_legacy(): array
{
    $path = pharmacy_reports_storage_path();

    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    return $data;
}

function pharmacy_reports_normalize(array $reports): array
{
    $changed = false;
    foreach ($reports as $index => $report) {
        if (($report['status'] ?? '') === 'open') {
            $reports[$index]['status'] = 'under_review';
            $changed = true;
        }
    }

    if ($changed) {
        try {
            pharmacy_reports_save_all($reports);
        } catch (Throwable) {
        }
    }

    return array_values($reports);
}

function pharmacy_reports_save_all(array $reports): bool
{
    try {
        pharmacy_reports_save_database($reports, caps_db());
    } catch (Throwable) {
        // The JSON copy below remains a safe fallback for local/offline installs.
    }

    $path = pharmacy_reports_storage_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode(array_values($reports), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents($path, $payload . PHP_EOL, LOCK_EX) !== false;
}

function pharmacy_reports_save_database(array $reports, PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM pharmacy_reports');
        $statement = $pdo->prepare('INSERT INTO pharmacy_reports
            (id, pharmacy_id, pharmacy_name, pharmacy_email, reporter_name, reporter_email, reason, details, proof_path, status, admin_note, action_taken, reviewed_at, created_at, updated_at)
            VALUES (:id, :pharmacy_id, :pharmacy_name, :pharmacy_email, :reporter_name, :reporter_email, :reason, :details, :proof_path, :status, :admin_note, :action_taken, :reviewed_at, :created_at, :updated_at)');
        foreach ($reports as $report) {
            $statement->execute([
                'id' => (string) ($report['id'] ?? ''),
                'pharmacy_id' => (string) ($report['pharmacy_id'] ?? ''),
                'pharmacy_name' => (string) ($report['pharmacy_name'] ?? 'Pharmacy'),
                'pharmacy_email' => (string) ($report['pharmacy_email'] ?? ''),
                'reporter_name' => (string) ($report['reporter_name'] ?? ''),
                'reporter_email' => (string) ($report['reporter_email'] ?? ''),
                'reason' => (string) ($report['reason'] ?? ''),
                'details' => (string) ($report['details'] ?? ''),
                'proof_path' => (string) ($report['proof_path'] ?? ''),
                'status' => (string) ($report['status'] ?? 'under_review'),
                'admin_note' => (string) ($report['admin_note'] ?? ''),
                'action_taken' => (string) ($report['action_taken'] ?? ''),
                'reviewed_at' => (string) ($report['reviewed_at'] ?? ''),
                'created_at' => (string) ($report['created_at'] ?? date('c')),
                'updated_at' => (string) ($report['updated_at'] ?? date('c')),
            ]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function pharmacy_reports_find_by_id(string $id): ?array
{
    foreach (pharmacy_reports_load_all() as $report) {
        if (($report['id'] ?? '') === $id) {
            return $report;
        }
    }

    return null;
}

function pharmacy_reports_update(string $id, array $changes): array
{
    $reports = pharmacy_reports_load_all();
    $found = false;

    foreach ($reports as $index => $report) {
        if (($report['id'] ?? '') !== $id) {
            continue;
        }

        $reports[$index] = array_merge($report, $changes, [
            'updated_at' => date('c'),
        ]);
        $found = true;
        break;
    }

    if (!$found) {
        return ['ok' => false, 'error' => 'Report not found.'];
    }

    if (!pharmacy_reports_save_all($reports)) {
        return ['ok' => false, 'error' => 'Could not save report changes.'];
    }

    return ['ok' => true, 'report' => pharmacy_reports_find_by_id($id)];
}

function pharmacy_reports_open_count(): int
{
    $count = 0;
    foreach (pharmacy_reports_load_all() as $report) {
        $status = (string) ($report['status'] ?? '');
        if ($status === 'under_review' || $status === 'open') {
            $count++;
        }
    }

    return $count;
}

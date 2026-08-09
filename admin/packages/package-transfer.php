<?php

require_once __DIR__ . '/../../assets/php/package-itinerary.php';

const PACKAGE_TRANSFER_VERSION = 1;

function packageTransferImage(string $relativePath): ?array
{
    $relativePath = ltrim(trim($relativePath), '/');
    if ($relativePath === '' || preg_match('~^(?:https?:)?//~i', $relativePath)) {
        return null;
    }

    $absolutePath = SITE_ROOT . $relativePath;
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }

    $contents = file_get_contents($absolutePath);
    if ($contents === false) {
        return null;
    }

    $mime = function_exists('mime_content_type') ? (string)mime_content_type($absolutePath) : '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $mime = 'application/octet-stream';
    }

    return [
        'path' => $relativePath,
        'name' => basename($absolutePath),
        'mime' => $mime,
        'data' => base64_encode($contents),
    ];
}

function packageTransferSlug(PDO $pdo, string $baseSlug): string
{
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', strtolower(trim($baseSlug))) ?: 'imported-package';
    $slug = trim($slug, '-') ?: 'imported-package';
    $candidate = $slug;
    $number = 1;
    $check = $pdo->prepare('SELECT id FROM packages WHERE slug = ? LIMIT 1');
    while (true) {
        $check->execute([$candidate]);
        if (!$check->fetchColumn()) {
            return $candidate;
        }
        $number++;
        $candidate = $slug . '-copy' . ($number > 2 ? '-' . ($number - 1) : '');
    }
}

function packageTransferStoreImage(array $image, array &$createdFiles): ?string
{
    $data = base64_decode((string)($image['data'] ?? ''), true);
    if ($data === false || $data === '') {
        return null;
    }

    $mime = (string)($image['mime'] ?? '');
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        return null;
    }

    $folder = str_contains((string)($image['path'] ?? ''), '/itinerary/')
        ? 'uploads/packages/itinerary/'
        : 'uploads/packages/';
    $absoluteFolder = SITE_ROOT . $folder;
    if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0775, true) && !is_dir($absoluteFolder)) {
        throw new RuntimeException('Could not create the package image folder.');
    }

    $filename = 'import_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extensions[$mime];
    $relativePath = $folder . $filename;
    $absolutePath = SITE_ROOT . $relativePath;
    if (file_put_contents($absolutePath, $data, LOCK_EX) === false) {
        throw new RuntimeException('Could not save an imported package image.');
    }
    $createdFiles[] = $relativePath;
    return $relativePath;
}

function packageTransferExport(PDO $pdo): array
{
    nt_ensure_package_itinerary_table($pdo);
    $packages = $pdo->query('SELECT title, slug, category, badge, duration, price, old_price, group_size,
        difficulty, best_season, rating, review_count, description, highlights, itinerary, inclusions,
        exclusions, destinations, cover_image, is_featured, is_active FROM packages ORDER BY id ASC')->fetchAll();
    $itemStmt = $pdo->prepare('SELECT day_number, title, description, image_1, image_2, sort_order
        FROM package_itinerary_items WHERE package_id = ? ORDER BY sort_order ASC, id ASC');
    $idStmt = $pdo->prepare('SELECT id FROM packages WHERE slug = ? LIMIT 1');

    foreach ($packages as &$package) {
        $package['cover_image_data'] = packageTransferImage((string)$package['cover_image']);
        unset($package['cover_image']);
        $idStmt->execute([$package['slug']]);
        $packageId = (int)$idStmt->fetchColumn();
        $itemStmt->execute([$packageId]);
        $package['itinerary_items'] = [];
        foreach ($itemStmt->fetchAll() as $item) {
            $item['image_1_data'] = packageTransferImage((string)$item['image_1']);
            $item['image_2_data'] = packageTransferImage((string)$item['image_2']);
            unset($item['image_1'], $item['image_2']);
            $package['itinerary_items'][] = $item;
        }
    }

    return ['format' => 'caglaf-tour-packages', 'version' => PACKAGE_TRANSFER_VERSION,
        'exported_at' => gmdate('c'), 'packages' => $packages];
}

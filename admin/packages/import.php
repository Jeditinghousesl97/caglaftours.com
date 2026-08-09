<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/package-transfer.php';

$errors = [];
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['package_file'] ?? null;
    if (!$file || (int)$file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose a valid exported package file.';
    } elseif ((int)$file['size'] > 250 * 1024 * 1024) {
        $errors[] = 'The import file must be under 250MB.';
    } else {
        $json = null;
        $zip = null;
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) !== true || $zip->locateName('manifest.json') === false) {
                $errors[] = 'This ZIP does not contain a valid package export manifest.';
            } else {
                $json = $zip->getFromName('manifest.json');
            }
        } else {
            $json = file_get_contents($file['tmp_name']);
        }
        $payload = json_decode((string)$json, true);
        if (!$errors && (!is_array($payload) || ($payload['format'] ?? '') !== 'caglaf-tour-packages' || (int)($payload['version'] ?? 0) !== PACKAGE_TRANSFER_VERSION)) {
            $errors[] = 'This is not a valid CAGLAF Tours package export file.';
        } elseif (!$errors && !is_array($payload['packages'] ?? null)) {
            $errors[] = 'The export file does not contain any packages.';
        } elseif (!$errors) {
            if ($zip instanceof ZipArchive) {
                foreach ($payload['packages'] as &$package) {
                    foreach ($package['itinerary_items'] ?? [] as &$item) {
                        foreach (['image_1_data', 'image_2_data'] as $field) {
                            if (!empty($item[$field]['file'])) {
                                $item[$field]['data'] = base64_encode((string)$zip->getFromName($item[$field]['file']));
                            }
                        }
                    }
                    unset($item);
                    if (!empty($package['cover_image_data']['file'])) {
                        $package['cover_image_data']['data'] = base64_encode((string)$zip->getFromName($package['cover_image_data']['file']));
                    }
                }
                unset($package);
                $zip->close();
            }
            $pdo = getPDO();
            $createdFiles = [];
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare('INSERT INTO packages
                    (title, slug, category, badge, duration, price, old_price, group_size, difficulty,
                     best_season, rating, review_count, description, highlights, itinerary, inclusions,
                    exclusions, destinations, cover_image, is_featured, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                nt_ensure_package_itinerary_table($pdo);
                $itemInsert = $pdo->prepare('INSERT INTO package_itinerary_items
                    (package_id, day_number, title, description, image_1, image_2, sort_order)
                    VALUES (?,?,?,?,?,?,?)');

                foreach ($payload['packages'] as $package) {
                    if (!is_array($package) || trim((string)($package['title'] ?? '')) === '' || trim((string)($package['description'] ?? '')) === '') {
                        continue;
                    }
                    $slug = packageTransferSlug($pdo, (string)($package['slug'] ?? $package['title']));
                    $cover = !empty($package['cover_image_data']) ? packageTransferStoreImage($package['cover_image_data'], $createdFiles) : null;
                    $insert->execute([
                        trim((string)$package['title']), $slug, (string)($package['category'] ?? 'sightseeing'),
                        $package['badge'] ?: null, (string)($package['duration'] ?? ''), $package['price'] ?? null,
                        $package['old_price'] ?? null, $package['group_size'] ?? null, $package['difficulty'] ?: 'moderate',
                        $package['best_season'] ?? null, $package['rating'] ?? null, (int)($package['review_count'] ?? 0),
                        (string)$package['description'], $package['highlights'] ?? null, $package['itinerary'] ?? null,
                        $package['inclusions'] ?? null, $package['exclusions'] ?? null, $package['destinations'] ?? null,
                        $cover, (int)($package['is_featured'] ?? 0), (int)($package['is_active'] ?? 1)
                    ]);
                    $packageId = (int)$pdo->lastInsertId();
                    foreach (($package['itinerary_items'] ?? []) as $item) {
                        if (!is_array($item) || trim((string)($item['title'] ?? '')) === '') continue;
                        $image1 = !empty($item['image_1_data']) ? packageTransferStoreImage($item['image_1_data'], $createdFiles) : null;
                        $image2 = !empty($item['image_2_data']) ? packageTransferStoreImage($item['image_2_data'], $createdFiles) : null;
                        $itemInsert->execute([$packageId, (int)($item['day_number'] ?? 1), $item['title'], $item['description'] ?? null, $image1, $image2, (int)($item['sort_order'] ?? 0)]);
                    }
                    $imported++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($createdFiles as $path) nt_delete_uploaded_file($path);
                $imported = 0;
                $errors[] = 'Import failed. No packages were added: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Import Packages';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <h1><i class="bi bi-upload me-2 text-primary"></i>Import Tour Packages</h1>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<?php if ($imported > 0): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= $imported ?> package(s) imported. Existing packages were not changed or removed.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="admin-card p-4">
  <p>Select a ZIP file exported from this website. Package data, cover images, and itinerary images are included in the file.</p>
  <p class="text-muted small">Imports are additive only. Duplicate slugs are automatically renamed, and existing packages are never overwritten.</p>
  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="package_file" class="form-control mb-3" accept=".zip,.json,application/zip,application/json" required>
    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Import Packages</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

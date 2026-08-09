<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/package-transfer.php';

$payload = packageTransferExport(getPDO());
$tempFile = tempnam(sys_get_temp_dir(), 'caglaf_export_');
$zip = new ZipArchive();
if ($tempFile === false || $zip->open($tempFile, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create the export ZIP file.');
}

$imageNumber = 0;
$addImage = static function (?array &$image, ZipArchive $zip, int &$imageNumber): void {
    if (!$image || empty($image['data'])) return;
    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$image['mime'] ?? ''] ?? null;
    if ($extension === null) return;
    $imageNumber++;
    $zipPath = 'images/image-' . $imageNumber . '.' . $extension;
    $decoded = base64_decode((string)$image['data'], true);
    if ($decoded === false) return;
    $zip->addFromString($zipPath, $decoded);
    $image = ['file' => $zipPath, 'mime' => $image['mime']];
};

foreach ($payload['packages'] as &$package) {
    $addImage($package['cover_image_data'], $zip, $imageNumber);
    foreach ($package['itinerary_items'] as &$item) {
        $addImage($item['image_1_data'], $zip, $imageNumber);
        $addImage($item['image_2_data'], $zip, $imageNumber);
    }
}
unset($package, $item);
$zip->addFromString('manifest.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$zip->close();

$filename = 'caglaf-tour-packages-' . date('Y-m-d-His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tempFile));
header('X-Content-Type-Options: nosniff');
readfile($tempFile);
unlink($tempFile);
exit;

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachmentId) {
    http_response_code(404);
    exit('المرفق المطلوب غير موجود.');
}

$statement = db()->prepare(
    "SELECT experience_attachments.original_name, experience_attachments.stored_name,
            experience_attachments.mime_type, experiences.user_id, experiences.status
     FROM experience_attachments
     JOIN experiences ON experiences.id = experience_attachments.experience_id
     WHERE experience_attachments.id = ?
     LIMIT 1"
);
$statement->execute([$attachmentId]);
$attachment = $statement->fetch();

if (
    $attachment === false
    || ($attachment['status'] !== 'published'
        && (current_user()['role'] !== 'admin' && (int) $attachment['user_id'] !== (int) current_user()['id']))
) {
    http_response_code(404);
    exit('المرفق المطلوب غير موجود.');
}

$storedName = $attachment['stored_name'];
if (!preg_match('/\A[a-f0-9]{48}\.(pdf|docx?|pptx?|xlsx?|png|jpe?g|gif|webp|mp4|webm|ogv)\z/', $storedName)) {
    http_response_code(404);
    exit('المرفق المطلوب غير موجود.');
}

$inline = ($_GET['inline'] ?? '') === '1';
$extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));
$inlineMimeTypes = [
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'mp4' => ['video/mp4'],
    'webm' => ['video/webm'],
    'ogv' => ['video/ogg'],
];

if ($inline && !in_array($attachment['mime_type'], $inlineMimeTypes[$extension] ?? [], true)) {
    http_response_code(404);
    exit('المرفق المطلوب غير موجود.');
}

$uploadDirectory = realpath(ROOT_PATH . '/storage/uploads');
$filePath = $uploadDirectory === false ? false : realpath($uploadDirectory . DIRECTORY_SEPARATOR . $storedName);
if ($filePath === false || !str_starts_with($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit('المرفق المطلوب غير موجود.');
}

$safeFileName = preg_replace('/[\r\n"\\\\]/', '_', $attachment['original_name']) ?: 'attachment';
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="attachment"; filename*=UTF-8\'\'' . rawurlencode($safeFileName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;

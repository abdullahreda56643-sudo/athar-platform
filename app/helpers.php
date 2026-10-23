<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function text_input(array $source, string $key): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(419);
        exit('انتهت صلاحية النموذج. أعيدي تحميل الصفحة وحاولي مرة أخرى.');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $storedMessage = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $storedMessage;
}

function experience_categories(): array
{
    static $categories;

    if (!is_array($categories)) {
        $categories = db()->query(
            'SELECT id, category_key, name, icon
             FROM experience_categories
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    return $categories;
}

function category_label(string $category): string
{
    static $labels;

    if (!is_array($labels)) {
        $labels = [];
        foreach (experience_categories() as $item) {
            $labels[$item['category_key']] = $item['name'];
        }
    }

    return $labels[$category] ?? $category;
}

function log_admin_activity(string $action, string $entityType, int $entityId, string $details): void
{
    $statement = db()->prepare(
        'INSERT INTO admin_activity (admin_id, action, entity_type, entity_id, details)
         VALUES (?, ?, ?, ?, ?)'
    );
    $statement->execute([current_user()['id'], $action, $entityType, $entityId, $details]);
}

function remove_experience_uploads(array $storedNames): bool
{
    $uploadDirectory = realpath(ROOT_PATH . '/storage/uploads');
    if ($uploadDirectory === false) {
        error_log('Unable to resolve the experience upload directory during deletion.');
        return $storedNames === [];
    }

    $allRemoved = true;
    foreach ($storedNames as $storedName) {
        if (!is_string($storedName) || preg_match('/\A[a-f0-9]{48}\.(pdf|docx?|pptx?|xlsx?|png|jpe?g|gif|webp|mp4|webm|ogv)\z/', $storedName) !== 1) {
            error_log('Refusing to remove an experience attachment with an invalid stored filename.');
            $allRemoved = false;
            continue;
        }

        $filePath = realpath($uploadDirectory . DIRECTORY_SEPARATOR . $storedName);
        if ($filePath === false) {
            continue;
        }
        if (!str_starts_with($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
            error_log('Refusing to remove an experience attachment outside its upload directory.');
            $allRemoved = false;
            continue;
        }
        if (!unlink($filePath)) {
            error_log('Unable to remove an experience attachment after its database record was deleted.');
            $allRemoved = false;
        }
    }

    return $allRemoved;
}

function format_experience_content(string $content): string
{
    $escapedContent = e(str_replace(["\r\n", "\r"], "\n", $content));
    $escapedContent = preg_replace_callback(
        '/\*\*(.+?)\*\*/u',
        static fn (array $match): string => '<strong>' . $match[1] . '</strong>',
        $escapedContent
    ) ?? $escapedContent;
    $escapedContent = preg_replace_callback(
        '/(?<!\*)\*([^*\n]+)\*(?!\*)/u',
        static fn (array $match): string => '<em>' . $match[1] . '</em>',
        $escapedContent
    ) ?? $escapedContent;

    $html = '';
    $listType = null;
    foreach (explode("\n", $escapedContent) as $line) {
        $nextListType = null;
        if (preg_match('/^\s*(?:[-*•])\s+(.+)$/u', $line, $match) === 1) {
            $nextListType = 'ul';
        } elseif (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match) === 1) {
            $nextListType = 'ol';
        }

        if ($nextListType !== null) {
            if ($listType !== $nextListType) {
                if ($listType !== null) {
                    $html .= '</' . $listType . '>';
                }
                $html .= '<' . $nextListType . ' class="experience-content-list">';
                $listType = $nextListType;
            }
            $html .= '<li>' . $match[1] . '</li>';
            continue;
        }

        if ($listType !== null) {
            $html .= '</' . $listType . '>';
            $listType = null;
        }

        if (preg_match('/^###\s+(.+)$/u', $line, $match) === 1) {
            $html .= '<h3>' . $match[1] . '</h3>';
        } elseif ($line === '') {
            $html .= '<br>';
        } else {
            $html .= $line . '<br>';
        }
    }

    if ($listType !== null) {
        $html .= '</' . $listType . '>';
    }

    return preg_replace('/(?:<br>)+$/', '', $html) ?? $html;
}

<?php

declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        flash('error', 'سجّلي الدخول أولاً لمتابعة هذه الصفحة.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if ((current_user()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('ليس لديكِ صلاحية لعرض هذه الصفحة.');
    }
}

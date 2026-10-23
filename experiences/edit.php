<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$experienceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$experienceId) {
    http_response_code(404);
    exit('الخبرة المطلوبة غير موجودة.');
}

redirect('experiences/create.php?id=' . $experienceId);

<?php
$pageTitle = $pageTitle ?? PLATFORM_NAME;
$user = current_user();
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$isExperienceBank = str_contains($scriptName, '/experiences/index.php');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e('منصة تعليمية لمشاركة الخبرات والممارسات الناجحة في ' . SCHOOL_NAME . '.') ?>">
    <title><?= e($pageTitle) ?> | <?= e(SCHOOL_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#14213D',
                        teal: { 50: '#effaf8', 100: '#d8f2ed', 600: '#0F8B7D', 700: '#0A6359' },
                        sand: '#f8f7f2'
                    },
                    fontFamily: {
                        sans: ['Cairo', 'Tahoma', 'Arial', 'sans-serif'],
                        head: ['Cairo', 'Tahoma', 'sans-serif']
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <?php if (isset($pageStylesheet)): ?><link rel="stylesheet" href="<?= e(url($pageStylesheet)) ?>"><?php endif; ?>
    <script src="<?= e(url('assets/js/navigation.js')) ?>" defer></script>
    <style>
        :focus-visible { outline: 3px solid #FFC83D; outline-offset: 3px; border-radius: .75rem; }
    </style>
</head>
<body class="min-h-screen bg-[#f5f8f2] font-sans text-[#15362d] antialiased <?= e($bodyClass ?? '') ?>">
<header class="site-header">
    <nav class="site-nav" aria-label="التنقل الرئيسي">
        <a class="site-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark" aria-hidden="true">✧</span>
            <span class="brand-copy">
                <span><?= e(SCHOOL_NAME) ?></span>
                <strong><?= e(PLATFORM_NAME) ?></strong>
            </span>
        </a>

        <div class="site-links" id="primary-navigation">
            <a class="site-link<?= !$isExperienceBank && basename($scriptName) === 'index.php' ? ' is-current' : '' ?>" href="<?= e(url('index.php')) ?>">الرئيسية</a>
            <a class="site-link<?= $isExperienceBank ? ' is-current' : '' ?>" href="<?= e(url($user !== null ? 'experiences/index.php' : 'login.php')) ?>">بنك الخبرات</a>
            <a class="site-link" href="<?= e(url($user !== null ? 'experiences/create.php' : 'login.php')) ?>">إضافة خبرة جديدة</a>
            <?php if ($user !== null && $user['role'] === 'teacher'): ?>
                <a class="site-link<?= str_contains($scriptName, '/experiences/my.php') ? ' is-current' : '' ?>" href="<?= e(url('experiences/my.php')) ?>">خبراتي</a>
            <?php endif; ?>
            <a class="site-link" href="<?= e(url('index.php#about')) ?>">عن المنصة</a>
            <?php if ($user !== null && $user['role'] === 'admin'): ?>
                <a class="site-link" href="<?= e(url('admin/index.php')) ?>">لوحة الإدارة</a>
            <?php endif; ?>
            <?php if ($user === null): ?>
                <a class="mobile-nav-login" href="<?= e(url('login.php')) ?>">تسجيل الدخول <span aria-hidden="true">↗</span></a>
                <a class="mobile-nav-login" href="<?= e(url('register.php')) ?>">إنشاء حساب جديد <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>

        <div class="site-account<?= $user === null ? ' site-account-guest' : '' ?>">
            <?php if ($user !== null): ?>
                <span class="account-greeting">مرحباً، <?= e($user['name']) ?></span>
                <a class="account-button account-button-light" href="<?= e(url('logout.php')) ?>">تسجيل الخروج</a>
            <?php else: ?>
                <a class="account-button account-button-light" href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
                <a class="account-button account-button-primary" href="<?= e(url('register.php')) ?>">إنشاء حساب <span aria-hidden="true">↗</span></a>
            <?php endif; ?>
        </div>

        <button class="menu-toggle" type="button" aria-label="فتح قائمة التنقل" aria-expanded="false" aria-controls="primary-navigation">
            <span class="menu-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
    </nav>
</header>
<main>
    <?php if ($message = flash('success')): ?>
        <div class="mx-auto mt-5 max-w-7xl px-5"><p class="rounded-2xl border border-teal-100 bg-teal-50 px-5 py-3 text-teal-700"><?= e($message) ?></p></div>
    <?php endif; ?>
    <?php if ($message = flash('error')): ?>
        <div class="mx-auto mt-5 max-w-7xl px-5"><p class="rounded-2xl border border-rose-100 bg-rose-50 px-5 py-3 text-rose-700"><?= e($message) ?></p></div>
    <?php endif; ?>
<?php

require __DIR__ . '/app/bootstrap.php';

if (current_user() !== null) {
    redirect('experiences/index.php');
}

$error = null;
$emailInput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $emailInput = text_input($_POST, 'email');
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email === false || !is_string($password) || $password === '') {
        $error = 'أدخلي البريد الإلكتروني وكلمة المرور بشكل صحيح.';
    } else {
        $statement = db()->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if ($user === false || !password_verify($password, $user['password'])) {
            $error = 'بيانات الدخول غير صحيحة.';
        } else {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            redirect('experiences/index.php');
        }
    }
}

$pageTitle = 'تسجيل الدخول';
$bodyClass = 'login-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="login-page" aria-labelledby="login-title">
    <div class="login-ambient login-ambient-top" aria-hidden="true"></div>
    <div class="login-ambient login-ambient-bottom" aria-hidden="true"></div>
    <div class="login-card">
        <div class="login-card-stripe" aria-hidden="true"></div>
        <div class="login-card-content">
            <header class="login-intro">
                <div class="login-crest" aria-hidden="true">
                    <svg viewBox="0 0 96 96" role="presentation">
                        <circle cx="48" cy="48" r="46" fill="#1b624d"/>
                        <circle cx="48" cy="48" r="36" fill="none" stroke="#d5b46b" stroke-dasharray="2 3" stroke-width="1.5"/>
                        <path d="M48 29v32c-9-5-18-5-25-2V37c8-3 17-2 25 3 8-5 17-6 25-3v22c-7-3-16-3-25 2" fill="#fffdf5"/>
                        <path d="M48 40v20" stroke="#1b624d" stroke-width="2"/>
                        <path d="M48 20l2.5 5.5L56 28l-5.5 2.5L48 36l-2.5-5.5L40 28l5.5-2.5z" fill="#e6c16e"/>
                    </svg>
                    <span class="login-crest-check">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3.2 3.2L15.5 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></svg>
                    </span>
                </div>
                <span class="login-kicker">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5.5 10 3l7 2.5v5c0 3.7-2.8 6-7 8-4.2-2-7-4.3-7-8z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m7 10 2 2 4-4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                    بوابة المعلمات الأكاديمية
                </span>
                <h1 id="login-title"><?= e(PLATFORM_NAME) ?></h1>
                <p><?= e(SCHOOL_NAME) ?> — مجتمع تعليمي مهني</p>
            </header>

            <aside class="login-privacy" aria-label="معلومات الخصوصية">
                <span class="login-privacy-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3 20 6v5.5c0 4.4-3.1 7.5-8 9.5-4.9-2-8-5.1-8-9.5V6z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.7"/><path d="m8.5 12 2.3 2.3 4.8-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/></svg>
                </span>
                <div>
                    <strong>بيئة تعليمية آمنة وموثوقة</strong>
                    <p>أنشئي حساباً جديداً من صفحة التسجيل، ثم سجّلي الدخول لمشاركة الخبرات التعليمية.</p>
                </div>
            </aside>

            <?php if ($error !== null): ?>
                <p class="login-error" id="login-error" role="alert">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4.5m0 2.5h.01" stroke="currentColor" stroke-linecap="round" stroke-width="1.7"/></svg>
                    <?= e($error) ?>
                </p>
            <?php endif; ?>

            <form class="login-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="login-field">
                    <label for="login-email">البريد الإلكتروني الأكاديمي <span aria-hidden="true">*</span></label>
                    <div class="login-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="10" r="2.4" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 17c.8-1.7 2.1-2.5 4-2.5s3.2.8 4 2.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.6"/></svg>
                        <input id="login-email" type="email" name="email" value="<?= e($emailInput) ?>" placeholder="أدخلي بريدكِ المدرسي الرسمي" autocomplete="username" aria-required="true" aria-describedby="login-email-hint<?= $error !== null ? ' login-error' : '' ?>" required autofocus>
                    </div>
                    <span class="login-field-hint" id="login-email-hint">أدخلي البريد الإلكتروني الذي سجلتِ به.</span>
                </div>

                <div class="login-field">
                    <label for="login-password">كلمة المرور <span aria-hidden="true">*</span></label>
                    <div class="login-input-wrap login-password-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.6"/></svg>
                        <input id="login-password" type="password" name="password" placeholder="أدخلي كلمة المرور المعتمدة" autocomplete="current-password" aria-required="true" required>
                        <button class="login-password-toggle" type="button" aria-label="إظهار كلمة المرور" aria-pressed="false">
                            <svg class="eye-icon eye-icon-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.2-6 9.2-6 9.2 6 9.2 6-3.2 6-9.2 6-9.2-6-9.2-6z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                            <svg class="eye-icon eye-icon-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.5 10.5 0 0 1 12 6c6 0 9.2 6 9.2 6a16 16 0 0 1-3.1 3.7M6.3 6.4C4.1 8 2.8 12 2.8 12s3.2 6 9.2 6c1 0 2-.2 2.8-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>
                        </button>
                    </div>
                </div>

                <p class="login-security-note">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.2c0 3.3-2.3 5.7-6 7.3-3.7-1.6-6-4-6-7.3V5z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.4"/><path d="m7.5 9.8 1.7 1.7 3.4-3.6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"/></svg>
                    دخول آمن إلى منصة الخبرات
                </p>

                <button class="login-submit" type="submit">
                    <span>الدخول إلى منصة الخبرات</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m0 0 6-6m-6 6 6 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
                </button>
            </form>
            <div class="login-account-prompt">
                <span>ليس لديكِ حساب؟</span>
                <a href="<?= e(url('register.php')) ?>">أنشئي حساباً جديداً <span aria-hidden="true">←</span></a>
            </div>
        </div>
    </div>
</section>
<script>
    document.querySelectorAll('.login-password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const password = document.getElementById('login-password');
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isVisible));
            this.setAttribute('aria-label', isVisible ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
            this.closest('.login-password-input-wrap').classList.toggle('password-is-visible', !isVisible);
        });
    });
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

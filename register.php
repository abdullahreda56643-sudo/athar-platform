<?php

require __DIR__ . '/app/bootstrap.php';

if (current_user() !== null) {
    redirect('experiences/index.php');
}

$error = null;
$nameInput = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $website = $_POST['website'] ?? '';
    if (!is_string($website) || trim($website) !== '') {
        flash('success', 'تم استلام طلب التسجيل. يمكنكِ تسجيل الدخول بعد إنشاء الحساب.');
        redirect('login.php');
    }

    $nameInput = text_input($_POST, 'name');
    $emailInput = strtolower(text_input($_POST, 'email'));
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (
        mb_strlen($nameInput) < 2
        || mb_strlen($nameInput) > 120
        || $email === false
        || strlen($emailInput) > 190
        || !is_string($password)
        || strlen($password) < 10
        || strlen($password) > 72
    ) {
        $error = 'تحققي من الاسم والبريد الإلكتروني، واجعلي كلمة المرور بين 10 و72 بايتاً.';
    } else {
        try {
            $statement = db()->prepare(
                'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)'
            );
            $statement->execute([$nameInput, $email, password_hash($password, PASSWORD_DEFAULT), 'teacher']);
            flash('success', 'تم إنشاء حسابكِ بنجاح. يمكنكِ تسجيل الدخول الآن.');
            redirect('login.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $error = 'تعذر إنشاء الحساب بهذا البريد الإلكتروني. تحققي من البيانات أو جرّبي تسجيل الدخول.';
        }
    }
}

$pageTitle = 'إنشاء حساب جديد';
$bodyClass = 'login-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="login-page" aria-labelledby="register-title">
    <div class="login-ambient login-ambient-top" aria-hidden="true"></div>
    <div class="login-ambient login-ambient-bottom" aria-hidden="true"></div>
    <div class="login-card">
        <div class="login-card-stripe" aria-hidden="true"></div>
        <div class="login-card-content">
            <header class="login-intro">
                <span class="login-kicker">بوابة المعلمات الأكاديمية</span>
                <h1 id="register-title">إنشاء حساب جديد</h1>
                <p>انضمي إلى مجتمع <?= e(SCHOOL_NAME) ?> وشاركي الخبرات التعليمية.</p>
            </header>

            <aside class="login-privacy" aria-label="معلومات التسجيل">
                <div>
                    <strong>حسابكِ مسؤوليتكِ</strong>
                    <p>استخدمي بريداً إلكترونياً يمكنكِ الوصول إليه، واختاري كلمة مرور قوية لا تستخدمينها في مواقع أخرى.</p>
                </div>
            </aside>

            <?php if ($error !== null): ?>
                <p class="login-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="login-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="registration-trap" aria-hidden="true">
                    <label for="registration-website">اتركي هذا الحقل فارغاً</label>
                    <input id="registration-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="login-field">
                    <label for="register-name">الاسم الكامل <span aria-hidden="true">*</span></label>
                    <div class="login-input-wrap">
                        <input id="register-name" type="text" name="name" value="<?= e($nameInput) ?>" maxlength="120" autocomplete="name" required autofocus>
                    </div>
                </div>

                <div class="login-field">
                    <label for="register-email">البريد الإلكتروني <span aria-hidden="true">*</span></label>
                    <div class="login-input-wrap">
                        <input id="register-email" type="email" name="email" value="<?= e($emailInput) ?>" maxlength="190" autocomplete="email" required>
                    </div>
                </div>

                <div class="login-field">
                    <label for="register-password">كلمة المرور <span aria-hidden="true">*</span></label>
                    <div class="login-input-wrap login-password-input-wrap">
                        <input id="register-password" type="password" name="password" minlength="10" maxlength="72" autocomplete="new-password" aria-describedby="register-password-hint" required>
                        <button class="login-password-toggle" type="button" aria-label="إظهار كلمة المرور" aria-pressed="false" aria-controls="register-password">
                            <svg class="eye-icon eye-icon-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.8 12s3.2-6 9.2-6 9.2 6 9.2 6-3.2 6-9.2 6-9.2-6-9.2-6z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
                            <svg class="eye-icon eye-icon-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A10.5 10.5 0 0 1 12 6c6 0 9.2 6 9.2 6a16 16 0 0 1-3.1 3.7M6.3 6.4C4.1 8 2.8 12 2.8 12s3.2 6 9.2 6c1 0 2-.2 2.8-.5M9.9 9.9a3 3 0 0 0 4.2 4.2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>
                        </button>
                    </div>
                    <span class="login-field-hint" id="register-password-hint">10 أحرف على الأقل، وبحد أقصى 72 بايتاً.</span>
                </div>

                <button class="login-submit" type="submit">
                    <span>إنشاء الحساب</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m0 0 6-6m-6 6 6 6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
                </button>
            </form>
            <div class="login-account-prompt">
                <span>لديكِ حساب بالفعل؟</span>
                <a href="<?= e(url('login.php')) ?>">سجّلي الدخول <span aria-hidden="true">←</span></a>
            </div>
        </div>
    </div>
</section>
<script>
    document.querySelectorAll('.login-password-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const password = document.getElementById('register-password');
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isVisible));
            this.setAttribute('aria-label', isVisible ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
            this.closest('.login-password-input-wrap').classList.toggle('password-is-visible', !isVisible);
        });
    });
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$adminId = (int) current_user()['id'];
$adminStatement = db()->prepare("SELECT name, email, password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$adminStatement->execute([$adminId]);
$admin = $adminStatement->fetch();
if ($admin === false) {
    http_response_code(404);
    exit('حساب الإدارة غير موجود.');
}

$error = null;
$nameInput = $admin['name'];
$emailInput = $admin['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nameInput = text_input($_POST, 'name');
    $emailInput = strtolower(text_input($_POST, 'email'));
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        mb_strlen($nameInput) < 2
        || mb_strlen($nameInput) > 120
        || $email === false
        || strlen($emailInput) > 190
    ) {
        $error = 'تحققي من الاسم والبريد الإلكتروني.';
    } elseif (
        !is_string($currentPassword)
        || !is_string($newPassword)
        || !is_string($confirmPassword)
    ) {
        $error = 'تحققي من بيانات كلمة المرور.';
    } elseif ($newPassword !== '' && (strlen($newPassword) < 10 || strlen($newPassword) > 72)) {
        $error = 'اجعلي كلمة المرور الجديدة بين 10 و72 بايتاً.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'كلمتا المرور الجديدتان غير متطابقتين.';
    } elseif (!password_verify($currentPassword, $admin['password'])) {
        $error = 'كلمة المرور الحالية غير صحيحة.';
    } else {
        $passwordHash = $newPassword === '' ? $admin['password'] : password_hash($newPassword, PASSWORD_DEFAULT);
        $updateAdmin = db()->prepare(
            "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'admin'"
        );

        try {
            $updateAdmin->execute([$nameInput, $email, $passwordHash, $adminId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            $error = 'هذا البريد الإلكتروني مستخدم بالفعل.';
        }

        if ($error === null) {
            $_SESSION['user']['name'] = $nameInput;
            $_SESSION['user']['email'] = $email;
            session_regenerate_id(true);
            log_admin_activity('admin_credentials_updated', 'user', $adminId, 'تحديث بيانات دخول حساب الإدارة');
            flash('success', 'تم تحديث بيانات الدخول بنجاح.');
            redirect('admin/settings.php');
        }
    }
}

$pageTitle = 'بيانات دخول الإدارة';
$pageStylesheet = 'assets/css/admin.css';
$bodyClass = 'admin-dashboard-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="admin-page">
    <div class="admin-settings-hero">
        <div class="admin-settings-hero-copy">
            <a class="admin-settings-back-link" href="<?= e(url('admin/index.php')) ?>"><span aria-hidden="true">→</span> لوحة الإدارة</a>
            <span class="admin-settings-eyebrow"><span aria-hidden="true">✦</span> إعدادات الحساب</span>
            <h1>بيانات دخول الإدارة</h1>
            <p>حدّثي معلومات حسابكِ وحافظي على أمان وصولكِ إلى المنصة.</p>
        </div>
        <div class="admin-settings-hero-mark" aria-hidden="true"><span>⚙</span></div>
    </div>

    <div class="admin-settings-layout">
        <aside class="admin-panel admin-settings-profile" aria-labelledby="admin-profile-title">
            <div class="admin-settings-avatar" aria-hidden="true"><?= e(mb_substr($admin['name'], 0, 1)) ?></div>
            <span class="admin-settings-profile-label">حساب الإدارة</span>
            <h2 id="admin-profile-title"><?= e($admin['name']) ?></h2>
            <p class="admin-settings-profile-email"><?= e($admin['email']) ?></p>
            <div class="admin-settings-divider"></div>
            <ul class="admin-settings-facts">
                <li><span class="admin-settings-fact-icon" aria-hidden="true">✓</span><span><strong>حساب بصلاحيات إدارية</strong><small>يمكنكِ إدارة محتوى المنصة ومستخدميها</small></span></li>
                <li><span class="admin-settings-fact-icon" aria-hidden="true">⌑</span><span><strong>تأكيد آمن للتغييرات</strong><small>يلزم إدخال كلمة المرور الحالية للحفظ</small></span></li>
            </ul>
            <div class="admin-settings-security-note">
                <span aria-hidden="true">✦</span>
                <p>استخدمي كلمة مرور قوية وفريدة، ولا تشاركيها مع أي شخص.</p>
            </div>
        </aside>

        <section class="admin-panel admin-settings-form-panel" aria-labelledby="admin-settings-title">
            <div class="admin-settings-form-heading">
                <div>
                    <span class="admin-panel-kicker">تحديث الحساب</span>
                    <h2 class="admin-panel-title" id="admin-settings-title">إدارة بيانات الدخول</h2>
                </div>
                <span class="admin-settings-secure-badge"><span aria-hidden="true">✓</span> اتصال آمن</span>
            </div>
            <p class="admin-panel-description">عدّلي الاسم أو البريد الإلكتروني أو كلمة المرور. لا حاجة لإدخال كلمة مرور جديدة إذا كنتِ تريدين تحديث بيانات الحساب فقط.</p>
            <?php if ($error !== null): ?><p class="admin-form-error" role="alert"><span aria-hidden="true">!</span><?= e($error) ?></p><?php endif; ?>
            <form class="admin-settings-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="admin-settings-section-heading"><span>01</span><div><h3>المعلومات الأساسية</h3><p>الاسم والبريد المستخدمان في حسابكِ</p></div></div>
                <div class="admin-settings-fields">
                    <label for="admin-name">الاسم الظاهر
                        <input class="admin-input" id="admin-name" name="name" maxlength="120" value="<?= e($nameInput) ?>" autocomplete="name" required>
                    </label>
                    <label for="admin-email">البريد الإلكتروني لتسجيل الدخول
                        <input class="admin-input" id="admin-email" type="email" name="email" maxlength="190" value="<?= e($emailInput) ?>" autocomplete="username" required>
                    </label>
                </div>

                <div class="admin-settings-section-heading admin-settings-password-heading"><span>02</span><div><h3>تغيير كلمة المرور</h3><p>اتركي الحقلين الجديدين فارغين للإبقاء على كلمة المرور الحالية</p></div></div>
                <div class="admin-settings-fields">
                    <label class="admin-settings-current-password" for="current-password">كلمة المرور الحالية <span class="admin-settings-required">مطلوبة للحفظ</span>
                        <input class="admin-input" id="current-password" type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <label for="new-password">كلمة المرور الجديدة <span class="admin-settings-optional">اختيارية</span>
                        <input class="admin-input" id="new-password" type="password" name="new_password" minlength="10" maxlength="72" autocomplete="new-password" aria-describedby="new-password-hint">
                        <small class="admin-settings-field-hint" id="new-password-hint">10 أحرف على الأقل، وبحد أقصى 72 بايتاً.</small>
                    </label>
                    <label for="confirm-password">تأكيد كلمة المرور الجديدة
                        <input class="admin-input" id="confirm-password" type="password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password">
                    </label>
                </div>

                <div class="admin-settings-form-footer">
                    <span><span aria-hidden="true">⌑</span> ستبقى جلستكِ الحالية آمنة بعد التحديث.</span>
                    <button class="admin-button admin-button-primary" type="submit"><span aria-hidden="true">✓</span> حفظ التغييرات</button>
                </div>
            </form>
        </section>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$search = text_input($_GET, 'q');
$selectedId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($selectedId === false) {
    http_response_code(400);
    exit('معرّف الحساب غير صالح.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = text_input($_POST, 'action');

    if ($action === 'toggle_teacher') {
        $teacherId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $isActive = text_input($_POST, 'is_active');
        if (!$teacherId || !in_array($isActive, ['0', '1'], true)) {
            http_response_code(400);
            exit('طلب تحديث الحساب غير صالح.');
        }

        $findTeacher = db()->prepare("SELECT name, is_active FROM users WHERE id = ? AND role = 'teacher'");
        $findTeacher->execute([$teacherId]);
        $teacher = $findTeacher->fetch();
        if ($teacher === false) {
            http_response_code(404);
            exit('حساب المعلمة المطلوب غير موجود.');
        }

        $activeValue = (int) $isActive;
        if ((int) $teacher['is_active'] !== $activeValue) {
            $updateTeacher = db()->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'teacher'");
            $updateTeacher->execute([$activeValue, $teacherId]);
            log_admin_activity(
                $activeValue === 1 ? 'teacher_activated' : 'teacher_suspended',
                'user',
                $teacherId,
                ($activeValue === 1 ? 'تفعيل حساب المعلمة: ' : 'إيقاف حساب المعلمة: ') . mb_substr($teacher['name'], 0, 190)
            );
        }

        flash('success', $activeValue === 1 ? 'تم تفعيل الحساب.' : 'تم إيقاف الحساب.');
        redirect('admin/users.php?' . http_build_query(['q' => text_input($_POST, 'q'), 'user_id' => $teacherId]));
    } elseif ($action === 'delete_teacher') {
        $teacherId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if (!$teacherId) {
            http_response_code(400);
            exit('طلب حذف الحساب غير صالح.');
        }

        $connection = db();
        $connection->beginTransaction();
        try {
            $findTeacher = $connection->prepare("SELECT name FROM users WHERE id = ? AND role = 'teacher' FOR UPDATE");
            $findTeacher->execute([$teacherId]);
            $teacher = $findTeacher->fetch();
            if ($teacher === false) {
                $connection->rollBack();
                http_response_code(404);
                exit('حساب المعلمة المطلوب غير موجود.');
            }

            $attachmentsQuery = $connection->prepare(
                'SELECT experience_attachments.stored_name
                 FROM experience_attachments
                 JOIN experiences ON experiences.id = experience_attachments.experience_id
                 WHERE experiences.user_id = ?'
            );
            $attachmentsQuery->execute([$teacherId]);
            $storedNames = array_column($attachmentsQuery->fetchAll(), 'stored_name');

            $deleteTeacher = $connection->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            $deleteTeacher->execute([$teacherId]);
            log_admin_activity(
                'teacher_deleted',
                'user',
                $teacherId,
                'حذف حساب المعلمة ومحتواها: ' . mb_substr($teacher['name'], 0, 210)
            );
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }

        $uploadsRemoved = remove_experience_uploads($storedNames);
        flash(
            $uploadsRemoved ? 'success' : 'error',
            $uploadsRemoved
                ? 'تم حذف الحساب وخبراته وتعليقاته وإعجاباته ومرفقات خبراته.'
                : 'حُذف الحساب ومحتواه، لكن تعذّر حذف بعض الملفات المرفقة. راجعي سجل الخادم.'
        );
        redirect('admin/users.php?' . http_build_query(['q' => text_input($_POST, 'q')]));
    } else {
        http_response_code(400);
        exit('طلب غير صالح.');
    }
}

$userSearch = $search === '' ? '' : '%' . $search . '%';
$usersStatement = db()->prepare(
    "SELECT users.id, users.name, users.email, users.is_active, users.created_at,
            COUNT(experiences.id) AS experiences_count
     FROM users
     LEFT JOIN experiences ON experiences.user_id = users.id
     WHERE users.role = 'teacher'
       AND (? = '' OR users.name LIKE ? OR users.email LIKE ?)
     GROUP BY users.id, users.name, users.email, users.is_active, users.created_at
     ORDER BY users.name"
);
$usersStatement->execute([$search, $userSearch, $userSearch]);
$users = $usersStatement->fetchAll();

$selectedTeacher = null;
$teacherExperiences = [];
$experienceStatusLabels = ['published' => 'منشورة', 'pending' => 'قيد المراجعة', 'hidden' => 'مخفية / مرفوضة'];
if ($selectedId !== false && $selectedId !== null) {
    $profileStatement = db()->prepare(
        "SELECT users.id, users.name, users.email, users.is_active, users.created_at,
                SUM(experiences.status = 'published') AS published_count,
                SUM(experiences.status = 'pending') AS pending_count,
                SUM(experiences.status = 'hidden') AS hidden_count
         FROM users
         LEFT JOIN experiences ON experiences.user_id = users.id
         WHERE users.id = ? AND users.role = 'teacher'
         GROUP BY users.id, users.name, users.email, users.is_active, users.created_at"
    );
    $profileStatement->execute([$selectedId]);
    $selectedTeacher = $profileStatement->fetch();
    if ($selectedTeacher === false) {
        http_response_code(404);
        exit('حساب المعلمة المطلوب غير موجود.');
    }

    $historyStatement = db()->prepare(
        'SELECT id, title, category, status, created_at FROM experiences WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
    );
    $historyStatement->execute([$selectedId]);
    $teacherExperiences = $historyStatement->fetchAll();
}

$pageTitle = 'إدارة المستخدمين';
$pageStylesheet = 'assets/css/admin.css';
$bodyClass = 'admin-dashboard-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="admin-page">
    <div class="admin-page-head admin-page-head-summary">
        <div class="admin-page-copy"><a class="admin-back-link" href="<?= e(url('admin/index.php')) ?>">← لوحة الإدارة</a><span class="admin-eyebrow"><span aria-hidden="true">♙</span> إدارة المجتمع</span><h1 class="admin-page-title">إدارة المستخدمين</h1><p>تابعي حسابات المعلمات واطّلعي على نشاطهن داخل المنصة.</p></div>
        <div class="admin-page-summary"><span class="admin-summary-icon" aria-hidden="true">♙</span><span>عدد الحسابات</span><strong><?= number_format(count($users)) ?></strong></div>
    </div>
    <div class="admin-user-layout">
        <div class="admin-panel admin-user-form-panel">
            <div class="admin-panel-head"><div><span class="admin-panel-kicker">إدارة المجتمع</span><h2 class="admin-panel-title">الحسابات الجديدة</h2></div><span class="admin-panel-icon" aria-hidden="true">♙</span></div>
            <p class="admin-panel-description">تُنشأ الحسابات الجديدة من صفحة التسجيل العامة، ويمكنكِ من هنا متابعة الحسابات وتفعيلها أو إيقافها.</p>
            <a class="admin-button admin-button-secondary admin-button-full" href="<?= e(url('register.php')) ?>">فتح صفحة التسجيل <span aria-hidden="true">↗</span></a>
        </div>

        <div class="admin-panel admin-user-list-panel">
            <div class="admin-panel-head"><div><span class="admin-panel-kicker">إدارة الحسابات</span><h2 class="admin-panel-title">حسابات المعلمات</h2></div><span class="admin-count-badge"><?= number_format(count($users)) ?></span></div>
            <form class="admin-user-search" method="get">
                <label class="sr-only" for="user-search">ابحثي عن مستخدمة</label>
                <input class="admin-input" id="user-search" type="search" name="q" value="<?= e($search) ?>" placeholder="البحث بالاسم أو البريد الإلكتروني">
                <button class="admin-button admin-button-primary" type="submit"><span aria-hidden="true">⌕</span> بحث</button>
            </form>
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>المعلمة</th><th>عدد الخبرات</th><th>الحالة</th><th>إجراءات</th></tr></thead>
                <tbody><?php foreach ($users as $teacher): ?><tr>
                    <td><div class="admin-user-cell"><span class="admin-user-avatar" aria-hidden="true"><?= e(mb_substr($teacher['name'], 0, 1)) ?></span><span><strong><?= e($teacher['name']) ?></strong><small><?= e($teacher['email']) ?></small></span></div></td>
                    <td><span class="admin-table-count"><?= (int) $teacher['experiences_count'] ?></span></td><td><span class="admin-status-badge <?= $teacher['is_active'] ? 'admin-status-active' : 'admin-status-inactive' ?>"><?= $teacher['is_active'] ? 'نشط' : 'موقوف' ?></span></td>
                    <td><div class="admin-row-actions">
                        <a class="admin-row-link" href="<?= e(url('admin/users.php?' . http_build_query(['q' => $search, 'user_id' => $teacher['id']]))) ?>">الملف والنشاط</a>
                        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_teacher"><input type="hidden" name="user_id" value="<?= (int) $teacher['id'] ?>"><input type="hidden" name="is_active" value="<?= $teacher['is_active'] ? '0' : '1' ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><button class="admin-row-action <?= $teacher['is_active'] ? 'admin-action-danger' : 'admin-action-enable' ?>" type="submit"><?= $teacher['is_active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
                        <form method="post" onsubmit="return window.confirm('سيُحذف الحساب نهائيًا مع خبراته ومرفقاتها وتعليقاته وإعجاباته. لا يمكن التراجع عن الحذف. هل تريدين المتابعة؟')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_teacher"><input type="hidden" name="user_id" value="<?= (int) $teacher['id'] ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><button class="admin-row-action admin-action-danger" type="submit">حذف</button></form>
                    </div></td>
                </tr><?php endforeach; ?></tbody>
            </table><?php if ($users === []): ?><p class="admin-empty-state admin-empty-panel">لا توجد حسابات مطابقة للبحث.</p><?php endif; ?></div>
        </div>
    </div>

    <?php if ($selectedTeacher !== null): ?>
        <section class="admin-panel admin-profile-panel" aria-labelledby="teacher-profile-title">
            <div class="admin-panel-head"><div><span class="admin-panel-kicker">ملف المعلمة</span><h2 class="admin-panel-title" id="teacher-profile-title"><?= e($selectedTeacher['name']) ?></h2></div><span class="admin-status-badge <?= $selectedTeacher['is_active'] ? 'admin-status-active' : 'admin-status-inactive' ?>"><?= $selectedTeacher['is_active'] ? 'حساب نشط' : 'حساب موقوف' ?></span></div>
            <p class="admin-panel-description"><?= e($selectedTeacher['email']) ?> <span aria-hidden="true">·</span> تاريخ التسجيل <?= e(date('Y-m-d', strtotime($selectedTeacher['created_at']))) ?></p>
            <div class="admin-profile-stats">
                <?php foreach ([['منشورة', 'published_count'], ['قيد المراجعة', 'pending_count'], ['مخفية / مرفوضة', 'hidden_count']] as [$label, $key]): ?>
                    <div><span><?= e($label) ?></span><strong><?= (int) ($selectedTeacher[$key] ?? 0) ?></strong></div>
                <?php endforeach; ?>
            </div>
            <h3 class="admin-subtitle admin-profile-history-title">أحدث الخبرات</h3>
            <ul class="admin-history-list"><?php foreach ($teacherExperiences as $experience): ?>
                <li><a href="<?= e(url('experiences/show.php?id=' . $experience['id'])) ?>"><?= e($experience['title']) ?></a><span><?= e(category_label($experience['category'])) ?> · <?= e($experienceStatusLabels[$experience['status']]) ?> · <?= e(date('Y-m-d', strtotime($experience['created_at']))) ?></span></li>
            <?php endforeach; ?></ul>
            <?php if ($teacherExperiences === []): ?><p class="admin-empty-state">لم تنشر المعلمة أي خبرات بعد.</p><?php endif; ?>
        </section>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

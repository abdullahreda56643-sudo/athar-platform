<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$statuses = ['published', 'pending', 'hidden'];
$categories = array_column(experience_categories(), 'category_key');
$statusLabels = ['published' => 'منشورة', 'pending' => 'قيد المراجعة', 'hidden' => 'مخفية / مرفوضة'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['delete_experience']) ? 'delete_experience' : text_input($_POST, 'action');
    $id = filter_input(INPUT_POST, 'experience_id', FILTER_VALIDATE_INT);

    if ($action === 'delete_experience') {
        if (!$id) {
            http_response_code(400);
            exit('طلب حذف الخبرة غير صالح.');
        }

        $connection = db();
        $connection->beginTransaction();
        try {
            $find = $connection->prepare('SELECT title FROM experiences WHERE id = ? FOR UPDATE');
            $find->execute([$id]);
            $experience = $find->fetch();
            if ($experience === false) {
                $connection->rollBack();
                http_response_code(404);
                exit('الخبرة المطلوبة غير موجودة.');
            }

            $attachmentsQuery = $connection->prepare('SELECT stored_name FROM experience_attachments WHERE experience_id = ?');
            $attachmentsQuery->execute([$id]);
            $storedNames = array_column($attachmentsQuery->fetchAll(), 'stored_name');
            $delete = $connection->prepare('DELETE FROM experiences WHERE id = ?');
            $delete->execute([$id]);
            log_admin_activity('experience_deleted', 'experience', $id, 'حذف الخبرة: ' . mb_substr($experience['title'], 0, 220));
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
                ? 'تم حذف الخبرة ومرفقاتها وتعليقاتها وإعجاباتها.'
                : 'حُذفت الخبرة، لكن تعذّر حذف بعض ملفاتها المرفقة. راجعي سجل الخادم.'
        );
        redirect('admin/review.php');
    }

    if ($action !== 'update_experience') {
        http_response_code(400);
        exit('طلب مراجعة غير صالح.');
    }
    $status = text_input($_POST, 'status');
    $reason = text_input($_POST, 'rejection_reason');

    if (!$id || !in_array($status, $statuses, true) || mb_strlen($reason) > 1000) {
        http_response_code(400);
        exit('طلب مراجعة غير صالح.');
    }
    if ($status === 'hidden' && $reason === '') {
        flash('error', 'أضيفي سبب الإخفاء أو الرفض قبل الحفظ.');
        redirect('admin/review.php');
    }

    $find = db()->prepare('SELECT title, status, rejection_reason FROM experiences WHERE id = ?');
    $find->execute([$id]);
    $experience = $find->fetch();
    if ($experience === false) {
        http_response_code(404);
        exit('المشاركة المطلوبة غير موجودة.');
    }

    $newReason = $status === 'hidden' ? $reason : null;
    if ($experience['status'] !== $status || $experience['rejection_reason'] !== $newReason) {
        $update = db()->prepare('UPDATE experiences SET status = ?, rejection_reason = ? WHERE id = ?');
        $update->execute([$status, $newReason, $id]);
        $action = $status === 'hidden' ? 'experience_rejected' : 'experience_status_changed';
        $details = $status === 'hidden'
            ? 'إخفاء/رفض المشاركة: ' . mb_substr($experience['title'], 0, 100) . ' · السبب: ' . mb_substr($reason, 0, 100)
            : 'تغيير حالة المشاركة إلى ' . $statusLabels[$status] . ': ' . mb_substr($experience['title'], 0, 150);
        log_admin_activity($action, 'experience', $id, $details);
    }

    flash('success', 'تم تحديث حالة المشاركة.');
    redirect('admin/review.php');
}

$search = text_input($_GET, 'q');
$statusFilter = text_input($_GET, 'status');
$categoryFilter = text_input($_GET, 'category');
$dateFrom = text_input($_GET, 'from');
$dateTo = text_input($_GET, 'to');
$conditions = [];
$parameters = [];

if (($statusFilter !== '' && !in_array($statusFilter, $statuses, true))
    || ($categoryFilter !== '' && !in_array($categoryFilter, $categories, true))) {
    http_response_code(400);
    exit('تصفية المشاركات غير صالحة.');
}

if (in_array($statusFilter, $statuses, true)) {
    $conditions[] = 'experiences.status = ?';
    $parameters[] = $statusFilter;
}
if (in_array($categoryFilter, $categories, true)) {
    $conditions[] = 'experiences.category = ?';
    $parameters[] = $categoryFilter;
}
if ($search !== '') {
    $conditions[] = '(experiences.title LIKE ? OR users.name LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($parameters, $needle, $needle);
}
foreach (['from' => $dateFrom, 'to' => $dateTo] as $field => $dateValue) {
    if ($dateValue !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) !== 1) {
        http_response_code(400);
        exit('تاريخ التصفية غير صالح.');
    }
    if ($dateValue !== '') {
        [$year, $month, $day] = array_map('intval', explode('-', $dateValue));
        if (!checkdate($month, $day, $year)) {
            http_response_code(400);
            exit('تاريخ التصفية غير صالح.');
        }
        $conditions[] = 'DATE(experiences.created_at) ' . ($field === 'from' ? '>=' : '<=') . ' ?';
        $parameters[] = $dateValue;
    }
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    http_response_code(400);
    exit('يجب أن يكون تاريخ البداية قبل تاريخ النهاية.');
}

$whereClause = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
$statement = db()->prepare(
    'SELECT experiences.id, experiences.title, experiences.category, experiences.status,
            experiences.rejection_reason, experiences.created_at, users.name AS author
     FROM experiences JOIN users ON users.id = experiences.user_id
     ' . $whereClause . '
     ORDER BY (experiences.status = ?) DESC, experiences.created_at DESC'
);
$statement->execute(array_merge($parameters, ['pending']));
$experiences = $statement->fetchAll();
$pageTitle = 'مراجعة وإدارة الخبرات';
$pageStylesheet = 'assets/css/admin.css';
$bodyClass = 'admin-dashboard-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="admin-page">
    <div class="admin-page-head admin-page-head-summary">
        <div class="admin-page-copy"><a class="admin-back-link" href="<?= e(url('admin/index.php')) ?>">← لوحة الإدارة</a><span class="admin-eyebrow"><span aria-hidden="true">✦</span> تنظيم المحتوى</span><h1 class="admin-page-title">مراجعة وإدارة الخبرات</h1><p>راجعي المشاركات، حددي حالتها، وابحثي عنها بسهولة.</p></div>
        <div class="admin-page-summary"><span class="admin-summary-icon" aria-hidden="true">◷</span><span>إجمالي النتائج</span><strong><?= number_format(count($experiences)) ?></strong></div>
    </div>
    <form class="admin-panel admin-filter-panel" method="get">
        <div class="admin-panel-head"><div><span class="admin-panel-kicker">تصفية النتائج</span><h2 class="admin-panel-title">ابحثي عن مشاركة</h2></div></div>
        <div class="admin-filter-fields">
            <label>بحث بالعنوان أو اسم المعلمة<input class="admin-input" type="search" name="q" value="<?= e($search) ?>" placeholder="اكتبي كلمة للبحث"></label>
            <label>الحالة<select class="admin-input" name="status"><option value="">كل الحالات</option><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= e($statusLabels[$option]) ?></option><?php endforeach; ?></select></label>
            <label>المجال<select class="admin-input" name="category"><option value="">كل المجالات</option><?php foreach ($categories as $option): ?><option value="<?= e($option) ?>" <?= $categoryFilter === $option ? 'selected' : '' ?>><?= e(category_label($option)) ?></option><?php endforeach; ?></select></label>
            <label>من تاريخ<input class="admin-input" type="date" name="from" value="<?= e($dateFrom) ?>"></label>
            <label>إلى تاريخ<input class="admin-input" type="date" name="to" value="<?= e($dateTo) ?>"></label>
        </div>
        <div class="admin-filter-actions"><button class="admin-button admin-button-primary" type="submit">تطبيق التصفية <span aria-hidden="true">⌕</span></button><a class="admin-button admin-button-secondary" href="<?= e(url('admin/review.php')) ?>">مسح التصفية</a></div>
    </form>
    <div class="admin-results-heading"><h2>قائمة الخبرات</h2><span><?= number_format(count($experiences)) ?> مشاركة</span></div>
    <div class="admin-record-list">
        <?php foreach ($experiences as $experience): ?>
            <article class="admin-record-card">
                <div class="admin-record-details">
                    <div class="admin-record-labels"><span class="admin-category-tag"><?= e(category_label($experience['category'])) ?></span><span class="admin-status-badge admin-status-<?= e($experience['status']) ?>"><?= e($statusLabels[$experience['status']]) ?></span></div>
                    <h3 class="admin-record-title"><a href="<?= e(url('experiences/show.php?id=' . $experience['id'])) ?>"><?= e($experience['title']) ?></a></h3>
                    <p class="admin-record-meta"><span><?= e($experience['author']) ?></span><span aria-hidden="true">·</span><span><?= e(date('Y-m-d', strtotime($experience['created_at']))) ?></span></p>
                    <?php if ($experience['rejection_reason'] !== null): ?><p class="admin-rejection-note"><strong>سبب الإخفاء / الرفض:</strong> <?= e($experience['rejection_reason']) ?></p><?php endif; ?>
                </div>
                <div class="admin-record-divider"></div>
                <form class="admin-review-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="experience_id" value="<?= (int) $experience['id'] ?>">
                    <input type="hidden" name="action" value="update_experience">
                    <label>تحديث الحالة<span class="sr-only"> للمشاركة <?= e($experience['title']) ?></span><select class="admin-input" name="status"><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>" <?= $experience['status'] === $option ? 'selected' : '' ?>><?= e($statusLabels[$option]) ?></option><?php endforeach; ?></select></label>
                    <label>سبب الإخفاء أو الرفض<span class="sr-only"> للمشاركة <?= e($experience['title']) ?></span><input class="admin-input" maxlength="1000" name="rejection_reason" placeholder="اكتبي السبب عند الإخفاء أو الرفض" value="<?= e($experience['rejection_reason']) ?>"></label>
                    <div class="admin-review-actions"><button class="admin-button admin-button-primary" type="submit">حفظ التغييرات</button><button class="admin-button admin-button-danger" type="submit" name="delete_experience" value="1" onclick="return window.confirm('سيتم حذف الخبرة ومرفقاتها وتعليقاتها وإعجاباتها نهائيًا. هل تريدين المتابعة؟')">حذف الخبرة</button></div>
                </form>
            </article>
        <?php endforeach; ?>
        <?php if ($experiences === []): ?><p class="admin-empty-state admin-empty-panel">لا توجد خبرات مطابقة للتصفية. جرّبي تغيير خيارات البحث.</p><?php endif; ?>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$counts = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM experiences WHERE status = 'published') AS published,
        (SELECT COUNT(*) FROM experiences WHERE status = 'pending') AS pending,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 0) AS inactive_teachers"
)->fetch();

$urgentExperiences = db()->query(
    "SELECT id, title, created_at FROM experiences WHERE status = 'pending' ORDER BY created_at ASC LIMIT 5"
)->fetchAll();
$inactiveTeachers = db()->query(
    "SELECT id, name, created_at FROM users WHERE role = 'teacher' AND is_active = 0 ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$activity = db()->query(
    "SELECT activity_type, title, description, created_at, target_url FROM (
        SELECT 'registration' AS activity_type, users.name AS title,
               'تسجيل مستخدمة جديدة' AS description, users.created_at,
               CONCAT('admin/users.php?user_id=', users.id) AS target_url
        FROM users WHERE users.role = 'teacher'
        UNION ALL
        SELECT 'experience' AS activity_type, experiences.title,
               CONCAT('خبرة جديدة من ', users.name) AS description, experiences.created_at,
               'admin/review.php' AS target_url
        FROM experiences JOIN users ON users.id = experiences.user_id
        UNION ALL
        SELECT 'admin_action' AS activity_type, admin_activity.details AS title,
               CONCAT('إجراء إداري · ', COALESCE(users.name, 'حساب إداري محذوف')) AS description,
               admin_activity.created_at, 'admin/index.php' AS target_url
        FROM admin_activity LEFT JOIN users ON users.id = admin_activity.admin_id
    ) AS recent_activity
    ORDER BY created_at DESC
    LIMIT 12"
)->fetchAll();

$pageTitle = 'لوحة الإدارة';
$pageStylesheet = 'assets/css/admin.css';
$bodyClass = 'admin-dashboard-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="admin-page">
    <div class="admin-page-head">
        <div class="admin-page-copy"><span class="admin-eyebrow"><span aria-hidden="true">✦</span> إدارة المجتمع</span><h1 class="admin-page-title">لوحة الإدارة</h1><p>نظرة شاملة على الخبرات والمستخدمين وآخر ما يجري في المنصة.</p></div>
        <nav class="admin-page-actions" aria-label="روابط الإدارة السريعة">
            <a class="admin-button admin-button-primary" href="<?= e(url('admin/review.php')) ?>"><span aria-hidden="true">✓</span> مراجعة الخبرات</a>
            <a class="admin-button admin-button-secondary" href="<?= e(url('admin/users.php')) ?>"><span aria-hidden="true">♙</span> إدارة المستخدمين</a>
            <a class="admin-button admin-button-secondary" href="<?= e(url('admin/categories.php')) ?>"><span aria-hidden="true">◇</span> إدارة المجالات</a>
            <a class="admin-button admin-button-secondary" href="<?= e(url('admin/settings.php')) ?>"><span aria-hidden="true">⚙</span> بيانات الدخول</a>
        </nav>
    </div>

    <div class="admin-stats">
        <?php foreach ([['إجمالي المستخدمين', 'users'], ['الخبرات المنشورة', 'published'], ['بانتظار المراجعة', 'pending']] as [$label, $key]): ?>
            <article class="admin-stat-card admin-stat-<?= e($key) ?>">
                <span class="admin-stat-icon" aria-hidden="true"><?= $key === 'users' ? '♙' : ($key === 'published' ? '✧' : '◷') ?></span>
                <div><p><?= e($label) ?></p><strong><?= number_format((int) $counts[$key]) ?></strong></div>
                <span class="admin-stat-decoration" aria-hidden="true"></span>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="admin-content-grid">
        <section class="admin-panel" aria-labelledby="urgent-title">
            <div class="admin-panel-head"><div><span class="admin-panel-kicker">متابعة يومية</span><h2 class="admin-panel-title" id="urgent-title">مهام تحتاج إلى متابعة</h2></div><a class="admin-text-link" href="<?= e(url('admin/review.php')) ?>">عرض الكل <span aria-hidden="true">←</span></a></div>
            <div class="admin-task-group">
                <h3 class="admin-subtitle">خبرات تنتظر المراجعة <span class="admin-count-badge admin-count-warning"><?= number_format((int) $counts['pending']) ?></span></h3>
                <?php if ($urgentExperiences === []): ?><p class="admin-empty-state">لا توجد خبرات بانتظار المراجعة.</p><?php else: ?>
                    <ul class="admin-activity-list"><?php foreach ($urgentExperiences as $experience): ?><li><span class="admin-list-marker admin-marker-warning" aria-hidden="true">◷</span><div><a href="<?= e(url('admin/review.php?status=pending')) ?>"><?= e($experience['title']) ?></a><small><?= e(date('Y-m-d', strtotime($experience['created_at']))) ?></small></div></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
            <div class="admin-task-group admin-task-group-separated">
                <h3 class="admin-subtitle">حسابات موقوفة <span class="admin-count-badge"><?= number_format((int) $counts['inactive_teachers']) ?></span></h3>
                <?php if ($inactiveTeachers === []): ?><p class="admin-empty-state">لا توجد حسابات موقوفة.</p><?php else: ?>
                    <ul class="admin-activity-list"><?php foreach ($inactiveTeachers as $teacher): ?><li><span class="admin-list-marker" aria-hidden="true">♙</span><div><a href="<?= e(url('admin/users.php?' . http_build_query(['user_id' => $teacher['id']]))) ?>"><?= e($teacher['name']) ?></a><small>تسجيل <?= e(date('Y-m-d', strtotime($teacher['created_at']))) ?></small></div></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-panel" aria-labelledby="activity-title">
            <div class="admin-panel-head"><div><span class="admin-panel-kicker">آخر المستجدات</span><h2 class="admin-panel-title" id="activity-title">أحدث النشاطات</h2></div><span class="admin-live-indicator"><span></span> محدث</span></div>
            <?php if ($activity === []): ?><p class="admin-empty-state">لا توجد نشاطات حتى الآن.</p><?php else: ?>
                <ol class="admin-activity-list admin-recent-list">
                    <?php foreach ($activity as $item): ?>
                        <li>
                            <span class="admin-list-marker <?= $item['activity_type'] === 'registration' ? 'admin-marker-user' : ($item['activity_type'] === 'experience' ? 'admin-marker-experience' : 'admin-marker-action') ?>" aria-hidden="true"><?= $item['activity_type'] === 'registration' ? '♙' : ($item['activity_type'] === 'experience' ? '✧' : '✓') ?></span>
                            <div><a href="<?= e(url($item['target_url'])) ?>"><?= e($item['title']) ?></a><p><?= e($item['description']) ?></p><small><?= e(date('Y-m-d · H:i', strtotime($item['created_at']))) ?></small></div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

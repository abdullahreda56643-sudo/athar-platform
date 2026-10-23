<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

if ((current_user()['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('هذه الصفحة مخصصة لحسابات المعلمات.');
}

$statement = db()->prepare(
    'SELECT id, title, content, category, status, rejection_reason, created_at, updated_at
     FROM experiences
     WHERE user_id = ?
     ORDER BY updated_at DESC, id DESC'
);
$statement->execute([current_user()['id']]);
$experiences = $statement->fetchAll();
$statusLabels = [
    'published' => 'منشورة',
    'pending' => 'قيد المراجعة',
    'hidden' => 'مخفية',
];

$pageTitle = 'خبراتي';
$pageStylesheet = 'assets/css/my-experiences.css';
require ROOT_PATH . '/includes/header.php';
?>
<section class="my-experiences page-container" aria-labelledby="my-experiences-title">
    <header class="my-experiences-header">
        <div>
            <span class="my-experiences-kicker">مساحتكِ المهنية</span>
            <h1 id="my-experiences-title">خبراتي</h1>
            <p>تابعي الخبرات التي شاركتِها، واطّلعي على حالتها، أو عدّليها واحذفيها.</p>
        </div>
        <a class="my-experiences-add" href="<?= e(url('experiences/create.php')) ?>"><span aria-hidden="true">＋</span> أضيفي خبرة جديدة</a>
    </header>

    <div class="my-experiences-summary">
        <span class="my-experiences-summary-icon" aria-hidden="true">✦</span>
        <span>إجمالي خبراتي</span>
        <strong><?= number_format(count($experiences)) ?></strong>
    </div>

    <?php if ($experiences === []): ?>
        <div class="my-experiences-empty">
            <span aria-hidden="true">✧</span>
            <h2>لم تشاركي أي خبرة بعد</h2>
            <p>ابدئي بمشاركة تجربة تعليمية قد تلهم زميلاتكِ وتثري مجتمعنا المهني.</p>
            <a class="my-experiences-add" href="<?= e(url('experiences/create.php')) ?>">شاركي خبرتكِ الأولى <span aria-hidden="true">←</span></a>
        </div>
    <?php else: ?>
        <div class="my-experiences-list">
            <?php foreach ($experiences as $experience): ?>
                <article class="my-experience-card">
                    <div class="my-experience-topline">
                        <span class="my-experience-category"><?= e(category_label($experience['category'])) ?></span>
                        <span class="my-experience-status my-experience-status-<?= e($experience['status']) ?>">
                            <span aria-hidden="true"></span><?= e($statusLabels[$experience['status']] ?? 'غير معروفة')
                            ?></span>
                    </div>
                    <h2><?= e($experience['title']) ?></h2>
                    <p class="my-experience-excerpt"><?= e(mb_substr($experience['content'], 0, 220)) ?><?= mb_strlen($experience['content']) > 220 ? '…' : '' ?></p>
                    <?php if ($experience['status'] === 'hidden' && $experience['rejection_reason'] !== null): ?>
                        <p class="my-experience-note"><strong>ملاحظة الإدارة:</strong> <?= e($experience['rejection_reason']) ?></p>
                    <?php endif; ?>
                    <div class="my-experience-footer">
                        <span>آخر تحديث: <?= e(date('Y/m/d', strtotime($experience['updated_at']))) ?></span>
                        <div class="my-experience-actions">
                            <a class="my-experience-view" href="<?= e(url('experiences/show.php?id=' . (int) $experience['id'])) ?>">عرض الخبرة</a>
                            <a class="my-experience-edit" href="<?= e(url('experiences/create.php?id=' . (int) $experience['id'])) ?>">تعديل</a>
                            <form method="post" action="<?= e(url('experiences/show.php?id=' . (int) $experience['id'] . '&from=mine')) ?>" onsubmit="return window.confirm('سيتم حذف الخبرة ومرفقاتها وتعليقاتها وإعجاباتها نهائيًا. هل تريدين المتابعة؟')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_experience">
                                <button class="my-experience-delete" type="submit">حذف</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

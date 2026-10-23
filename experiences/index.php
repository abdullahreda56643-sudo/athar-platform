<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$search = text_input($_GET, 'q');
$category = text_input($_GET, 'category');
$sort = text_input($_GET, 'sort');
$pageInput = text_input($_GET, 'page');
$page = ctype_digit($pageInput) ? max(1, (int) $pageInput) : 1;
$perPage = 8;
$categories = array_column(experience_categories(), 'category_key');
$conditions = ["experiences.status = 'published'"];
$parameters = [];

if ($search !== '') {
    $conditions[] = '(experiences.title LIKE ? OR experiences.content LIKE ? OR users.name LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($parameters, $needle, $needle, $needle);
}
if (in_array($category, $categories, true)) {
    $conditions[] = 'experiences.category = ?';
    $parameters[] = $category;
}

$orderBy = match ($sort) {
    'oldest' => 'experiences.created_at ASC',
    'popular' => '(SELECT COUNT(*) FROM experience_likes WHERE experience_likes.experience_id = experiences.id) DESC, experiences.created_at DESC',
    default => 'experiences.created_at DESC',
};

$whereClause = implode(' AND ', $conditions);
$countStatement = db()->prepare(
    'SELECT COUNT(*)
     FROM experiences JOIN users ON users.id = experiences.user_id
     WHERE ' . $whereClause
);
$countStatement->execute($parameters);
$totalExperiences = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalExperiences / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$visiblePages = [1];

if ($totalPages > 1) {
    $startPage = max(2, $page - 2);
    $endPage = min($totalPages - 1, $page + 2);
    if ($page <= 3) {
        $endPage = min($totalPages - 1, 4);
    } elseif ($page >= $totalPages - 2) {
        $startPage = max(2, $totalPages - 3);
    }

    for ($visiblePage = $startPage; $visiblePage <= $endPage; $visiblePage++) {
        $visiblePages[] = $visiblePage;
    }

    $visiblePages[] = $totalPages;
}

$paginationQuery = array_filter(
    ['q' => $search, 'category' => $category, 'sort' => $sort],
    static fn (string $value): bool => $value !== ''
);
$pageUrl = static function (int $targetPage) use ($paginationQuery): string {
    return url('experiences/index.php?' . http_build_query($paginationQuery + ['page' => $targetPage]));
};

$statement = db()->prepare(
    'SELECT experiences.id, experiences.title, experiences.content, experiences.category, experiences.created_at,
            users.name AS author,
            (SELECT COUNT(*) FROM experience_likes WHERE experience_likes.experience_id = experiences.id) AS likes_count,
            (SELECT COUNT(*) FROM comments WHERE comments.experience_id = experiences.id AND comments.status = ?) AS comments_count
     FROM experiences JOIN users ON users.id = experiences.user_id
     WHERE ' . $whereClause . '
     ORDER BY ' . $orderBy . '
     LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$statement->execute(array_merge(['visible'], $parameters));
$experiences = $statement->fetchAll();

$categoryCounts = db()->query(
    "SELECT category, COUNT(*) AS experiences_count
     FROM experiences
     WHERE status = 'published'
     GROUP BY category
     ORDER BY experiences_count DESC, category ASC"
)->fetchAll();

$communitySummary = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM experiences WHERE status = 'published') AS experiences_count,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1) AS teachers_count"
)->fetch();

$pageTitle = 'بنك الخبرات';
require ROOT_PATH . '/includes/header.php';
?>
<div class="experience-bank">
    <section class="bank-heading" aria-labelledby="bank-title">
        <div class="bank-container bank-heading-inner">
            <div>
                <p class="bank-kicker"><span aria-hidden="true">✦</span> <?= e(SCHOOL_NAME) ?> · مجتمعنا المهني</p>
                <h1 id="bank-title">بنك الخبرات والممارسات التربوية</h1>
                <p>مساحة ملهمة نتشارك فيها التجارب الناجحة ونطوّر ممارساتنا التعليمية معاً.</p>
            </div>
            <a class="bank-add-button" href="<?= e(url('experiences/create.php')) ?>"><span aria-hidden="true">＋</span> إضافة خبرة جديدة</a>
        </div>
    </section>

    <div class="bank-container bank-content">
        <form class="bank-filters" method="get" action="<?= e(url('experiences/index.php')) ?>">
            <label class="bank-search">
                <span class="bank-search-icon" aria-hidden="true">⌕</span>
                <span class="sr-only">ابحثي في الخبرات</span>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="ابحثي في الخبرات، الممارسات، أو أسماء المعلمات...">
                <button type="submit">بحث</button>
            </label>
            <div class="bank-filter-row">
                <nav class="bank-category-list" aria-label="تصفية حسب المجال">
                    <a class="bank-category-pill<?= $category === '' ? ' is-selected' : '' ?>" href="<?= e(url('experiences/index.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) ?>">كل الخبرات <span><?= number_format((int) $communitySummary['experiences_count']) ?></span></a>
                    <?php foreach ($categories as $option): ?>
                        <?php
                        $query = array_filter(['q' => $search, 'category' => $option, 'sort' => $sort], static fn (string $value): bool => $value !== '');
                        $categoryCount = 0;
                        foreach ($categoryCounts as $countRow) {
                            if ($countRow['category'] === $option) {
                                $categoryCount = (int) $countRow['experiences_count'];
                                break;
                            }
                        }
                        ?>
                        <a class="bank-category-pill<?= $category === $option ? ' is-selected' : '' ?>" href="<?= e(url('experiences/index.php?' . http_build_query($query))) ?>"><?= e(category_label($option)) ?> <span><?= $categoryCount ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <label class="bank-sort">
                    <span>الترتيب</span>
                    <select name="sort" onchange="this.form.requestSubmit()">
                        <option value="">الأحدث أولاً</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>الأقدم أولاً</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>الأكثر إعجاباً</option>
                    </select>
                    <input type="hidden" name="q" value="<?= e($search) ?>">
                    <input type="hidden" name="category" value="<?= e($category) ?>">
                </label>
            </div>
        </form>

        <div class="bank-layout">
            <aside class="bank-sidebar" aria-label="معلومات وموارد بنك الخبرات">
                <section class="bank-side-card">
                    <div class="bank-side-title"><h2>مجالات الخبرة</h2><span>حسب المشاركات</span></div>
                    <ul class="bank-topic-list">
                        <?php foreach ($categoryCounts as $topic): ?>
                            <li><a href="<?= e(url('experiences/index.php?' . http_build_query(array_filter(['q' => $search, 'category' => $topic['category'], 'sort' => $sort], static fn (string $value): bool => $value !== '')))) ?>"><span><?= e(category_label($topic['category'])) ?></span><strong><?= number_format((int) $topic['experiences_count']) ?></strong></a></li>
                        <?php endforeach; ?>
                        <?php if ($categoryCounts === []): ?><li class="bank-empty-topic">ستظهر المجالات هنا مع نشر الخبرات.</li><?php endif; ?>
                    </ul>
                </section>

                <section class="bank-impact-card">
                    <h2>أثر المعرفة في مدرستنا</h2>
                    <div class="bank-impact-stats">
                        <div><strong><?= number_format((int) $communitySummary['experiences_count']) ?></strong><span>خبرة منشورة</span></div>
                        <div><strong><?= number_format((int) $communitySummary['teachers_count']) ?></strong><span>معلمة مشاركة</span></div>
                    </div>
                </section>
            </aside>

            <section class="bank-results" aria-label="الخبرات المنشورة">
                <div class="bank-results-heading">
                    <div>
                        <p>من واقع تجاربنا التعليمية</p>
                        <h2><?= $search !== '' || in_array($category, $categories, true) ? 'الخبرات المطابقة' : 'أحدث الخبرات والممارسات' ?></h2>
                    </div>
                    <span><?= number_format($totalExperiences) ?> <?= $totalExperiences === 1 ? 'خبرة' : 'خبرات' ?></span>
                </div>

                <?php if ($experiences === []): ?>
                    <div class="bank-empty-state">
                        <span aria-hidden="true">✧</span>
                        <h2><?= $search !== '' || in_array($category, $categories, true) ? 'لم نعثر على خبرات مطابقة' : 'كوني أول من يشارك خبرة' ?></h2>
                        <p><?= $search !== '' || in_array($category, $categories, true) ? 'جرّبي كلمات بحث أخرى أو اختاري مجالاً مختلفاً.' : 'ابدئي بإضافة ممارسة ناجحة لتكون مصدر إلهام لزميلاتكِ.' ?></p>
                        <?php if ($search !== '' || in_array($category, $categories, true)): ?>
                            <a class="bank-add-button" href="<?= e(url('experiences/index.php')) ?>">عرض جميع الخبرات</a>
                        <?php else: ?>
                            <a class="bank-add-button" href="<?= e(url('experiences/create.php')) ?>">＋ إضافة خبرة جديدة</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="bank-experience-list">
                        <?php foreach ($experiences as $experience): ?>
                            <article class="bank-experience-card">
                                <div class="bank-card-topline">
                                    <div class="bank-card-author">
                                        <span class="bank-avatar bank-avatar-small" aria-hidden="true"><?= e(mb_substr($experience['author'], 0, 1)) ?></span>
                                        <div><strong><?= e($experience['author']) ?></strong><small>مشاركة مجتمعية · <?= e(date('Y-m-d', strtotime($experience['created_at']))) ?></small></div>
                                    </div>
                                    <span class="bank-card-category"><?= e(category_label($experience['category'])) ?></span>
                                </div>
                                <h3><a href="<?= e(url('experiences/show.php?id=' . $experience['id'])) ?>"><?= e($experience['title']) ?></a></h3>
                                <p class="bank-card-excerpt"><?= e(mb_substr($experience['content'], 0, 310)) ?><?= mb_strlen($experience['content']) > 310 ? '…' : '' ?></p>
                                <div class="bank-card-bottom">
                                    <div class="bank-card-stats">
                                        <span aria-label="<?= (int) $experience['likes_count'] ?> إعجاب">♡ <?= number_format((int) $experience['likes_count']) ?> إعجاب</span>
                                        <span aria-label="<?= (int) $experience['comments_count'] ?> تعليق">▢ <?= number_format((int) $experience['comments_count']) ?> تعليق</span>
                                    </div>
                                    <a class="bank-detail-link" href="<?= e(url('experiences/show.php?id=' . $experience['id'])) ?>">اقرأ المزيد <span aria-hidden="true">←</span></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="bank-pagination-summary">
                        <span>عرض <?= number_format($offset + 1) ?>–<?= number_format($offset + count($experiences)) ?> من <?= number_format($totalExperiences) ?> خبرة</span>
                        <span>الصفحة <?= number_format($page) ?> من <?= number_format($totalPages) ?></span>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <nav class="bank-pagination" aria-label="صفحات الخبرات">
                            <?php if ($page > 1): ?>
                                <a class="bank-page-control" href="<?= e($pageUrl(1)) ?>" aria-label="الصفحة الأولى"><span aria-hidden="true">«</span><span class="bank-page-control-label">الأولى</span></a>
                                <a class="bank-page-control" href="<?= e($pageUrl($page - 1)) ?>" rel="prev"><span aria-hidden="true">‹</span><span class="bank-page-control-label">السابقة</span></a>
                            <?php else: ?>
                                <span class="bank-page-control is-disabled" aria-disabled="true"><span aria-hidden="true">«</span><span class="bank-page-control-label">الأولى</span></span>
                                <span class="bank-page-control is-disabled" aria-disabled="true"><span aria-hidden="true">‹</span><span class="bank-page-control-label">السابقة</span></span>
                            <?php endif; ?>

                            <div class="bank-page-numbers">
                                <?php foreach ($visiblePages as $index => $visiblePage): ?>
                                    <?php if ($index > 0 && $visiblePage - $visiblePages[$index - 1] > 1): ?>
                                        <span class="bank-page-ellipsis" aria-hidden="true">…</span>
                                    <?php endif; ?>
                                    <?php if ($visiblePage === $page): ?>
                                        <span class="bank-page-number is-current" aria-current="page"><?= number_format($visiblePage) ?></span>
                                    <?php else: ?>
                                        <a class="bank-page-number" href="<?= e($pageUrl($visiblePage)) ?>"><?= number_format($visiblePage) ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($page < $totalPages): ?>
                                <a class="bank-page-control" href="<?= e($pageUrl($page + 1)) ?>" rel="next"><span class="bank-page-control-label">التالية</span><span aria-hidden="true">›</span></a>
                                <a class="bank-page-control" href="<?= e($pageUrl($totalPages)) ?>" aria-label="الصفحة الأخيرة"><span class="bank-page-control-label">الأخيرة</span><span aria-hidden="true">»</span></a>
                            <?php else: ?>
                                <span class="bank-page-control is-disabled" aria-disabled="true"><span class="bank-page-control-label">التالية</span><span aria-hidden="true">›</span></span>
                                <span class="bank-page-control is-disabled" aria-disabled="true"><span class="bank-page-control-label">الأخيرة</span><span aria-hidden="true">»</span></span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

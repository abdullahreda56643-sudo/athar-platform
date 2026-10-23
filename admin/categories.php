<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$icons = ['◉', '▤', '⌘', '◇', '✧', '✦', '☆', '✎', '♧', '⚙'];
$error = null;
$formName = '';
$formIcon = '✧';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($editId === false) {
    http_response_code(400);
    exit('معرّف المجال غير صالح.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = text_input($_POST, 'action');
    $idValue = $_POST['category_id'] ?? null;
    $categoryId = filter_var($idValue, FILTER_VALIDATE_INT);
    $formName = text_input($_POST, 'name');
    $formIcon = text_input($_POST, 'icon');

    if (!in_array($action, ['create', 'update', 'delete'], true)) {
        http_response_code(400);
        exit('طلب إدارة المجالات غير صالح.');
    }
    if ($action !== 'create' && (!$categoryId || $categoryId < 1)) {
        http_response_code(400);
        exit('معرّف المجال غير صالح.');
    }
    if ($action === 'update') {
        $editId = $categoryId;
    }

    if ($action === 'delete') {
        $connection = db();
        $findCategory = $connection->prepare('SELECT category_key, name FROM experience_categories WHERE id = ?');
        $findCategory->execute([$categoryId]);
        $category = $findCategory->fetch();
        if ($category === false) {
            http_response_code(404);
            exit('المجال المطلوب غير موجود.');
        }

        $countExperiences = $connection->prepare('SELECT COUNT(*) FROM experiences WHERE category = ?');
        $countExperiences->execute([$category['category_key']]);
        if ((int) $countExperiences->fetchColumn() > 0) {
            flash('error', 'لا يمكن حذف هذا المجال لارتباطه بخبرات موجودة. عدّلي اسمه بدلاً من ذلك.');
            redirect('admin/categories.php');
        }
        if ((int) $connection->query('SELECT COUNT(*) FROM experience_categories')->fetchColumn() <= 1) {
            flash('error', 'يجب الإبقاء على مجال واحد على الأقل.');
            redirect('admin/categories.php');
        }

        try {
            $delete = $connection->prepare('DELETE FROM experience_categories WHERE id = ?');
            $delete->execute([$categoryId]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }
            flash('error', 'تعذر حذف المجال لأنه أصبح مرتبطاً بخبرة. حدّثي الصفحة وحاولي مرة أخرى.');
            redirect('admin/categories.php');
        }

        log_admin_activity('category_deleted', 'category', $categoryId, 'حذف المجال التربوي: ' . $category['name']);
        flash('success', 'تم حذف المجال بنجاح.');
        redirect('admin/categories.php');
    }

    if (mb_strlen($formName) < 2 || mb_strlen($formName) > 100) {
        $error = 'اكتبي اسم المجال بين حرفين و100 حرف.';
    } elseif (!in_array($formIcon, $icons, true)) {
        $error = 'اختاري رمزاً متاحاً للمجال.';
    } else {
        $duplicate = db()->prepare(
            'SELECT id FROM experience_categories WHERE name = ? AND id <> ? LIMIT 1'
        );
        $duplicate->execute([$formName, $action === 'update' ? $categoryId : 0]);
        if ($duplicate->fetchColumn() !== false) {
            $error = 'يوجد مجال بهذا الاسم بالفعل.';
        }
    }

    if ($error === null && $action === 'update') {
        $findCategory = db()->prepare('SELECT name FROM experience_categories WHERE id = ?');
        $findCategory->execute([$categoryId]);
        $existingCategory = $findCategory->fetch();
        if ($existingCategory === false) {
            http_response_code(404);
            exit('المجال المطلوب غير موجود.');
        }

        $update = db()->prepare('UPDATE experience_categories SET name = ?, icon = ? WHERE id = ?');
        $update->execute([$formName, $formIcon, $categoryId]);
        log_admin_activity('category_updated', 'category', $categoryId, 'تعديل المجال التربوي: ' . $formName);
        flash('success', 'تم تحديث المجال بنجاح.');
        redirect('admin/categories.php');
    }

    if ($error === null) {
        $categoryKey = 'category_' . bin2hex(random_bytes(12));
        $create = db()->prepare(
            'INSERT INTO experience_categories (category_key, name, icon, sort_order) VALUES (?, ?, ?, ?)'
        );
        $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM experience_categories')->fetchColumn();
        $create->execute([$categoryKey, $formName, $formIcon, $sortOrder]);
        $newId = (int) db()->lastInsertId();
        log_admin_activity('category_created', 'category', $newId, 'إضافة المجال التربوي: ' . $formName);
        flash('success', 'تمت إضافة المجال بنجاح.');
        redirect('admin/categories.php');
    }
}

$editingCategory = null;
if ($editId !== null) {
    $findCategory = db()->prepare('SELECT id, name, icon FROM experience_categories WHERE id = ?');
    $findCategory->execute([$editId]);
    $editingCategory = $findCategory->fetch();
    if ($editingCategory === false) {
        http_response_code(404);
        exit('المجال المطلوب غير موجود.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formName = $editingCategory['name'];
        $formIcon = $editingCategory['icon'];
    }
}

$categories = db()->query(
    'SELECT experience_categories.id, experience_categories.category_key, experience_categories.name,
            experience_categories.icon, COUNT(experiences.id) AS experiences_count
     FROM experience_categories
     LEFT JOIN experiences ON experiences.category = experience_categories.category_key
     GROUP BY experience_categories.id, experience_categories.category_key,
              experience_categories.name, experience_categories.icon, experience_categories.sort_order
     ORDER BY experience_categories.sort_order ASC, experience_categories.id ASC'
)->fetchAll();
$pageTitle = 'إدارة المجالات التربوية';
$pageStylesheet = 'assets/css/admin.css';
$bodyClass = 'admin-dashboard-body';
require ROOT_PATH . '/includes/header.php';
?>
<section class="admin-page">
    <div class="admin-category-hero">
        <div class="admin-category-hero-copy">
            <a class="admin-category-back-link" href="<?= e(url('admin/index.php')) ?>"><span aria-hidden="true">←</span> العودة إلى لوحة الإدارة</a>
            <span class="admin-category-eyebrow"><span aria-hidden="true">◇</span> إعدادات المحتوى</span>
            <h1>إدارة المجالات التربوية</h1>
            <p>نظّمي تصنيفات الخبرات التعليمية، وأضيفي المجالات التي تلهم مجتمعكِ.</p>
        </div>
        <div class="admin-category-hero-stat">
            <span class="admin-category-hero-icon" aria-hidden="true">◇</span>
            <span>مجال متاح</span>
            <strong><?= number_format(count($categories)) ?></strong>
        </div>
    </div>

    <div class="admin-category-layout">
        <section class="admin-panel admin-category-form-panel" aria-labelledby="category-form-title">
            <div class="admin-panel-head">
                <div><span class="admin-panel-kicker"><?= $editingCategory !== null ? 'تحديث التصنيف' : 'ابدئي من هنا' ?></span><h2 class="admin-panel-title" id="category-form-title"><?= $editingCategory !== null ? 'تعديل المجال' : 'إضافة مجال جديد' ?></h2></div>
                <span class="admin-category-form-mark" aria-hidden="true"><?= e($formIcon) ?></span>
            </div>
            <p class="admin-panel-description">سيظهر اسم المجال ورمزه مباشرةً في نموذج إضافة الخبرة وصفحات التصفية.</p>
            <?php if ($error !== null): ?><p class="admin-form-error" role="alert"><span aria-hidden="true">!</span><?= e($error) ?></p><?php endif; ?>
            <form class="admin-category-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $editingCategory !== null ? 'update' : 'create' ?>">
                <?php if ($editingCategory !== null): ?><input type="hidden" name="category_id" value="<?= (int) $editingCategory['id'] ?>"><?php endif; ?>
                <label for="category-name">اسم المجال
                    <input class="admin-input" id="category-name" name="name" maxlength="100" value="<?= e($formName) ?>" placeholder="مثال: التعلم التعاوني" required>
                </label>
                <fieldset class="admin-category-icons">
                    <legend>اختاري رمزاً يعبّر عن المجال</legend>
                    <div class="admin-category-icon-options">
                        <?php foreach ($icons as $index => $icon): ?>
                            <label class="admin-category-icon-option">
                                <input type="radio" name="icon" value="<?= e($icon) ?>" <?= $formIcon === $icon ? 'checked' : '' ?> required>
                                <span aria-hidden="true"><?= e($icon) ?></span>
                                <span class="sr-only">الرمز <?= $index + 1 ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="admin-category-form-actions">
                    <button class="admin-button admin-button-primary" type="submit"><span aria-hidden="true"><?= $editingCategory !== null ? '✓' : '＋' ?></span><?= $editingCategory !== null ? 'حفظ التعديلات' : 'إضافة المجال' ?></button>
                    <?php if ($editingCategory !== null): ?><a class="admin-button admin-button-secondary" href="<?= e(url('admin/categories.php')) ?>">إلغاء التعديل</a><?php endif; ?>
                </div>
            </form>
            <div class="admin-category-tip"><span aria-hidden="true">✦</span><p>المجالات المستخدمة في خبرات سابقة لا يمكن حذفها، لكن يمكنكِ تعديل أسمائها ورموزها.</p></div>
        </section>

        <section class="admin-panel admin-category-list-panel" aria-labelledby="category-list-title">
            <div class="admin-panel-head">
                <div><span class="admin-panel-kicker">نظرة عامة</span><h2 class="admin-panel-title" id="category-list-title">المجالات الحالية</h2></div>
                <span class="admin-category-total"><?= number_format(count($categories)) ?> مجالات</span>
            </div>
            <div class="admin-category-list">
                <?php foreach ($categories as $category): ?>
                    <article class="admin-category-row">
                        <span class="admin-category-icon" aria-hidden="true"><?= e($category['icon']) ?></span>
                        <div class="admin-category-copy"><strong><?= e($category['name']) ?></strong><small><?= number_format((int) $category['experiences_count']) ?> <?= (int) $category['experiences_count'] === 1 ? 'خبرة مرتبطة' : 'خبرات مرتبطة' ?></small></div>
                        <div class="admin-category-actions">
                            <a class="admin-category-edit" href="<?= e(url('admin/categories.php?edit=' . (int) $category['id'])) ?>"><span aria-hidden="true">✎</span> تعديل</a>
                            <form method="post" onsubmit="return window.confirm('هل تريدين حذف مجال <?= e($category['name']) ?>؟ لا يمكن حذف المجال إذا كانت هناك خبرات مرتبطة به.')">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                <button class="admin-category-delete" type="submit" <?= (int) $category['experiences_count'] > 0 || count($categories) <= 1 ? 'disabled title="لا يمكن حذف مجال مستخدم أو المجال الأخير"' : '' ?>><span aria-hidden="true">×</span> حذف</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ($categories === []): ?><p class="admin-category-empty">لا توجد مجالات حتى الآن. أضيفي أول مجال للبدء.</p><?php endif; ?>
            </div>
        </section>
    </div>
</section>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($editId === false) {
    http_response_code(400);
    exit('معرّف الخبرة غير صالح.');
}
$isEditing = $editId !== null;
$existingAttachments = [];

if ($isEditing) {
    if ((current_user()['role'] ?? '') !== 'teacher') {
        http_response_code(403);
        exit('هذه الصفحة مخصصة لتعديل خبرات المعلمات.');
    }

    $findExperience = db()->prepare(
        'SELECT id, title, content, category
         FROM experiences
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $findExperience->execute([$editId, current_user()['id']]);
    $experience = $findExperience->fetch();
    if ($experience === false) {
        http_response_code(404);
        exit('الخبرة المطلوبة غير موجودة.');
    }

    $title = $experience['title'];
    $content = $experience['content'];
    $category = $experience['category'];

    if (preg_match('/\n*### الأثر والممارسات القابلة للتطبيق[\r\n]+(.*)\z/su', $content, $impactMatch) === 1) {
        $impact = trim($impactMatch[1]);
        $content = preg_replace('/\n*### الأثر والممارسات القابلة للتطبيق[\r\n]+.*\z/su', '', $content) ?? $content;
    }
    if (preg_match('/\n*### السياق التعليمي[\r\n]+(.*)\z/su', $content, $contextMatch) === 1) {
        $context = $contextMatch[1];
        $content = preg_replace('/\n*### السياق التعليمي[\r\n]+.*\z/su', '', $content) ?? $content;
        if (preg_match('/^المرحلة الدراسية:\s*(.+)$/mu', $context, $stageMatch) === 1) {
            $stage = trim($stageMatch[1]);
        }
        if (preg_match('/^المادة أو التخصص:\s*(.+)$/mu', $context, $subjectMatch) === 1) {
            $subject = trim($subjectMatch[1]);
        }
    }
    $content = rtrim($content);

    $attachmentsQuery = db()->prepare(
        'SELECT original_name FROM experience_attachments WHERE experience_id = ? ORDER BY created_at ASC'
    );
    $attachmentsQuery->execute([$editId]);
    $existingAttachments = $attachmentsQuery->fetchAll();
}

$categories = experience_categories();
$categoryKeys = array_column($categories, 'category_key');
$stages = ['المرحلة الابتدائية', 'المرحلة المتوسطة', 'المرحلة الثانوية', 'جميع المراحل'];
$allowedFiles = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'ppt' => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'xls' => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'gif' => ['image/gif'],
    'webp' => ['image/webp'],
    'mp4' => ['video/mp4'],
    'webm' => ['video/webm'],
    'ogv' => ['video/ogg'],
];
$error = null;
$title = $title ?? '';
$content = $content ?? '';
$category = $category ?? ($categoryKeys[0] ?? '');
$subject = $subject ?? '';
$stage = $stage ?? '';
$impact = $impact ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = text_input($_POST, 'title');
    $content = text_input($_POST, 'content');
    $category = text_input($_POST, 'category');
    $subject = text_input($_POST, 'subject');
    $stage = text_input($_POST, 'stage');
    $impact = text_input($_POST, 'impact');
    $contentSections = [$content];
    if ($subject !== '' || $stage !== '') {
        $context = [];
        if ($stage !== '') {
            $context[] = 'المرحلة الدراسية: ' . $stage;
        }
        if ($subject !== '') {
            $context[] = 'المادة أو التخصص: ' . $subject;
        }
        $contentSections[] = "### السياق التعليمي\n" . implode("\n", $context);
    }
    if ($impact !== '') {
        $contentSections[] = "### الأثر والممارسات القابلة للتطبيق\n" . $impact;
    }
    $savedContent = implode("\n\n", $contentSections);

    if (mb_strlen($title) < 5 || mb_strlen($title) > 180) {
        $error = 'اجعلي العنوان بين 5 و180 حرفاً.';
    } elseif (mb_strlen($content) < 20 || mb_strlen($savedContent) > 10000) {
        $error = 'اكتبي وصفاً لا يقل عن 20 حرفاً ولا يتجاوز 10000 حرف.';
    } elseif (mb_strlen($subject) > 120 || ($stage !== '' && !in_array($stage, $stages, true))) {
        $error = 'تحققي من بيانات المادة والمرحلة الدراسية.';
    } elseif (mb_strlen($impact) > 3000 || mb_strlen($savedContent) > 10000) {
        $error = 'اختصري وصف التجربة والأثر بحيث لا يتجاوز مجموعهما 10000 حرف.';
    } elseif (!in_array($category, $categoryKeys, true)) {
        $error = 'اختاري مجالاً صحيحاً للخبرة.';
    }

    $uploads = [];
    if ($error === null && isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'] ?? null)) {
        $fileCount = count($_FILES['attachments']['name']);
        if ($fileCount > 5) {
            $error = 'يمكن إرفاق خمسة ملفات كحد أقصى.';
        } else {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            for ($index = 0; $index < $fileCount; $index++) {
                $uploadError = $_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $error = 'تعذر رفع أحد الملفات. تحققي من حجمه ثم حاولي مرة أخرى.';
                    break;
                }

                $temporaryPath = $_FILES['attachments']['tmp_name'][$index] ?? '';
                $fileSize = (int) ($_FILES['attachments']['size'][$index] ?? 0);
                $originalName = basename(str_replace('\\', '/', (string) ($_FILES['attachments']['name'][$index] ?? '')));
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $validTemporaryFile = is_string($temporaryPath) && is_uploaded_file($temporaryPath) && is_file($temporaryPath);
                $mimeType = $validTemporaryFile ? $fileInfo->file($temporaryPath) : false;

                if (
                    !$validTemporaryFile
                    || $fileSize < 1
                    || $fileSize > 10 * 1024 * 1024
                    || !isset($allowedFiles[$extension])
                    || !is_string($mimeType)
                    || !in_array($mimeType, $allowedFiles[$extension], true)
                ) {
                    $error = 'نوع أحد الملفات غير مدعوم أو يتجاوز حجمه 10 ميجابايت.';
                    break;
                }

                $uploads[] = [
                    'temporary_path' => $temporaryPath,
                    'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'مرفق', 0, 240),
                    'extension' => $extension,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'stored_name' => bin2hex(random_bytes(24)) . '.' . $extension,
                ];
            }
        }
    }
    if ($error === null && count($existingAttachments) + count($uploads) > 5) {
        $error = 'يمكن إرفاق خمسة ملفات كحد أقصى، وتشمل الملفات الموجودة حالياً.';
    }

    if ($error === null) {
        $uploadDirectory = ROOT_PATH . '/storage/uploads';
        $movedFiles = [];
        $connection = db();

        try {
            $connection->beginTransaction();
            if ($isEditing) {
                $lockExperience = $connection->prepare(
                    'SELECT id FROM experiences WHERE id = ? AND user_id = ? FOR UPDATE'
                );
                $lockExperience->execute([$editId, current_user()['id']]);
                if ($lockExperience->fetchColumn() === false) {
                    throw new RuntimeException('الخبرة المطلوبة غير موجودة أو لم تعودي تملكين صلاحية تعديلها.');
                }

                $countAttachments = $connection->prepare(
                    'SELECT id FROM experience_attachments WHERE experience_id = ? FOR UPDATE'
                );
                $countAttachments->execute([$editId]);
                if (count($countAttachments->fetchAll()) + count($uploads) > 5) {
                    throw new RuntimeException('تجاوز عدد المرفقات الحد المسموح به وهو خمسة ملفات.');
                }

                $update = $connection->prepare(
                    'UPDATE experiences SET title = ?, content = ?, category = ? WHERE id = ? AND user_id = ?'
                );
                $update->execute([$title, $savedContent, $category, $editId, current_user()['id']]);
                $experienceId = $editId;
            } else {
                $statement = $connection->prepare(
                    'INSERT INTO experiences (user_id, title, content, category, status) VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([current_user()['id'], $title, $savedContent, $category, 'published']);
                $experienceId = (int) $connection->lastInsertId();
            }

            $attachmentStatement = $connection->prepare(
                'INSERT INTO experience_attachments (experience_id, original_name, stored_name, mime_type, file_size) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($uploads as $upload) {
                $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $upload['stored_name'];
                if (!move_uploaded_file($upload['temporary_path'], $destination)) {
                    throw new RuntimeException('تعذر حفظ أحد الملفات المرفقة.');
                }
                $movedFiles[] = $destination;
                $attachmentStatement->execute([
                    $experienceId,
                    $upload['original_name'],
                    $upload['stored_name'],
                    $upload['mime_type'],
                    $upload['file_size'],
                ]);
            }

            $connection->commit();
            if ($isEditing) {
                flash('success', 'تم حفظ تعديلات خبرتكِ بنجاح.');
                redirect('experiences/my.php');
            }
            flash('success', 'تم نشر خبرتك بنجاح. شكراً لمشاركتك!');
            redirect('experiences/show.php?id=' . $experienceId);
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            foreach ($movedFiles as $movedFile) {
                if (is_file($movedFile) && !unlink($movedFile)) {
                    error_log('Unable to remove an uploaded file after a failed experience submission.');
                }
            }
            if ($isEditing && $exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                throw $exception;
            }
        }
    }
}

$pageTitle = $isEditing ? 'تعديل الخبرة' : 'إضافة خبرة جديدة';
$bodyClass = 'experience-compose-body';
$pageStylesheet = 'assets/css/experience-create.css';
require ROOT_PATH . '/includes/header.php';
?>
<div class="experience-compose">
    <section class="compose-hero" aria-labelledby="compose-title">
        <div class="compose-hero-copy">
            <span class="compose-hero-kicker"><span aria-hidden="true">✦</span> <?= $isEditing ? 'تحديث خبرتكِ' : 'مساحة للمعرفة والأثر' ?></span>
            <h1 id="compose-title"><?= $isEditing ? 'عدّلي خبرتكِ وألهمي زميلاتكِ' : 'شاركي خبرتكِ، وألهمي زميلاتكِ' ?></h1>
            <p><?= $isEditing ? 'حدّثي تفاصيل مشاركتكِ، وستبقى حالة النشر والمرفقات الحالية كما هي.' : 'كل تجربة ناجحة تستحق أن تُروى؛ اكتبيها لتكون مصدر إلهام ونمو مهني لمجتمعنا التعليمي.' ?></p>
        </div>
        <?php if (!$isEditing): ?><div class="compose-hero-stat" aria-label="مشاركة خبرتك تصنع أثراً">
            <span class="compose-stat-icon" aria-hidden="true">✧</span>
            <strong>أثركِ يبدأ<br>من هنا</strong>
            <small>خبرة واحدة قد تصنع فرقاً</small>
        </div><?php else: ?><a class="compose-edit-back" href="<?= e(url('experiences/my.php')) ?>">العودة إلى خبراتي <span aria-hidden="true">↩</span></a><?php endif; ?>
    </section>

    <div class="compose-layout">
        <aside class="compose-sidebar" aria-label="إرشادات كتابة الخبرة">
            <section class="compose-author-card">
                <span class="compose-author-avatar" aria-hidden="true"><?= e(mb_substr(current_user()['name'], 0, 1)) ?></span>
                <div><span>تُكتب هذه التجربة باسم</span><strong><?= e(current_user()['name']) ?></strong><small>عضوة في مجتمعنا التعليمي</small></div>
                <span class="compose-author-check" aria-label="حساب موثق">✓</span>
            </section>

            <section class="compose-tips-card">
                <h2><span aria-hidden="true">✎</span> نصائح لصياغة تجربة ملهمة</h2>
                <ol>
                    <li><span class="compose-tip-number">١</span><div><strong>ابدئي بعنوان واضح ومشوق</strong><p>اختاري عنواناً يعكس جوهر الممارسة وفائدتها.</p></div></li>
                    <li><span class="compose-tip-number">٢</span><div><strong>صفي موقفاً حقيقياً</strong><p>اذكري التحدي أو الموقف والسياق الذي حدث فيه.</p></div></li>
                    <li><span class="compose-tip-number">٣</span><div><strong>سلّطي الضوء على أثر التطبيق</strong><p>كيف استجابت الطالبات؟ وما الذي تغير للأفضل؟</p></div></li>
                    <li><span class="compose-tip-number">٤</span><div><strong>شاركي التحديات والحلول</strong><p>التفاصيل العملية تجعل تجربتك أكثر فائدة وقابلية للتطبيق.</p></div></li>
                    <li><span class="compose-tip-number">٥</span><div><strong>أرفقي ما يدعم عملك</strong><p>خطط أو نماذج تساعد زميلاتك على الاستفادة.</p></div></li>
                </ol>
            </section>

            <blockquote class="compose-quote">
                <span aria-hidden="true">“</span>
                <p>اتركي أثراً، وتضاعف المعرفة حين نتشاركها.</p>
                <cite>دليل الممارسات التربوية الملهمة</cite>
            </blockquote>

            <section class="compose-faq-card">
                <h2>استفسارات تبحث عن خبرة</h2>
                <p>موضوعات يسعد زميلاتكِ بقراءة تجربة منكِ عنها</p>
                <span>كيف تعززين دافعية الطالبات؟</span>
                <span>ما أفضل طريقة لإدارة الصف؟</span>
            </section>
        </aside>

        <form class="compose-form" id="experience-form" method="post" action="<?= e(url('experiences/create.php' . ($isEditing ? '?id=' . (int) $editId : ''))) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($error !== null): ?><p class="compose-error" role="alert"><?= e($error) ?></p><?php endif; ?>

            <section class="compose-card compose-basics-card">
                <div class="compose-section-heading">
                    <span class="compose-section-icon" aria-hidden="true">✎</span>
                    <div><h2>ابدئي بتعريف تجربتك</h2><p>تفاصيل بسيطة تساعد زميلاتكِ على اكتشاف تجربتك والاستفادة منها.</p></div>
                </div>
                <label class="compose-label" for="experience-title">عنوان التجربة أو الخبرة التعليمية <span aria-hidden="true">*</span></label>
                <input class="compose-input" id="experience-title" name="title" maxlength="180" value="<?= e($title) ?>" placeholder="مثال: تطبيق استراتيجية التعلم النشط في درس العلوم" required>

                <div class="compose-field-heading"><span class="compose-label">المجال التربوي الرئيسي <span aria-hidden="true">*</span></span><small>اختاري المجال الأقرب لتجربتك</small></div>
                <div class="compose-category-grid">
                    <?php foreach ($categories as $option): ?>
                        <label class="compose-category-option">
                            <input type="radio" name="category" value="<?= e($option['category_key']) ?>" <?= $category === $option['category_key'] ? 'checked' : '' ?> required>
                            <span class="compose-category-icon" aria-hidden="true"><?= e($option['icon']) ?></span>
                            <span><?= e($option['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="compose-select-grid">
                    <label class="compose-label" for="experience-stage">المرحلة الدراسية المعنية
                        <select class="compose-select" id="experience-stage" name="stage">
                            <option value="">اختاري المرحلة...</option>
                            <?php foreach ($stages as $option): ?><option value="<?= e($option) ?>" <?= $stage === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="compose-label" for="experience-subject">المادة أو التخصص
                        <input class="compose-input" id="experience-subject" name="subject" maxlength="120" value="<?= e($subject) ?>" placeholder="مثال: العلوم والرياضيات">
                    </label>
                </div>
            </section>

            <section class="compose-card compose-editor-card">
                <div class="compose-section-heading">
                    <span class="compose-section-icon" aria-hidden="true">▤</span>
                    <div><h2>تفاصيل ووصف الممارسة التعليمية <span aria-hidden="true">*</span></h2><p>اكتبي وصفاً واضحاً لتجربتك وخطواتها، وما الذي جعلها ناجحة.</p></div>
                </div>
                <div class="compose-editor">
                    <div class="compose-editor-toolbar" role="group" aria-label="أدوات تنسيق النص">
                        <button type="button" data-insert="heading" aria-label="إضافة عنوان فرعي">H</button>
                        <button type="button" data-insert="bullet" aria-label="إضافة قائمة نقطية">•</button>
                        <button type="button" data-insert="number" aria-label="إضافة قائمة مرقمة">1.</button>
                        <span aria-hidden="true"></span>
                        <button type="button" data-insert="quote" aria-label="إضافة اقتباس">“ ”</button>
                        <button type="button" data-insert="bold" aria-label="تمييز نص مهم"><strong>B</strong></button>
                    </div>
                    <textarea class="compose-textarea compose-main-textarea" id="experience-content" name="content" maxlength="10000" required placeholder="صفي الموقف التعليمي، والخطوات التي اتبعتها، وما الذي تعلمته من التجربة..."><?= e($content) ?></textarea>
                    <div class="compose-editor-footer"><span>اكتبي بأسلوبك، فالتجارب الصادقة هي الأكثر إلهاماً.</span><span><b id="content-count"><?= mb_strlen($content) ?></b> / 10000 حرف</span></div>
                </div>
            </section>

            <section class="compose-card compose-impact-card">
                <div class="compose-section-heading">
                    <span class="compose-section-icon compose-impact-icon" aria-hidden="true">↗</span>
                    <div><h2>ما الذي استفدته من التجربة وما هو الأثر الملموس على الطالبات؟</h2><p>جودي التجربة بذكر الأثر التحصيلي والسلوكي، أو التطور الذي لاحظته. (مثال: ارتفاع متوسط درجات الاختبار بنسبة 25%، تفاعل الطالبات، أو تحسن الثقة بالنفس)</p></div>
                </div>
                <label class="compose-visually-hidden" for="experience-impact">الأثر والتعلم من التجربة</label>
                <textarea class="compose-textarea compose-impact-textarea" id="experience-impact" name="impact" maxlength="3000" placeholder="صفي الأثر بدقة (أثر تربوي على الطالبات + أثر مهني ذاتي عليك كمعلمة)..."><?= e($impact) ?></textarea>
                <div class="compose-prompt-tags" aria-label="أفكار لمؤشرات الأثر"><span>نمو في التحصيل</span><span>زيادة المشاركة</span><span>تعزيز الثقة</span><span>دعم التعاون</span></div>
            </section>

            <section class="compose-card compose-attachments-card">
                <div class="compose-section-heading">
                    <span class="compose-section-icon" aria-hidden="true">⌁</span>
                    <div><h2>إرفاق خطط العمل ونماذج الأنشطة</h2><p><?= $isEditing ? 'ستبقى المرفقات الحالية محفوظة. يمكنكِ إضافة ملفات جديدة ضمن حد خمسة مرفقات إجمالاً.' : 'أرفقي ملفات داعمة أو صوراً وفيديوهات بصيغ PDF وDOC وDOCX وPPT وPPTX وXLS وXLSX وJPG وPNG وGIF وWebP وMP4 وWebM وOGV، بحد أقصى 10 ميجابايت للملف.' ?></p></div>
                </div>
                <?php if ($existingAttachments !== []): ?><ul class="compose-existing-attachments"><?php foreach ($existingAttachments as $attachment): ?><li><?= e($attachment['original_name']) ?></li><?php endforeach; ?></ul><?php endif; ?>
                <label class="compose-upload-zone" for="experience-attachments">
                    <span class="compose-upload-icon" aria-hidden="true">⇧</span>
                    <strong>اسحبي وأفلتي الملفات هنا أو تصفحي جهازكِ</strong>
                    <small>يمكن إضافة <?= 5 - count($existingAttachments) ?> ملفات كحد أقصى</small>
                    <input class="compose-visually-hidden" id="experience-attachments" type="file" name="attachments[]" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp,.mp4,.webm,.ogv" multiple <?= count($existingAttachments) >= 5 ? 'disabled' : '' ?>>
                </label>
                <ul class="compose-file-list" id="attachment-list" aria-live="polite"></ul>
            </section>

            <div class="compose-actions">
                <?php if ($isEditing): ?>
                    <button class="compose-submit-button" type="submit"><span aria-hidden="true">✓</span> حفظ التعديلات</button>
                    <a class="compose-secondary-button" href="<?= e(url('experiences/my.php')) ?>">إلغاء</a>
                <?php else: ?>
                    <button class="compose-submit-button" type="submit"><span aria-hidden="true">➤</span> نشر التجربة في المنصة الآن</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(url('assets/js/experience-create.js')) ?>" defer></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

<?php

require dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$experienceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$experienceId) {
    http_response_code(404);
    exit('الخبرة المطلوبة غير موجودة.');
}

$statement = db()->prepare(
    "SELECT experiences.*, users.name AS author
     FROM experiences JOIN users ON users.id = experiences.user_id
     WHERE experiences.id = ?
       AND (experiences.status = 'published' OR ? = 'admin' OR experiences.user_id = ?)
     LIMIT 1"
);
$statement->execute([$experienceId, current_user()['role'] ?? '', current_user()['id']]);
$experience = $statement->fetch();

if ($experience === false) {
    http_response_code(404);
    exit('الخبرة المطلوبة غير موجودة.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    $action = text_input($_POST, 'action');

    if ($action === 'delete_experience') {
        if ((int) current_user()['id'] !== (int) $experience['user_id']) {
            http_response_code(403);
            exit('لا تملكين صلاحية حذف هذه الخبرة.');
        }

        $connection = db();
        $connection->beginTransaction();
        try {
            $find = $connection->prepare('SELECT user_id FROM experiences WHERE id = ? FOR UPDATE');
            $find->execute([$experienceId]);
            $lockedExperience = $find->fetch();
            if ($lockedExperience === false || (int) $lockedExperience['user_id'] !== (int) current_user()['id']) {
                $connection->rollBack();
                http_response_code(404);
                exit('الخبرة المطلوبة غير موجودة.');
            }

            $attachmentsQuery = $connection->prepare('SELECT stored_name FROM experience_attachments WHERE experience_id = ?');
            $attachmentsQuery->execute([$experienceId]);
            $storedNames = array_column($attachmentsQuery->fetchAll(), 'stored_name');
            $delete = $connection->prepare('DELETE FROM experiences WHERE id = ?');
            $delete->execute([$experienceId]);
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
                ? 'تم حذف خبرتك ومرفقاتها وتعليقاتها وإعجاباتها.'
                : 'حُذفت الخبرة، لكن تعذّر حذف بعض ملفاتها المرفقة. راجعي إدارة المنصة.'
        );
        redirect(text_input($_GET, 'from') === 'mine' ? 'experiences/my.php' : 'experiences/index.php');
    } elseif ($action === 'like') {
        $like = db()->prepare('INSERT IGNORE INTO experience_likes (experience_id, user_id) VALUES (?, ?)');
        $like->execute([$experienceId, current_user()['id']]);
    } elseif ($action === 'comment') {
        $comment = text_input($_POST, 'comment');
        if (mb_strlen($comment) < 2 || mb_strlen($comment) > 2000) {
            flash('error', 'يجب أن يكون التعليق بين حرفين و2000 حرف.');
            redirect('experiences/show.php?id=' . $experienceId);
        }
        $insert = db()->prepare('INSERT INTO comments (experience_id, user_id, content) VALUES (?, ?, ?)');
        $insert->execute([$experienceId, current_user()['id'], $comment]);
    } else {
        http_response_code(400);
        exit('طلب غير صالح.');
    }

    redirect('experiences/show.php?id=' . $experienceId);
}

$commentsQuery = db()->prepare(
    'SELECT comments.content, comments.created_at, users.name AS author
     FROM comments JOIN users ON users.id = comments.user_id
     WHERE comments.experience_id = ? AND comments.status = ? ORDER BY comments.created_at ASC'
);
$commentsQuery->execute([$experienceId, 'visible']);
$comments = $commentsQuery->fetchAll();
$attachmentsQuery = db()->prepare(
    'SELECT id, original_name, file_size, mime_type FROM experience_attachments WHERE experience_id = ? ORDER BY created_at ASC'
);
$attachmentsQuery->execute([$experienceId]);
$attachments = $attachmentsQuery->fetchAll();
$likesQuery = db()->prepare('SELECT COUNT(*) FROM experience_likes WHERE experience_id = ?');
$likesQuery->execute([$experienceId]);
$likesCount = (int) $likesQuery->fetchColumn();
$likedByUser = false;

if (current_user() !== null) {
    $likedQuery = db()->prepare('SELECT 1 FROM experience_likes WHERE experience_id = ? AND user_id = ?');
    $likedQuery->execute([$experienceId, current_user()['id']]);
    $likedByUser = $likedQuery->fetchColumn() !== false;
}

$pageTitle = $experience['title'];
$pageStylesheet = 'assets/css/experience-show.css';
$bodyClass = 'experience-show-body';
$authorMark = mb_substr($experience['author'], 0, 1);
require ROOT_PATH . '/includes/header.php';
?>
<div class="experience-show page-container">
    <nav class="experience-breadcrumb" aria-label="مسار التنقل">
        <a href="<?= e(url('index.php')) ?>">الرئيسية</a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('experiences/index.php')) ?>">بنك الخبرات</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e(category_label($experience['category'])) ?></span>
    </nav>

    <header class="experience-hero">
        <div class="experience-hero-copy">
            <div class="experience-tags">
                <span class="experience-tag experience-tag-category"><?= e(category_label($experience['category'])) ?></span>
                <span class="experience-tag">خبرة تعليمية</span>
            </div>
            <h1><?= e($experience['title']) ?></h1>
            <div class="experience-byline">
                <span class="author-avatar" aria-hidden="true"><?= e($authorMark) ?></span>
                <span class="byline-copy">
                    <strong><?= e($experience['author']) ?></strong>
                    <span>مشاركة في بنك الخبرات · <?= e(date('Y/m/d', strtotime($experience['created_at']))) ?></span>
                </span>
            </div>
        </div>
        <div class="experience-hero-actions">
            <a class="show-action show-action-light" href="<?= e(url('experiences/index.php')) ?>">العودة إلى الخبرات <span aria-hidden="true">↩</span></a>
            <?php if (current_user() !== null): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="like">
                    <button class="show-action show-action-primary" type="submit" <?= $likedByUser ? 'disabled' : '' ?>>
                        <span aria-hidden="true"><?= $likedByUser ? '♥' : '♡' ?></span>
                        <?= $likedByUser ? 'أعجبتك الخبرة' : 'أعجبتني الخبرة' ?> · <?= $likesCount ?>
                    </button>
                </form>
            <?php else: ?>
                <span class="show-action show-action-primary"><span aria-hidden="true">♡</span> إعجاب · <?= $likesCount ?></span>
            <?php endif; ?>
            <button class="show-action show-action-share" type="button" data-experience-share data-share-title="<?= e($experience['title']) ?>">
                <span aria-hidden="true">↗</span> مشاركة الخبرة
            </button>
            <?php if ((int) current_user()['id'] === (int) $experience['user_id']): ?>
                <form method="post" onsubmit="return window.confirm('سيتم حذف الخبرة ومرفقاتها وتعليقاتها وإعجاباتها نهائيًا. هل تريدين المتابعة؟')">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_experience">
                    <button class="show-action show-action-danger" type="submit"><span aria-hidden="true">⌫</span> حذف خبرتي</button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <p class="share-feedback" data-share-feedback role="status" aria-live="polite"></p>

    <?php if ($experience['status'] === 'hidden' && $experience['rejection_reason'] !== null): ?>
        <div class="show-card" role="status">
            <strong>ملاحظة من إدارة المنصة:</strong>
            <?= e($experience['rejection_reason']) ?>
        </div>
    <?php endif; ?>

    <div class="experience-show-layout">
        <aside class="experience-sidebar" aria-label="معلومات الخبرة">
            <section class="show-card author-card">
                <div class="author-card-heading">
                    <span class="author-avatar author-avatar-large" aria-hidden="true"><?= e($authorMark) ?></span>
                    <div>
                        <span class="card-eyebrow">صاحبة الخبرة</span>
                        <h2><?= e($experience['author']) ?></h2>
                    </div>
                </div>
                <p class="author-card-note">شكراً لمشاركة هذه التجربة التعليمية مع المجتمع المهني.</p>
                <div class="author-stats" aria-label="إحصاءات الخبرة">
                    <div><strong><?= $likesCount ?></strong><span>إعجاب</span></div>
                    <div><strong><?= count($comments) ?></strong><span>تعليق</span></div>
                    <div><strong><?= count($attachments) ?></strong><span>مرفق</span></div>
                </div>
                <a class="sidebar-link" href="<?= e(url('experiences/index.php')) ?>">استكشاف خبرات أخرى <span aria-hidden="true">←</span></a>
            </section>

            <section class="show-card details-card" aria-labelledby="experience-details-title">
                <div class="show-card-heading">
                    <span class="heading-icon" aria-hidden="true">✦</span>
                    <h2 id="experience-details-title">تفاصيل الخبرة</h2>
                </div>
                <dl class="experience-details">
                    <div><dt>المجال</dt><dd><?= e(category_label($experience['category'])) ?></dd></div>
                    <div><dt>تاريخ المشاركة</dt><dd><?= e(date('Y/m/d', strtotime($experience['created_at']))) ?></dd></div>
                    <div><dt>التفاعل</dt><dd><?= $likesCount ?> إعجاب · <?= count($comments) ?> تعليق</dd></div>
                </dl>
            </section>
        </aside>

        <div class="experience-main">
            <article class="show-card story-card" aria-labelledby="story-title">
                <div class="show-card-heading story-heading">
                    <span class="heading-icon heading-icon-mint" aria-hidden="true">01</span>
                    <div>
                        <span class="card-eyebrow">من واقع الممارسة التعليمية</span>
                        <h2 id="story-title">تفاصيل التجربة</h2>
                    </div>
                </div>
                <div class="experience-content"><?= format_experience_content($experience['content']) ?></div>
            </article>

            <?php if ($attachments !== []): ?>
                <section class="show-card attachments-card" aria-labelledby="attachments-title">
                    <div class="section-title-row">
                        <div class="show-card-heading">
                            <span class="heading-icon heading-icon-sand" aria-hidden="true">↗</span>
                            <div>
                                <span class="card-eyebrow">مواد مساندة</span>
                                <h2 id="attachments-title">المرفقات والموارد القابلة للتحميل</h2>
                            </div>
                        </div>
                        <span class="section-count"><?= count($attachments) ?> ملفات</span>
                    </div>
                    <ul class="attachment-list">
                        <?php foreach ($attachments as $attachment): ?>
                            <?php $fileExtension = strtoupper(pathinfo($attachment['original_name'], PATHINFO_EXTENSION) ?: 'FILE'); ?>
                            <?php
                            $isImage = in_array($attachment['mime_type'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
                            $isVideo = in_array($attachment['mime_type'], ['video/mp4', 'video/webm', 'video/ogg'], true);
                            $inlineUrl = url('experiences/download.php?id=' . (int) $attachment['id'] . '&inline=1');
                            ?>
                            <li class="attachment-item<?= $isImage || $isVideo ? ' attachment-item-media' : '' ?>">
                                <?php if ($isImage): ?>
                                    <a class="attachment-preview attachment-image-preview" href="<?= e($inlineUrl) ?>" target="_blank" rel="noopener">
                                        <img src="<?= e($inlineUrl) ?>" alt="<?= e($attachment['original_name']) ?>" loading="lazy">
                                    </a>
                                <?php elseif ($isVideo): ?>
                                    <video class="attachment-preview attachment-video-preview" controls preload="metadata" playsinline>
                                        <source src="<?= e($inlineUrl) ?>" type="<?= e($attachment['mime_type']) ?>">
                                        متصفحك لا يدعم تشغيل الفيديو.
                                    </video>
                                <?php endif; ?>
                                <div class="attachment-file-info">
                                    <span class="attachment-type"><?= e($fileExtension) ?></span>
                                    <span class="attachment-copy">
                                        <strong><?= e($attachment['original_name']) ?></strong>
                                        <small><?= e(number_format((int) $attachment['file_size'] / 1024)) ?> كيلوبايت</small>
                                    </span>
                                    <a class="download-link" href="<?= e(url('experiences/download.php?id=' . (int) $attachment['id'])) ?>">تحميل <span aria-hidden="true">↓</span></a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="show-card discussion-card" aria-labelledby="discussion-title">
                <div class="section-title-row">
                    <div class="show-card-heading">
                        <span class="heading-icon heading-icon-mint" aria-hidden="true">02</span>
                        <div>
                            <span class="card-eyebrow">مساحة للتعلم وتبادل الأفكار</span>
                            <h2 id="discussion-title">حلقة النقاش وتبادل الأفكار المهنية</h2>
                        </div>
                    </div>
                    <span class="section-count"><?= count($comments) ?> تعليق</span>
                </div>

                <?php if (current_user() !== null): ?>
                    <form class="comment-form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="comment">
                        <label for="comment">أضيفي تعليقاً أو فكرة بناءة</label>
                        <textarea id="comment" name="comment" maxlength="2000" required placeholder="شاركي رأيك أو أضيفي اقتراحاً يساعد على تطوير هذه التجربة..."></textarea>
                        <div class="comment-form-footer">
                            <span>كوني ملهمة، واحرصي على أن يكون تعليقك مهنياً وبنّاءً.</span>
                            <button class="show-action show-action-primary" type="submit">إرسال التعليق <span aria-hidden="true">↗</span></button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="discussion-login"><a href="<?= e(url('login.php')) ?>">سجّلي الدخول</a> للمشاركة في النقاش وإضافة تعليق.</p>
                <?php endif; ?>

                <div class="comment-list">
                    <?php foreach ($comments as $comment): ?>
                        <?php $commentMark = mb_substr($comment['author'], 0, 1); ?>
                        <article class="comment-item">
                            <span class="author-avatar comment-avatar" aria-hidden="true"><?= e($commentMark) ?></span>
                            <div class="comment-body">
                                <div class="comment-meta">
                                    <strong><?= e($comment['author']) ?></strong>
                                    <time datetime="<?= e(date(DATE_ATOM, strtotime($comment['created_at']))) ?>"><?= e(date('Y/m/d', strtotime($comment['created_at']))) ?></time>
                                </div>
                                <p><?= e($comment['content']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($comments === []): ?>
                        <p class="empty-comments">لا توجد تعليقات بعد. ابدئي نقاشاً مهنياً حول هذه التجربة.</p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</div>
<script src="<?= e(url('assets/js/experience-share.js')) ?>" defer></script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>

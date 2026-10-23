<?php

require __DIR__ . '/app/bootstrap.php';

$pageTitle = 'الرئيسية';
$user = current_user();
$summary = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM experiences WHERE status = 'published') AS experiences_count,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1) AS teachers_count"
)->fetch();
$categoryCount = count(experience_categories());
$latestExperiences = [];
$teachers = [
    'تركية الطلحي',
    'مها البقمي',
    'نوف الغامدي',
    'هدى الثمالي',
    'هدى الثقفي',
    'ديمة السبيعي',
];

if ($user !== null) {
    $statement = db()->query(
        "SELECT experiences.id, experiences.title, experiences.content, experiences.category, experiences.created_at, users.name AS author
         FROM experiences
         JOIN users ON users.id = experiences.user_id
         WHERE experiences.status = 'published'
         ORDER BY experiences.created_at DESC
         LIMIT 3"
    );
    $latestExperiences = $statement->fetchAll();
}

require ROOT_PATH . '/includes/header.php';
?><div class="home-page">
    <section class="home-hero" aria-labelledby="hero-title">
        <div class="page-container hero-layout">
            <div class="hero-copy">
                <p class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> <?= e(SCHOOL_NAME) ?> · تعليم ونمو مهني</p>
                <h1 id="hero-title">مرحباً بكِ في<br><span><?= e(PLATFORM_NAME) ?></span></h1>
                <p class="hero-description">ملتقى للتميز التربوي والمعرفي لممارسات ومستجدات <?= e(SCHOOL_NAME) ?>. نتبادل الخبرات المهنية ونستلهم الأفكار، لنصنع معاً بيئة تعليمية أكثر إبداعاً وأثراً.</p>
                <div class="hero-actions">
                    <?php if ($user !== null): ?>
                        <a class="button button-primary" href="<?= e(url('experiences/index.php')) ?>">استكشفي تبادل الخبرات <span aria-hidden="true">←</span></a>
                        <a class="button button-light" href="<?= e(url('experiences/create.php')) ?>">شاركي خبرتكِ الآن</a>
                    <?php else: ?>
                        <a class="button button-primary" href="<?= e(url('login.php')) ?>">استكشفي تبادل الخبرات <span aria-hidden="true">←</span></a>
                        <a class="button button-light" href="#about">تعرفي على المنصة</a>
                    <?php endif; ?>
                </div>
                <div class="hero-note"><span aria-hidden="true">✦</span> بيئة تربوية ملهمة <span class="note-separator">|</span> مجتمع مهني يشارك المعرفة</div>
            </div>

            <div class="hero-visual">
                <div class="leadership-art">
                    <svg class="school-illustration" viewBox="0 0 600 430" role="img" aria-labelledby="school-title school-description">
                        <title id="school-title">مبنى المدرسة ومديرتها نسيم القرني</title>
                        <desc id="school-description">رسم معماري لمدرسة عصرية بواجهة زجاجية ولوحة حجرية منقوش عليها اسم مديرة المدرسة نسيم القرني.</desc>
                        <defs>
                            <linearGradient id="school-wall" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#fffefa"/>
                                <stop offset="1" stop-color="#e7efe5"/>
                            </linearGradient>
                            <linearGradient id="school-roof" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#347b62"/>
                                <stop offset="1" stop-color="#104a3b"/>
                            </linearGradient>
                            <linearGradient id="school-glass" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#d1eee3"/>
                                <stop offset="1" stop-color="#69a991"/>
                            </linearGradient>
                            <linearGradient id="school-door" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#e6bf7c"/>
                                <stop offset="1" stop-color="#b77b43"/>
                            </linearGradient>
                            <linearGradient id="school-plaque" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#f5e8cb"/>
                                <stop offset=".48" stop-color="#e2c797"/>
                                <stop offset="1" stop-color="#c8a66d"/>
                            </linearGradient>
                            <filter id="school-shadow" x="-25%" y="-25%" width="150%" height="170%">
                                <feDropShadow dx="0" dy="12" stdDeviation="12" flood-color="#234d38" flood-opacity=".17"/>
                            </filter>
                            <filter id="school-plaque-shadow" x="-15%" y="-25%" width="130%" height="155%">
                                <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#183f30" flood-opacity=".28"/>
                            </filter>
                        </defs>
                        <circle cx="292" cy="207" r="176" fill="#e9f2e6"/>
                        <circle cx="292" cy="207" r="151" fill="none" stroke="#d2e3d1" stroke-width="1.5" stroke-dasharray="3 9"/>
                        <circle cx="444" cy="145" r="20" fill="#f2d99e"/>
                        <circle cx="149" cy="91" r="8" fill="#dcebdd"/>
                        <path d="M66 350h468" stroke="#b9cdb7" stroke-width="2" stroke-linecap="round"/>
                        <path d="M224 425l43-117h50l50 117z" fill="#f7f7ed"/>
                        <path d="M274 320h43m-51 25h59m-68 27h76m-84 28h91" stroke="#dfe8da" stroke-width="2" stroke-linecap="round"/>
                        <g fill="#86ad83" opacity=".5">
                            <path d="M103 347V239h5v108zm389 0V232h5v115z"/>
                        </g>
                        <g fill="#78a879" stroke="#e9f2e6" stroke-width="4">
                            <path d="M105 347v-81c-19 0-31-12-31-28 0-15 11-26 27-27 3-17 17-26 31-22 14 4 20 16 17 30 14 3 22 13 20 27-2 14-13 23-29 23v78z"/>
                            <path d="M495 347v-79c-18 0-29-11-29-26 0-14 10-24 25-25 3-16 16-24 29-20 13 3 19 15 16 28 13 3 20 12 18 25-2 13-12 22-27 22v75z"/>
                        </g>
                        <g fill="#548665">
                            <path d="M83 350c-1-13 8-21 19-20 4-10 15-13 22-6 11-3 20 6 18 17l-1 9z"/>
                            <path d="M458 350c-1-13 8-21 19-20 4-10 15-13 22-6 11-3 20 6 18 17l-1 9z"/>
                        </g>
                        <g filter="url(#school-shadow)">
                            <path d="M121 211h344v139H121z" fill="url(#school-wall)" stroke="#d1dfd0" stroke-width="2"/>
                            <path d="M108 204h370v18H108z" fill="#d7ae70"/>
                            <path d="M130 180h114v170H130z" fill="#edf3e9" stroke="#d1dfd0" stroke-width="2"/>
                            <path d="M342 180h114v170H342z" fill="#edf3e9" stroke="#d1dfd0" stroke-width="2"/>
                            <path d="M121 176h133v13H121zm199 0h145v13H320z" fill="url(#school-roof)"/>
                            <path d="M238 156V94h124v256H238z" fill="url(#school-wall)" stroke="#d1dfd0" stroke-width="2"/>
                            <path d="M225 98h150v13H225z" fill="url(#school-roof)" stroke="#104a3b" stroke-width="2"/>
                            <path d="M218 109h164v6H218z" fill="#d7ae70"/>
                            <path d="M280 118h40v28h-40z" fill="url(#school-glass)" stroke="#fffefa" stroke-width="4"/>
                            <path d="M260 161h80" stroke="#dce7d9" stroke-width="2"/>
                            <path d="M260 177h80" stroke="#cbdccd" stroke-width="2"/>
                            <path d="M273 201h54v45h-54z" fill="url(#school-glass)" stroke="#fffefa" stroke-width="4"/>
                            <path d="M300 203v41m-25-20h50" stroke="#f8fff9" stroke-opacity=".9" stroke-width="2"/>
                            <path d="M264 350v-69a36 36 0 0 1 72 0v69z" fill="url(#school-door)" stroke="#a97843" stroke-width="2"/>
                            <path d="M274 278h52m-52 9h52" stroke="#fff0c7" stroke-opacity=".75" stroke-width="2"/>
                            <circle cx="324" cy="317" r="2.5" fill="#fff2ca"/>
                            <g fill="url(#school-glass)" stroke="#fffefa" stroke-width="4">
                                <path d="M145 212h33v43h-33zm49 0h33v43h-33zm-49 63h33v43h-33zm49 0h33v43h-33z"/>
                                <path d="M364 212h33v43h-33zm49 0h33v43h-33zm-49 63h33v43h-33zm49 0h33v43h-33z"/>
                            </g>
                            <g stroke="#f8fff9" stroke-width="2" opacity=".82">
                                <path d="M161.5 214v39m-14.5-19h29m19 0h33m-16.5-19v39"/>
                                <path d="M380.5 214v39m-14.5-19h29m19 0h33m-16.5-19v39"/>
                                <path d="M161.5 277v39m-14.5-19h29m19 0h33m-16.5-19v39"/>
                                <path d="M380.5 277v39m-14.5-19h29m19 0h33m-16.5-19v39"/>
                            </g>
                            <path d="M130 201h106m128 0h92" stroke="#fff" stroke-opacity=".68" stroke-width="2"/>
                            <path d="M233 169h5v181h-5zm125 0h5v181h-5z" fill="#d7ae70" opacity=".6"/>
                            <path d="M105 350h376" stroke="#afc5ac" stroke-width="6" stroke-linecap="round"/>
                        </g>
                        <path d="M299 83V57" stroke="#52775d" stroke-width="3" stroke-linecap="round"/>
                        <path d="M302 58h35l-9 12h-26z" fill="#d7ae70"/>
                        <path d="M123 168h43m12 0h34m153 0h37m12 0h40" stroke="#c6d8c6" stroke-width="2" stroke-linecap="round"/>
                        <g fill="#d4a75e">
                            <path d="M151 124l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
                            <path d="M478 222l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>
                            <circle cx="435" cy="103" r="3"/>
                        </g>
                        <g filter="url(#school-plaque-shadow)">
                            <rect x="201" y="145" width="198" height="62" rx="5" fill="#9e8054" opacity=".72"/>
                            <rect x="201" y="140" width="198" height="62" rx="5" fill="url(#school-plaque)" stroke="#fff3d7" stroke-width="2"/>
                            <rect x="207" y="146" width="186" height="50" rx="2" fill="none" stroke="#9b7a48" stroke-opacity=".68"/>
                            <path d="M216 151h168M216 191h168" stroke="#fff7e4" stroke-opacity=".68"/>
                            <circle cx="215" cy="154" r="1.8" fill="#967447"/>
                            <circle cx="385" cy="154" r="1.8" fill="#967447"/>
                            <circle cx="215" cy="188" r="1.8" fill="#967447"/>
                            <circle cx="385" cy="188" r="1.8" fill="#967447"/>
                            <text x="300" y="166" fill="#244b35" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="11" font-weight="700" letter-spacing=".3" text-anchor="middle" direction="rtl" unicode-bidi="plaintext">مديرة المدرسة</text>
                            <text x="300" y="186" fill="#fff5dc" fill-opacity=".8" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="20" font-weight="700" text-anchor="middle" direction="rtl" unicode-bidi="plaintext">نسيم القرني</text>
                            <text x="300" y="188" fill="#31533c" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="20" font-weight="700" text-anchor="middle" direction="rtl" unicode-bidi="plaintext">نسيم القرني</text>
                        </g>
                    </svg>
                </div>
                <span class="hero-decoration hero-decoration-one" aria-hidden="true"></span>
                <span class="hero-decoration hero-decoration-two" aria-hidden="true"></span>
            </div>
        </div>
    </section>

    <section class="teachers-section" aria-labelledby="teachers-title">
        <div class="page-container">
            <div class="section-heading centered-heading teachers-heading">
                <p class="section-kicker">وجوه تصنع الأثر</p>
                <h2 id="teachers-title">معلمات مجتمعنا</h2>
                <p>بخبراتكنّ وشغفكنّ، تنمو المعرفة وتزدهر الممارسات التعليمية.</p>
            </div>
            <div class="teachers-grid">
                <?php foreach ($teachers as $index => $teacher): ?>
                    <article class="teacher-card teacher-card-<?= $index % 3 ?>">
                        <span class="teacher-card-number" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span>
                        <span class="teacher-avatar" aria-hidden="true"><?= e(mb_substr($teacher, 0, 1)) ?></span>
                        <h3><?= e($teacher) ?></h3>
                        <span class="teacher-card-caption">منسوبات المدرسة</span>
                        <span class="teacher-card-spark" aria-hidden="true">✦</span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="page-container stats-section" aria-labelledby="stats-title">
        <div class="stats-heading">
            <div>
                <p class="stats-kicker"><span aria-hidden="true"></span> مجتمعنا بالأرقام</p>
                <h2 id="stats-title">أثرٌ يتنامى بالمعرفة</h2>
            </div>
            <p>كل تجربة ومشاركة تصنع فرقاً في رحلتنا التعليمية.</p>
        </div>    <div class="stats-grid">
            <article class="stat-item">
                <span class="stat-icon stat-mint" aria-hidden="true">✦</span>
                <strong class="stat-value"><?= number_format((int) $summary['experiences_count']) ?></strong>
                <span class="stat-label">تجربة منشورة</span>
                <small>خبرات وممارسات قابلة للتطبيق</small>
            </article>
            <article class="stat-item">
                <span class="stat-icon stat-peach" aria-hidden="true">♡</span>
                <strong class="stat-value"><?= number_format((int) $summary['teachers_count']) ?></strong>
                <span class="stat-label">معلمة مسجلة</span>
                <small>مجتمع مهني يتبادل المعرفة</small>
            </article>
            <article class="stat-item">
                <span class="stat-icon stat-sage" aria-hidden="true">▧</span>
                <strong class="stat-value"><?= number_format($categoryCount) ?></strong>
                <span class="stat-label">مجالات تعليمية</span>
                <small>تصنيفات تلبي احتياجاتكِ</small>
            </article>
            <article class="stat-item">
                <span class="stat-icon stat-mint" aria-hidden="true">✓</span>
                <strong class="stat-value">03</strong>
                <span class="stat-label">قيادات داعمة</span>
                <small>تعاون يصنع أثراً تعليمياً</small>
            </article>
        </div>
    </section> <section id="about" class="steps-section" aria-labelledby="steps-title">
        <div class="page-container">
            <div class="section-heading centered-heading">
                <p class="section-kicker">رحلة المعرفة في مجتمعنا</p>
                <h2 id="steps-title">كيف تستفيد المعلمة من المنصة؟</h2>
                <p>خطوات بسيطة تحوّل ممارساتكِ اليومية إلى رصيد مهني موثوق، وتفتح المجال للنمو والتطوير.</p>
            </div>
            <div class="steps-grid">
                <article class="step-card">
                    <span class="step-number">01</span><span class="step-icon icon-mint" aria-hidden="true">⌕</span>
                    <h3>تصفحي التجارب بحسب المجال</h3><p>استلهمي من الخبرات المنشورة ما يناسب احتياجكِ وممارستكِ التعليمية.</p>
                    <span class="step-link">استكشفي الخبرات <span aria-hidden="true">←</span></span>
                </article>
                <article class="step-card">
                    <span class="step-number">02</span><span class="step-icon icon-sage" aria-hidden="true">↗</span>
                    <h3>انقلي الأثر للتطبيق العملي</h3><p>اختاري فكرة ملهمة، وكيّفيها بما يلائم طالباتكِ وبيئتكِ الصفية.</p>
                    <span class="step-link">ممارسات قابلة للتطبيق <span aria-hidden="true">←</span></span>
                </article>
                <article class="step-card">
                    <span class="step-number">03</span><span class="step-icon icon-peach" aria-hidden="true">▤</span>
                    <h3>انشري التجربة ووثقي النمو</h3><p>دوّني ما نجح معكِ ليكون مصدر إلهام وخبرة تتناقلها الزميلات.</p>
                    <span class="step-link">شاركي تجربة ملهمة <span aria-hidden="true">←</span></span>
                </article>
                <article class="step-card">
                    <span class="step-number">04</span><span class="step-icon icon-dark" aria-hidden="true">♧</span>
                    <h3>مجتمع مهني متعاون وموثوق</h3><p>تفاعلي مع زميلاتكِ وشاركي المعرفة في بيئة تربوية داعمة.</p>
                    <span class="step-link">معاً نصنع فرقاً <span aria-hidden="true">←</span></span>
                </article>
            </div>
        </div>
    </section>
    <section class="experiences-section" aria-labelledby="experiences-title">
        <div class="page-container">
            <div class="section-heading heading-row">
                <div>
                    <p class="section-kicker">أحدث المشاركات المعرفية</p>
                    <h2 id="experiences-title">تجارب نوعية تستحق الاقتداء</h2>
                    <p>ممارسات تعليمية ناجحة تثري مجتمع المدرسة وتفتح آفاقاً جديدة للتعلم.</p>
                </div>
                <?php if ($user !== null): ?>
                    <a class="text-link" href="<?= e(url('experiences/index.php')) ?>">عرض بنك التجارب كاملاً <span aria-hidden="true">←</span></a>
                <?php else: ?>
                    <a class="text-link" href="<?= e(url('login.php')) ?>">دخول المعلمات <span aria-hidden="true">←</span></a>
                <?php endif; ?>
            </div>

            <?php if ($user === null): ?>
                <div class="experience-locked">
                    <span class="lock-icon" aria-hidden="true">⌑</span>
                    <div><h3>الخبرات متاحة لمعلمات المدرسة</h3><p>سجّلي الدخول للاطلاع على المشاركات وتجارب زميلاتكِ.</p></div>
                    <a class="button button-primary" href="<?= e(url('login.php')) ?>">دخول المعلمات <span aria-hidden="true">←</span></a>
                </div>
            <?php elseif ($latestExperiences === []): ?>
                <div class="experience-locked">
                    <span class="lock-icon" aria-hidden="true">✎</span>
                    <div><h3>لا توجد خبرات منشورة بعد</h3><p>كوني أول من يشارك ممارسة ناجحة مع زميلاتها.</p></div>
                    <a class="button button-primary" href="<?= e(url('experiences/create.php')) ?>">أضيفي أول خبرة <span aria-hidden="true">←</span></a>
                </div>
            <?php else: ?> <div class="experience-grid">
                    <?php foreach ($latestExperiences as $index => $experience): ?>
                        <a class="experience-card experience-accent-<?= $index % 3 ?>" href="<?= e(url('experiences/show.php?id=' . $experience['id'])) ?>">
                            <span class="category-tag"><?= e(category_label($experience['category'])) ?></span>
                            <h3><?= e($experience['title']) ?></h3>
                            <p class="experience-excerpt"><?= e(mb_substr($experience['content'], 0, 150)) ?></p>
                            <div class="experience-meta">
                                <span class="author-initial" aria-hidden="true"><?= e(mb_substr($experience['author'], 0, 1)) ?></span>
                                <span><?= e($experience['author']) ?><small><?= e(date('Y-m-d', strtotime($experience['created_at']))) ?></small></span>
                                <span class="experience-arrow" aria-hidden="true">←</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section> <section class="page-container cta-section" aria-labelledby="cta-title">
        <div class="cta-card">
            <div class="cta-message">
                <p class="cta-kicker"><span aria-hidden="true">✧</span> معاً نرتقي بالخبرة ونصنع الأثر</p>
                <h2 id="cta-title">عطاء اليوم يُثري مسيرة الغد... معاً نرتقي بمدارسنا</h2>
                <p>تبادل المعرفة والخبرات ركيزة أساسية للتطوير والنمو. بمشاركة الممارسات الناجحة تصبح خبراتنا المهنية مرجعاً يعود بالنفع على الجميع.</p>
                <div class="cta-points"><span>✦ بيئة تعليمية محفزة</span><span>✦ تعلم مهني مستدام</span><span>✦ أثر إيجابي مستمر</span></div>
            </div>
            <div class="cta-action">
                <strong>جاهزة لتوثيق أثر اليوم؟</strong>
                <p>تجربتكِ الصغيرة قد تصنع فرقاً كبيراً لدى زميلة.</p>
                <?php if ($user !== null): ?>
                    <a href="<?= e(url('experiences/create.php')) ?>">شاركي تجربة جديدة الآن <span aria-hidden="true">↗</span></a>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">دخول المعلمات ومشاركة الخبرات <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
                <small>انطلاقاً من قيم الجودة والتميز</small>
            </div>
        </div>
        <div class="privacy-note"><span aria-hidden="true">♡</span><p>نحرص على بيئة تعليمية آمنة ومحفزة؛ محتوى المنصة متاح للحسابات المسجلة ويخضع للمراجعة.</p></div>
    </section>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
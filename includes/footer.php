</main>
<footer class="site-footer">
    <div class="page-container footer-grid">
        <section class="footer-school">
            <span class="footer-brand-mark" aria-hidden="true">✧</span>
            <h2><?= e(SCHOOL_NAME) ?></h2>
            <p>منصة تعليمية لمشاركة الخبرات والممارسات الناجحة بين معلمات المدرسة؛ نحو مجتمع مهني متعاون ومُلهم.</p>
        </section>
        <section class="footer-column">
            <h2>المنصة والتعلم</h2>
            <a href="<?= e(url('index.php#about')) ?>">عن المنصة</a>
            <a href="<?= e(url('index.php#experiences-title')) ?>">كيف تستفيدين؟</a>
            <a href="<?= e(url('experiences/index.php')) ?>">مجالات الخبرات</a>
        </section>
        <section class="footer-column">
            <h2>مصادر المعرفة المهنية</h2>
            <a href="<?= e(url('experiences/index.php')) ?>">استكشاف الخبرات</a>
            <a href="<?= e(url('experiences/create.php')) ?>">مشاركة تجربة</a>
            <a href="<?= e(url('login.php')) ?>">دخول المعلمات</a>
            <a href="<?= e(url('register.php')) ?>">إنشاء حساب جديد</a>
        </section>
        <section class="footer-column footer-message">
            <h2>مجتمع مهني يشارك المعرفة</h2>
            <p>نتعلم من خبراتنا، ونرتقي معاً بأثرنا التربوي.</p>
        </section>
    </div>
    <div class="page-container footer-bottom">
        <p>جميع الحقوق محفوظة © <?= date('Y') ?> <?= e(PLATFORM_NAME) ?> · <?= e(SCHOOL_NAME) ?></p>
        <a href="<?= e(url('login.php')) ?>">الخصوصية والاستخدام</a>
    </div>
</footer>
</body>
</html>

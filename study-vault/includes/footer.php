    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-identity">
                    <div class="footer-lockup">
                        <img src="<?= BASE_URL ?>assets/images/isu-logo.png" alt="Isabela State University seal" class="footer-seal">
                        <div><strong>Study Vault</strong><span>ISU-Ilagan Research Repository</span></div>
                    </div>
                    <p>A scholarly home for thesis and research works from Isabela State University’s Information Technology students and alumni.</p>
                </div>

                <div class="footer-column">
                    <p class="footer-heading">Discover</p>
                    <a href="<?= BASE_URL ?>research.php">Research archive</a>
                    <a href="<?= BASE_URL ?>categories.php">Browse by subject</a>
                    <?php if (is_student_logged_in() || !is_logged_in()): ?><a href="<?= BASE_URL ?>plans.php">Manuscript access</a><?php endif; ?>
                </div>

                <div class="footer-column">
                    <p class="footer-heading">Your account</p>
                    <?php if (is_student_logged_in()): ?>
                        <a href="<?= BASE_URL ?>profile.php">Profile &amp; subscriptions</a>
                        <a href="<?= BASE_URL ?>index.php#access-plans">Access plans</a>
                    <?php elseif (is_logged_in()): ?>
                        <a href="<?= BASE_URL ?>admin/dashboard.php">Administrator workspace</a>
                        <a href="<?= BASE_URL ?>admin/subscriptions.php?tab=plans">Subscriptions &amp; plans</a>
                        <a href="<?= BASE_URL ?>admin/settings.php">Account settings</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>login.php">Student sign in</a>
                        <a href="<?= BASE_URL ?>verify.php">Verify Student ID</a>
                        <a href="<?= BASE_URL ?>admin/login.php">Administrator portal</a>
                    <?php endif; ?>
                </div>

                <div class="footer-note-card">
                    <span class="footer-note-mark" aria-hidden="true">◎</span>
                    <strong>Research, responsibly shared.</strong>
                    <p>Full manuscripts are accessed through a protected, watermarked reader. Abstracts remain available for discovery.</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; <?= date('Y') ?> Study Vault &middot; Isabela State University, Ilagan Campus</span>
                <span>Academic research repository <i aria-hidden="true"></i> <?= PAYMENT_MODE === 'demo' ? 'Demo payments only' : 'Secure access' ?></span>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/script.js"></script>
</body>
</html>

<?php if (is_logged_in()) : ?>
    </main>
    </div>
<?php else : ?>
    </div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(app_url('assets/js/main.js')) ?>"></script>
</body>

</html>
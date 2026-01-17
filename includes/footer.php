<?php
// Get current user untuk footer info
$current_user = getCurrentUser();

// Load settings dengan fallback
global $SISTEM;
if (!isset($SISTEM)) {
    $SISTEM = [
        'nama_perpustakaan' => 'Perpustakaan Nusantara',
        'alamat_perpustakaan' => 'Surabaya, East Java, ID'
    ];
}
?>
    <!-- Main Content Ends Here -->
    </div> <!-- CLOSE #main-wrapper - CRITICAL FOR FOOTER FIX -->
    
    <!-- Footer - FIXED -->
    <footer class="mt-auto py-3" style="background: #ffffff; border-top: 1px solid #dee2e6; width: 100%;">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-book-open me-2 text-primary"></i>
                        <strong><?= defined('SITE_NAME') ? SITE_NAME : 'Perpustakaan Nusantara' ?></strong>
                    </div>
                    <p class="text-muted small mb-0">
                        Sistem Informasi Perpustakaan Digital<br>
                        <?= $SISTEM['alamat_perpustakaan'] ?? 'Surabaya, East Java, ID' ?>
                    </p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <div class="text-muted small">
                        <div class="mb-1">
                            <i class="fas fa-clock me-1"></i>
                            <?= date('l, d F Y H:i') ?> WIB
                        </div>
                        <?php if ($current_user): ?>
                            <div>
                                <i class="fas fa-user me-1"></i>
                                Login: <?= htmlspecialchars($current_user['nama']) ?> 
                                (<?= ucfirst(str_replace('_', ' ', $current_user['role'])) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <hr class="my-2">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted small mb-0">
                        &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? SITE_NAME : 'Perpustakaan Nusantara' ?>. Dikembangkan untuk kemudahan akses informasi.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-muted">
                        Create by Fariz & Faathir
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?= asset_url('assets/js/script.js') ?>"></script>
    
    <!-- Additional JavaScript files -->
    <?php if (isset($additional_js) && is_array($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?= asset_url($js_file) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Inline JavaScript -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?= $inline_js ?>
        </script>
    <?php endif; ?>
    
    <!-- Auto-hide alerts after 5 seconds -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
                alerts.forEach(function(alert) {
                    if (alert.classList.contains('show')) {
                        try {
                            const bsAlert = new bootstrap.Alert(alert);
                            alert.style.transition = 'opacity 0.5s';
                            alert.style.opacity = '0';
                            setTimeout(() => {
                                try { 
                                    bsAlert.close(); 
                                } catch(e) {
                                    // Fallback jika bootstrap Alert gagal
                                    alert.remove();
                                }
                            }, 500);
                        } catch(e) {
                            // Fallback manual remove
                            alert.style.display = 'none';
                        }
                    }
                });
            }, 5000);
        });
        
    </script>

</body>
</html>
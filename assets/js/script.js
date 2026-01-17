// ================================
// MODERN JAVASCRIPT FUNCTIONALITY
// ================================

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Set today's date as default
    setDefaultDates();
    
    // Initialize toasts if they exist
    initializeToasts();
});

// Initialize toasts
function initializeToasts() {
    const toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function(toastEl) {
        const t = new bootstrap.Toast(toastEl, { delay: 3000 });
        t.show();
    });
}

// Toast Notification Functions
function showSuccessToast(message) {
    const successToastEl = document.getElementById('successToast');
    if (successToastEl) {
        const msg = document.getElementById('successMessage');
        if (msg) msg.textContent = message;
        const toast = new bootstrap.Toast(successToastEl);
        toast.show();
    } else {
        showDynamicAlert(message, 'success');
    }
}

function showErrorToast(message) {
    const errorToastEl = document.getElementById('errorToast');
    if (errorToastEl) {
        const msg = document.getElementById('errorMessage');
        if (msg) msg.textContent = message;
        const toast = new bootstrap.Toast(errorToastEl);
        toast.show();
    } else {
        showDynamicAlert(message, 'danger');
    }
}

// Create dynamic alert
function showDynamicAlert(message, type) {
    const oldAlert = document.querySelector('.dynamic-alert');
    if (oldAlert) oldAlert.remove();
    
    const alertHtml = `
        <div class="alert alert-${type} ${type === 'success' ? 'success-alert' : ''} dynamic-alert alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHtml);
        setTimeout(() => {
            const alertElement = document.querySelector('.dynamic-alert');
            if (alertElement) {
                const bsAlert = new bootstrap.Alert(alertElement);
                bsAlert.close();
            }
        }, 5000);
    }
}

// ================================
// ANIMASI TOMBOL KEMBALIKAN BUKU
// ================================

function handleReturnBook(event, form) {
    const button = form.querySelector('.btn-return');
    const bookTitle = form.closest('tr').querySelector('td:nth-child(4)').textContent.trim();
    
    if (!confirm(`Yakin ingin mengembalikan buku "${bookTitle}"?`)) {
        event.preventDefault();
        return false;
    }
    
    button.classList.add('returning');
    button.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i>Mengembalikan...';
    button.disabled = true;
    
    setTimeout(() => {
        button.classList.remove('returning');
        button.classList.add('success');
        button.innerHTML = '<i class="fas fa-check me-1"></i>Berhasil!';
        showDynamicAlert('Buku berhasil dikembalikan dan stok telah bertambah!', 'success');
        setTimeout(() => { form.submit(); }, 800);
    }, 1500);
    
    event.preventDefault();
    return false;
}

function handleReturnBookSimple(event, form) {
    const button = form.querySelector('.btn-return');
    const bookTitle = form.closest('tr').querySelector('td:nth-child(4)').textContent.trim();
    
    if (!confirm(`Yakin ingin mengembalikan buku "${bookTitle}"?`)) {
        event.preventDefault();
        return false;
    }
    
    button.classList.add('returning');
    button.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i>Mengembalikan...';
    button.disabled = true;
    
    return true;
}

// ================================
// FORM VALIDATION
// ================================

function validateForm(formType) {
    if (formType === 'buku') {
        const isbnField = document.querySelector('input[name="isbn"]');
        if (isbnField) {
            const isbn = isbnField.value;
            const isbnPattern = /^(978|979)[-\s]?\d{1,5}[-\s]?\d{1,7}[-\s]?\d{1,6}[-\s]?\d$/;
            if (!isbnPattern.test(isbn)) {
                showErrorToast('Format ISBN tidak valid! Gunakan format: 978-xxx-xxx-xxx-x');
                return false;
            }
        }

        const tahunField = document.querySelector('input[name="tahun"]');
        if (tahunField) {
            const tahun = parseInt(tahunField.value);
            const currentYear = new Date().getFullYear();
            if (tahun < 1900 || tahun > currentYear) {
                showErrorToast('Tahun terbit tidak valid!');
                return false;
            }
        }
    }
    
    if (formType === 'peminjaman') {
        const pinjamField = document.querySelector('input[name="tanggal_pinjam"]');
        const kembaliField = document.querySelector('input[name="tanggal_kembali"]');
        if (pinjamField && kembaliField) {
            const tglPinjam = new Date(pinjamField.value);
            const tglKembali = new Date(kembaliField.value);
            if (tglKembali <= tglPinjam) {
                showErrorToast('Tanggal kembali harus setelah tanggal pinjam!');
                return false;
            }
            const diffTime = Math.abs(tglKembali - tglPinjam);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            if (diffDays > 14) {
                return confirm('Masa pinjam lebih dari 14 hari. Lanjutkan?');
            }
        }
    }
    return true;
}

// ================================
// DELETE FUNCTIONALITY
// ================================

async function confirmDelete(itemType, id) {
    return new Promise((resolve) => {
        showCustomDeleteModal(itemType, id, (result) => {
            if (result) showSuccessToast(`${itemType} berhasil dihapus!`);
            resolve(result);
        });
    });
}

async function hapusPeminjaman(id, nama) {
    const confirmed = await confirmDelete("peminjaman", id);
    if (confirmed) {
        showDynamicAlert('Menghapus data...', 'info');
        setTimeout(() => {
            window.location.href = `proses/hapus_peminjaman.php?id=${id}`;
        }, 600);
    }
}

// ================================
// ANGGOTA DELETE FUNCTIONALITY (FIXED)
// ================================

// Function untuk hapus anggota dengan Bootstrap modal
function openDeleteModal(nik, nama) {
    console.log("openDeleteModal called", { nik, nama });
    
    // Set message di modal
    document.getElementById('deleteMessage').textContent = 
        `Apakah Anda yakin ingin menghapus anggota "${nama}"?`;
    
    // Set href untuk tombol konfirmasi hapus
    document.getElementById('deleteConfirmBtn').href = 
        `proses/hapus_anggota.php?nik=${encodeURIComponent(nik)}`;
    
    // Show modal
    var deleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    deleteModal.show();
}

// ================================
// EDIT ANGGOTA FUNCTIONALITY
// ================================

function openEditModal(nik, nama, no_hp, alamat) {
    console.log("openEditModal called", { nik, nama, no_hp, alamat });

    document.getElementById('edit_nik').value = nik;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_no_hp').value = no_hp;
    document.getElementById('edit_alamat').value = alamat;

    var editModal = new bootstrap.Modal(document.getElementById('editAnggotaModal'));
    editModal.show();
}

// ================================
// MISC FUNCTIONS
// ================================

function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const tglPinjamInput = document.querySelector('input[name="tanggal_pinjam"]');
    const tglKembaliInput = document.querySelector('input[name="tanggal_kembali"]');
    
    if (tglPinjamInput) tglPinjamInput.value = today;
    if (tglKembaliInput) {
        const returnDate = new Date();
        returnDate.setDate(returnDate.getDate() + 7);
        tglKembaliInput.value = returnDate.toISOString().split('T')[0];
    }
}

function showLoadingButton(button, originalText = null) {
    if (!originalText) originalText = button.innerHTML;
    button.innerHTML = '<span class="loading-spinner"></span> Processing...';
    button.disabled = true;
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 1500);
}

function searchTable(tableId, query) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < cells.length; j++) {
            if (cells[j].textContent.toLowerCase().includes(query.toLowerCase())) {
                found = true;
                break;
            }
        }
        row.style.display = (query === '' || found) ? '' : 'none';
    }
}

function showLaporan() {
    window.location.href = 'index.php#laporan';
}

setTimeout(function() {
    document.querySelectorAll('.alert:not(.dynamic-alert)').forEach(function(alert) {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }
    });
}, 5000);

// ================================
// CUSTOM DELETE MODAL
// ================================

function showCustomDeleteModal(itemType, id, callback) {
    const existingModal = document.querySelector('.custom-modal-backdrop');
    if (existingModal) existingModal.remove();

    const modalHTML = `
        <div class="custom-modal-backdrop" id="customDeleteModal">
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <div class="custom-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="custom-modal-title">Konfirmasi Hapus</h3>
                </div>
                <div class="custom-modal-body">
                    <p class="custom-modal-message">
                        Apakah Anda yakin ingin menghapus ${itemType} ini?
                    </p>
                    <p class="custom-modal-submessage">
                        Data yang dihapus tidak dapat dikembalikan.
                    </p>
                    <div class="custom-modal-actions">
                        <button type="button" class="custom-modal-btn custom-modal-btn-cancel" id="cancelDeleteBtn">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="button" class="custom-modal-btn custom-modal-btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash me-2"></i>Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('customDeleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');

    setTimeout(() => { modal.classList.add('show'); }, 10);

    confirmBtn.addEventListener('click', () => {
        confirmBtn.classList.add('loading');
        confirmBtn.innerHTML = '<span class="loading-spinner"></span> Menghapus...';
        setTimeout(() => {
            hideCustomModal(modal);
            callback(true);
        }, 800);
    });
    cancelBtn.addEventListener('click', () => {
        hideCustomModal(modal);
        callback(false);
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hideCustomModal(modal);
            callback(false);
        }
    });
    const handleEscKey = (e) => {
        if (e.key === 'Escape') {
            hideCustomModal(modal);
            callback(false);
            document.removeEventListener('keydown', handleEscKey);
        }
    };
    document.addEventListener('keydown', handleEscKey);
}

function hideCustomModal(modal) {
    modal.classList.remove('show');
    setTimeout(() => { if (modal && modal.parentNode) modal.remove(); }, 300);
}

async function handleDeleteClick(event, itemType, id, deleteUrl) {
    event.preventDefault();
    const confirmed = await confirmDelete(itemType, id);
    if (confirmed) {
        showDynamicAlert('Menghapus data...', 'info');
        setTimeout(() => { window.location.href = deleteUrl; }, 600);
    }
    return false;
}

function confirmDeleteBuku(isbn) {
    return new Promise((resolve) => {
        showCustomDeleteModal('buku', isbn, (result) => { resolve(result); });
    });
}

function showCustomDeleteModalWithDetails(itemType, id, details, callback) {
    const existingModal = document.querySelector('.custom-modal-backdrop');
    if (existingModal) existingModal.remove();

    const modalHTML = `
        <div class="custom-modal-backdrop" id="customDeleteModal">
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <div class="custom-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="custom-modal-title">Konfirmasi Hapus Buku</h3>
                </div>
                <div class="custom-modal-body">
                    <p class="custom-modal-message">
                        Apakah Anda yakin ingin menghapus buku ini?
                    </p>
                    ${details ? `
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0; text-align: left;">
                            <strong>Detail Buku:</strong><br>
                            <small class="text-muted">${details}</small>
                        </div>
                    ` : ''}
                    <p class="custom-modal-submessage">
                        Data yang dihapus tidak dapat dikembalikan.
                    </p>
                    <div class="custom-modal-actions">
                        <button type="button" class="custom-modal-btn custom-modal-btn-cancel" id="cancelDeleteBtn">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="button" class="custom-modal-btn custom-modal-btn-danger" id="confirmDeleteBtn">
                            <i class="fas fa-trash me-2"></i>Hapus Buku
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('customDeleteModal');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');

    setTimeout(() => { modal.classList.add('show'); }, 10);

    confirmBtn.addEventListener('click', () => {
        confirmBtn.classList.add('loading');
        confirmBtn.innerHTML = '<span class="loading-spinner"></span> Menghapus...';
        setTimeout(() => {
            hideCustomModal(modal);
            callback(true);
        }, 800);
    });
    cancelBtn.addEventListener('click', () => {
        hideCustomModal(modal);
        callback(false);
    });
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hideCustomModal(modal);
            callback(false);
        }
    });
    const handleEscKey = (e) => {
        if (e.key === 'Escape') {
            hideCustomModal(modal);
            callback(false);
            document.removeEventListener('keydown', handleEscKey);
        }
    };
    document.addEventListener('keydown', handleEscKey);
}

// ================================
// MOBILE SIDEBAR FUNCTIONALITY
// ================================

document.addEventListener('DOMContentLoaded', function() {
    initMobileSidebar();
});

function initMobileSidebar() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const closeSidebar = document.getElementById('closeSidebar');
    const dropdownToggles = document.querySelectorAll('.mobile-dropdown-toggle');

    // Check if elements exist
    if (!mobileMenuToggle || !mobileSidebar) {
        return; // Exit if mobile sidebar elements don't exist
    }

    // Open Sidebar
    mobileMenuToggle.addEventListener('click', function() {
        mobileSidebar.classList.add('active');
        mobileMenuToggle.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent body scroll
    });

    // Close Sidebar - Close Button
    if (closeSidebar) {
        closeSidebar.addEventListener('click', function() {
            closeMobileSidebar();
        });
    }

    // Close Sidebar - Overlay Click
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            closeMobileSidebar();
        });
    }

    // Close Sidebar - ESC Key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileSidebar.classList.contains('active')) {
            closeMobileSidebar();
        }
    });

    // Dropdown Toggle
    dropdownToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parent = this.closest('.mobile-nav-dropdown');
            
            // Toggle current dropdown
            parent.classList.toggle('open');
            
            // Close other dropdowns
            dropdownToggles.forEach(function(otherToggle) {
                if (otherToggle !== toggle) {
                    const otherParent = otherToggle.closest('.mobile-nav-dropdown');
                    if (otherParent) {
                        otherParent.classList.remove('open');
                    }
                }
            });
        });
    });

    // Close sidebar when clicking a regular link (not dropdown)
    const sidebarLinks = mobileSidebar.querySelectorAll('.mobile-nav-menu > li > a:not(.mobile-dropdown-toggle)');
    sidebarLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            // Small delay to allow navigation to start
            setTimeout(function() {
                closeMobileSidebar();
            }, 200);
        });
    });

    // Close sidebar when clicking dropdown sub-menu links
    const dropdownLinks = mobileSidebar.querySelectorAll('.mobile-dropdown-menu a');
    dropdownLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            setTimeout(function() {
                closeMobileSidebar();
            }, 200);
        });
    });

    // Function to close mobile sidebar
    function closeMobileSidebar() {
        mobileSidebar.classList.remove('active');
        mobileMenuToggle.classList.remove('active');
        document.body.style.overflow = ''; // Restore body scroll
        
        // Close all dropdowns when sidebar closes
        document.querySelectorAll('.mobile-nav-dropdown.open').forEach(function(dropdown) {
            dropdown.classList.remove('open');
        });
    }

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Close sidebar if window is resized to desktop size
            if (window.innerWidth > 991 && mobileSidebar.classList.contains('active')) {
                closeMobileSidebar();
            }
        }, 250);
    });
}


// Global Auth Helpers
function checkAuthRedirect() {
    if (!API.isLoggedIn()) {
        window.location.href = '/app/login.html';
    }
}

function checkGuestRedirect() {
    if (API.isLoggedIn()) {
        window.location.href = '/app/dashboard.html';
    }
}

// Register Alpine.js components
document.addEventListener('alpine:init', () => {
    // Shared Navbar data
    Alpine.data('navbar', () => ({
        isLoggedIn: API.isLoggedIn(),
        user: API.getUser(),
        logout() {
            API.logout();
            window.location.href = '/app/index.html';
        }
    }));

    // Shortener Component (Index Page)
    Alpine.data('shortener', () => ({
        originalUrl: '',
        customAlias: '',
        expiresAt: '',
        idempotencyKey: '',
        result: null,
        loading: false,
        errorMsg: '',
        fieldErrors: {},
        copied: false,
        isLoggedIn: API.isLoggedIn(),

        init() {
            this.generateIdempotencyKey();
        },

        generateIdempotencyKey() {
            this.idempotencyKey = 'idemp_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now();
        },

        async handleSubmit() {
            this.loading = true;
            this.errorMsg = '';
            this.fieldErrors = {};
            this.result = null;

            if (!this.originalUrl) {
                this.errorMsg = 'URL Asli wajib diisi.';
                this.loading = false;
                return;
            }

            // Convert datetime-local to MySQL format
            let formattedExpiry = null;
            if (this.expiresAt) {
                formattedExpiry = this.expiresAt.replace('T', ' ') + ':00';
            }

            const res = await API.shorten(
                this.originalUrl,
                this.customAlias || null,
                formattedExpiry,
                this.idempotencyKey
            );

            this.loading = false;

            if (res.success) {
                this.result = res.data;
                this.generateIdempotencyKey();
                this.originalUrl = '';
                this.customAlias = '';
                this.expiresAt = '';
            } else {
                if (res.status === 400 && typeof res.data === 'object') {
                    this.fieldErrors = res.data;
                } else {
                    this.errorMsg = res.message;
                }
            }
        },

        copyToClipboard() {
            if (!this.result) return;
            navigator.clipboard.writeText(this.result.short_url).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    }));

    // Register Component
    Alpine.data('registerForm', () => ({
        username: '',
        email: '',
        password: '',
        loading: false,
        errorMsg: '',
        fieldErrors: {},

        init() {
            checkGuestRedirect();
        },

        async submit() {
            this.loading = true;
            this.errorMsg = '';
            this.fieldErrors = {};

            const res = await API.register(this.username, this.email, this.password);
            this.loading = false;

            if (res.success) {
                alert('Registrasi berhasil! Silakan login.');
                window.location.href = '/app/login.html';
            } else {
                if (res.status === 400 && typeof res.data === 'object') {
                    this.fieldErrors = res.data;
                } else {
                    this.errorMsg = res.message;
                }
            }
        }
    }));

    // Login Component
    Alpine.data('loginForm', () => ({
        email: '',
        password: '',
        loading: false,
        errorMsg: '',

        init() {
            checkGuestRedirect();
        },

        async submit() {
            this.loading = true;
            this.errorMsg = '';

            const res = await API.login(this.email, this.password);
            this.loading = false;

            if (res.success) {
                window.location.href = '/app/dashboard.html';
            } else {
                this.errorMsg = res.message;
            }
        }
    }));

    // Dashboard Component
    Alpine.data('dashboard', () => ({
        urls: [],
        loading: false,
        page: 1,
        totalPages: 1,
        totalItems: 0,
        perPage: 10,
        
        // Edit Modal State
        editModal: false,
        editingUrl: null,
        editAlias: '',
        editExpiry: '',
        editError: '',
        editFieldErrors: {},

        // Stats Modal State
        statsModal: false,
        statsData: null,
        statsLogs: [],

        // Delete Modal State
        deleteModal: false,
        deletingId: null,

        init() {
            checkAuthRedirect();
            this.fetchUrls();
        },

        async fetchUrls() {
            this.loading = true;
            const res = await API.getUrls(this.page, this.perPage);
            this.loading = false;

            if (res.success && res.data) {
                this.urls = res.data.urls;
                this.page = res.data.pagination.current_page;
                this.totalPages = res.data.pagination.total_pages;
                this.totalItems = res.data.pagination.total_items;
            } else {
                alert('Gagal memuat data: ' + res.message);
            }
        },

        nextPage() {
            if (this.page < this.totalPages) {
                this.page++;
                this.fetchUrls();
            }
        },

        prevPage() {
            if (this.page > 1) {
                this.page--;
                this.fetchUrls();
            }
        },

        copyUrl(shortUrl) {
            navigator.clipboard.writeText(shortUrl).then(() => {
                alert('Tautan disalin ke papan klip!');
            });
        },

        openEditModal(url) {
            this.editingUrl = url;
            this.editAlias = url.custom_alias || url.short_code;
            if (url.expires_at) {
                this.editExpiry = url.expires_at.replace(' ', 'T').substring(0, 16);
            } else {
                this.editExpiry = '';
            }
            this.editError = '';
            this.editFieldErrors = {};
            this.editModal = true;
        },

        async saveEdit() {
            if (!this.editingUrl) return;
            this.editError = '';
            this.editFieldErrors = {};

            let formattedExpiry = null;
            if (this.editExpiry) {
                formattedExpiry = this.editExpiry.replace('T', ' ') + ':00';
            }

            const res = await API.updateUrl(this.editingUrl.id, this.editAlias, formattedExpiry);

            if (res.success) {
                this.editModal = false;
                this.fetchUrls();
            } else {
                if (res.status === 400 && typeof res.data === 'object') {
                    this.editFieldErrors = res.data;
                } else {
                    this.editError = res.message;
                }
            }
        },

        deleteUrl(id) {
            this.deletingId = id;
            this.deleteModal = true;
        },

        async confirmDelete() {
            if (!this.deletingId) return;
            const res = await API.deleteUrl(this.deletingId);
            this.deleteModal = false;
            this.deletingId = null;
            if (res.success) {
                this.fetchUrls();
            } else {
                alert('Gagal menghapus tautan: ' + res.message);
            }
        },

        async openStatsModal(url) {
            this.statsData = url;
            this.statsLogs = [];
            this.statsModal = true;

            const res = await API.getUrlStats(url.id);
            if (res.success && res.data) {
                this.statsData = res.data.url;
                const baseURL = window.location.origin;
                this.statsData.short_url = `${baseURL}/${this.statsData.short_code}`;
                this.statsLogs = res.data.click_logs;
            } else {
                alert('Gagal memuat analitik: ' + res.message);
                this.statsModal = false;
            }
        }
    }));
});

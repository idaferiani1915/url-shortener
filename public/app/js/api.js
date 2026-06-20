const API = {
    // Helper to perform fetch requests with JWT automatically attached
    async request(endpoint, options = {}) {
        const token = localStorage.getItem('url_shortener_token');
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers,
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            ...options,
            headers
        };

        try {
            // Ensure endpoint starts with a slash
            const formattedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
            const response = await fetch(`${window.location.origin}${formattedEndpoint}`, config);
            const text = await response.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                data = { status: 'error', message: 'Respon server tidak valid (bukan JSON).' };
            }

            if (!response.ok) {
                // Return failed response structured
                return {
                    success: false,
                    status: response.status,
                    message: data.message || 'Terjadi kesalahan pada server.',
                    data: data.data || null
                };
            }

            return {
                success: true,
                status: response.status,
                message: data.message || 'Permintaan berhasil.',
                data: data.data || null
            };
        } catch (error) {
            return {
                success: false,
                status: 0,
                message: 'Tidak dapat terhubung ke server. Pastikan koneksi internet aktif.',
                data: null
            };
        }
    },

    // Auth endpoints
    async register(username, email, password) {
        return this.request('/api/register', {
            method: 'POST',
            body: JSON.stringify({ username, email, password })
        });
    },

    async login(email, password) {
        const res = await this.request('/api/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });

        if (res.success && res.data && res.data.token) {
            localStorage.setItem('url_shortener_token', res.data.token);
            localStorage.setItem('url_shortener_user', JSON.stringify(res.data.user));
        }

        return res;
    },

    logout() {
        localStorage.removeItem('url_shortener_token');
        localStorage.removeItem('url_shortener_user');
    },

    isLoggedIn() {
        return localStorage.getItem('url_shortener_token') !== null;
    },

    getUser() {
        const user = localStorage.getItem('url_shortener_user');
        return user ? JSON.parse(user) : null;
    },

    // Shorten endpoint
    async shorten(originalUrl, customAlias = null, expiresAt = null, idempotencyKey = null) {
        const headers = {};
        if (idempotencyKey) {
            headers['Idempotency-Key'] = idempotencyKey;
        }

        const body = { original_url: originalUrl };
        if (customAlias) body.custom_alias = customAlias;
        if (expiresAt) body.expires_at = expiresAt;

        return this.request('/api/shorten', {
            method: 'POST',
            headers,
            body: JSON.stringify(body)
        });
    },

    // Protected endpoints
    async getUrls(page = 1, limit = 10) {
        return this.request(`/api/urls?page=${page}&limit=${limit}`, {
            method: 'GET'
        });
    },

    async updateUrl(id, customAlias = null, expiresAt = null) {
        const body = {};
        // If empty string, send null to clear custom_alias
        body.custom_alias = customAlias === '' ? null : customAlias;
        body.expires_at = expiresAt === '' ? null : expiresAt;

        return this.request(`/api/urls/${id}`, {
            method: 'PUT',
            body: JSON.stringify(body)
        });
    },

    async deleteUrl(id) {
        return this.request(`/api/urls/${id}`, {
            method: 'DELETE'
        });
    },

    async getUrlStats(id) {
        return this.request(`/api/urls/${id}/stats`, {
            method: 'GET'
        });
    }
};

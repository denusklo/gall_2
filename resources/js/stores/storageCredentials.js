import { defineStore } from 'pinia';
import axios from 'axios';

export const useStorageCredentialsStore = defineStore('storageCredentials', {
    state: () => ({
        credentials: [],
        loading: false,
        error: null,
        testing: {
            supabase: false,
            vercel: false
        },
        testResults: {
            supabase: null,
            vercel: null
        },
        // Modal states
        showAddModal: false,
        showEditModal: false,
        editingCredential: null,
        // Form state
        form: {
            provider: 'supabase',
            is_default: false,
            supabase_url: '',
            supabase_key: '',
            supabase_service_key: '',
            supabase_bucket: 'images',
            vercel_blob_token: '',
            vercel_blob_store_url: 'https://blob.vercel-storage.com'
        }
    }),

    getters: {
        supabaseCredentials: (state) => {
            return state.credentials.filter(c => c.provider === 'supabase');
        },
        vercelCredentials: (state) => {
            return state.credentials.filter(c => c.provider === 'vercel');
        },
        defaultCredential: (state) => {
            return state.credentials.find(c => c.is_default) || null;
        },
        hasCredentials: (state) => {
            return state.credentials.length > 0;
        }
    },

    actions: {
        async fetchCredentials() {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.get('/apiv/_1/storage-credentials');
                this.credentials = response.data;
            } catch (error) {
                console.error('Failed to fetch storage credentials:', error);
                this.error = error.response?.data?.message || 'Failed to fetch credentials';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async createCredential(data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.post('/apiv/_1/storage-credentials', data);
                await this.fetchCredentials();
                return response.data;
            } catch (error) {
                console.error('Failed to create credential:', error);
                this.error = error.response?.data?.errors || 'Failed to create credential';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async updateCredential(id, data) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.put(`/apiv/_1/storage-credentials/${id}`, data);
                await this.fetchCredentials();
                return response.data;
            } catch (error) {
                console.error('Failed to update credential:', error);
                this.error = error.response?.data?.errors || 'Failed to update credential';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async deleteCredential(id) {
            this.loading = true;
            this.error = null;
            try {
                await axios.delete(`/apiv/_1/storage-credentials/${id}`);
                await this.fetchCredentials();
            } catch (error) {
                console.error('Failed to delete credential:', error);
                this.error = error.response?.data?.message || 'Failed to delete credential';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async setAsDefault(id) {
            this.loading = true;
            this.error = null;
            try {
                const response = await axios.post(`/apiv/_1/storage-credentials/${id}/default`);
                await this.fetchCredentials();
                return response.data;
            } catch (error) {
                console.error('Failed to set default credential:', error);
                this.error = error.response?.data?.message || 'Failed to set default credential';
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async testCredential(credential) {
            const provider = credential.provider;
            this.testing[provider] = true;
            this.testResults[provider] = null;
            try {
                const response = await axios.post(`/apiv/_1/storage-credentials/${credential.id}/test`);
                this.testResults[provider] = response.data;
                // Refresh so the verified badge/timestamp updates
                await this.fetchCredentials();
                return response.data;
            } catch (error) {
                console.error('Credential test failed:', error);
                this.testResults[provider] = {
                    success: false,
                    message: error.response?.data?.message || 'Connection test failed'
                };
                throw error;
            } finally {
                this.testing[provider] = false;
            }
        },

        async testSupabaseCredentials(url, key, serviceKey) {
            this.testing.supabase = true;
            this.testResults.supabase = null;
            try {
                const response = await axios.post('/apiv/_1/storage-credentials/test/supabase', {
                    url,
                    key,
                    service_key: serviceKey
                });
                this.testResults.supabase = response.data;
                return response.data;
            } catch (error) {
                console.error('Supabase test failed:', error);
                this.testResults.supabase = {
                    success: false,
                    message: error.response?.data?.message || 'Connection test failed'
                };
                throw error;
            } finally {
                this.testing.supabase = false;
            }
        },

        async testVercelCredentials(token) {
            this.testing.vercel = true;
            this.testResults.vercel = null;
            try {
                const response = await axios.post('/apiv/_1/storage-credentials/test/vercel', {
                    token
                });
                this.testResults.vercel = response.data;
                return response.data;
            } catch (error) {
                console.error('Vercel test failed:', error);
                this.testResults.vercel = {
                    success: false,
                    message: error.response?.data?.message || 'Connection test failed'
                };
                throw error;
            } finally {
                this.testing.vercel = false;
            }
        },

        openAddModal() {
            this.showAddModal = true;
            this.resetForm();
        },

        closeAddModal() {
            this.showAddModal = false;
            this.resetForm();
        },

        openEditModal(credential) {
            this.showEditModal = true;
            this.editingCredential = { ...credential };
            // Populate form with existing data
            this.form.provider = credential.provider;
            this.form.is_default = credential.is_default;
            this.form.supabase_url = credential.supabase_url || '';
            this.form.supabase_bucket = credential.supabase_bucket || 'images';
            this.form.vercel_blob_store_url = credential.vercel_blob_store_url || 'https://blob.vercel-storage.com';
        },

        closeEditModal() {
            this.showEditModal = false;
            this.editingCredential = null;
            this.resetForm();
        },

        resetForm() {
            this.form = {
                provider: 'supabase',
                is_default: false,
                supabase_url: '',
                supabase_key: '',
                supabase_service_key: '',
                supabase_bucket: 'images',
                vercel_blob_token: '',
                vercel_blob_store_url: 'https://blob.vercel-storage.com'
            };
        },

        clearTestResults() {
            this.testResults = {
                supabase: null,
                vercel: null
            };
        },

        clearError() {
            this.error = null;
        }
    }
});

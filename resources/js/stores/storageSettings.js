// resources/js/stores/storageSettings.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useStorageSettingsStore = defineStore('storageSettings', {
  state: () => ({
    settings: null,
    loading: false,
    error: null,
    testResults: {
      supabase: null,
      vercel: null
    },
    testing: {
      supabase: false,
      vercel: false
    },
    saving: false
  }),

  getters: {
    hasSupabaseConfig: (state) => {
      return state.settings?.has_supabase_config || false;
    },
    hasVercelConfig: (state) => {
      return state.settings?.has_vercel_config || false;
    },
    isVerified: (state) => {
      return state.settings?.credentials_verified || false;
    },
    lastVerified: (state) => {
      return state.settings?.last_verified_at || null;
    }
  },

  actions: {
    async fetchSettings() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/apiv/_1/storage-settings');
        this.settings = response.data;
      } catch (error) {
        this.error = error.response?.data?.error || 'Failed to fetch storage settings';
        console.error('Error fetching storage settings:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateSettings(data) {
      this.saving = true;
      this.error = null;
      try {
        const method = this.settings ? 'put' : 'post';
        const response = await axios({
          method,
          url: '/apiv/_1/storage-settings',
          data
        });

        // Update local settings
        await this.fetchSettings();
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.error || 'Failed to update storage settings';
        console.error('Error updating storage settings:', error);
        throw error;
      } finally {
        this.saving = false;
      }
    },

    async testSupabaseCredentials(url, key, serviceKey) {
      this.testing.supabase = true;
      this.testResults.supabase = null;
      try {
        const response = await axios.post('/apiv/_1/storage-settings/test/supabase', {
          url,
          key,
          service_key: serviceKey || null
        });
        this.testResults.supabase = {
          success: true,
          ...response.data
        };
        return response.data;
      } catch (error) {
        this.testResults.supabase = {
          success: false,
          message: error.response?.data?.message || 'Connection test failed',
          details: error.response?.data?.details || {}
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
        const response = await axios.post('/apiv/_1/storage-settings/test/vercel', {
          token
        });
        this.testResults.vercel = {
          success: true,
          ...response.data
        };
        return response.data;
      } catch (error) {
        this.testResults.vercel = {
          success: false,
          message: error.response?.data?.message || 'Connection test failed',
          details: error.response?.data?.details || {}
        };
        throw error;
      } finally {
        this.testing.vercel = false;
      }
    },

    async deleteProvider(provider) {
      this.error = null;
      try {
        await axios.delete(`/apiv/_1/storage-settings/${provider}`);
        await this.fetchSettings();
      } catch (error) {
        this.error = error.response?.data?.error || `Failed to delete ${provider} credentials`;
        console.error('Error deleting provider credentials:', error);
        throw error;
      }
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

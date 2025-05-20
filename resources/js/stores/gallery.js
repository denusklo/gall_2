// resources/js/stores/gallery.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useGalleryStore = defineStore('gallery', {
  state: () => ({
    galleries: [],
    loading: false,
    error: null,
    uploadProgress: 0,
    pagination: {
      currentPage: 1,
      totalItems: 0,
      perPage: 12
    },
    stats: {
      totalImages: 0,
      totalStorage: 0,
      recentUploads: 0,
      fileTypes: [],
      timeline: []
    },
    activeCategory: null
  }),

  actions: {
    async fetchGalleries(page = 1, filters = {}) {
      this.loading = true;
      try {
        const params = {
          page,
          search: filters.search || '',
          file_type: filters.fileType || '',
          sort_by: filters.sortBy || 'newest',
          category_id: filters.categoryId || this.activeCategory || ''
        };

        const response = await axios.get('/apiv/_1/galleries', { params });

        this.galleries = response.data.data;
        this.pagination.currentPage = response.data.current_page;
        this.pagination.totalItems = response.data.total;
        this.pagination.perPage = response.data.per_page;
        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch galleries';
        console.error('Error fetching galleries:', error);
      } finally {
        this.loading = false;
      }
    },

    // In your Vue/React component
    async uploadFile(file, title, description = '', categoryId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      try {
        // Create form data for the backend upload
        const formData = new FormData();
        formData.append('file', file);
        formData.append('title', title);
        formData.append('description', description || '');
        if (categoryId) formData.append('category_id', categoryId);

        // Send to our backend endpoint (now handles the Supabase upload)
        const response = await axios.post('/apiv/_1/galleries/upload', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          onUploadProgress: (progressEvent) => {
            this.uploadProgress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          }
        });

        // Add the new gallery to the list
        this.galleries.unshift(response.data);
        this.error = null;
        
        return response.data;
      } catch (error) {
        console.error('Error uploading file:', error);
        this.error = error.response?.data?.message || 'Failed to upload file';
        throw error;
      } finally {
        this.loading = false;
        this.uploadProgress = 0;
      }
    },

    async deleteGallery(id) {
      try {
        await axios.delete(`/apiv/_1/galleries/${id}`);
        this.galleries = this.galleries.filter(gallery => gallery.id !== id);
        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete gallery';
        console.error('Error deleting gallery:', error);
      }
    },

    async updateGallery(id, data) {
      try {
        const response = await axios.put(`/apiv/_1/galleries/${id}`, data);
        const index = this.galleries.findIndex(gallery => gallery.id === id);

        if (index !== -1) {
          this.galleries[index] = { ...this.galleries[index], ...response.data };
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update gallery';
        console.error('Error updating gallery:', error);
        throw error;
      }
    },

    async fetchStats() {
      try {
        const response = await axios.get('/apiv/_1/galleries/stats');
        this.stats = response.data;
        this.error = null;
      } catch (error) {
        console.error('Error fetching gallery stats:', error);
        // Don't set error state to avoid disrupting UI if stats fetch fails
      }
    },

    setActiveCategory(categoryId) {
      this.activeCategory = categoryId;
    }
  }
});
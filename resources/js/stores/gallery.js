// resources/js/stores/gallery.js
import { defineStore } from 'pinia';
import axios from 'axios';
import { put, generateClientToken } from '@vercel/blob/client';

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

    // Modified uploadFile method using Vercel Blob client
    async uploadFile(file, title, description = '', categoryId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      const token = await generateClientToken({
        pathname,
        allowedContentTypes: allowedTypes,
        addRandomSuffix: true
      });


      try {
        // First get a blob token from our backend
        const tokenResponse = await axios.post('/apiv/_1/blob/generate-token', {
          filename: file.name,
          contentType: file.type,
        });

        if (!tokenResponse.data || !tokenResponse.data.tokenPayload) {
          throw new Error('Failed to get upload token');
        }

        // Upload directly to Vercel Blob using the client SDK
        const blob = await put(file.name, file, {
          access: 'public',
          handleUploadUrl: '/apiv/_1/blob/handle-upload',
          clientPayload: tokenResponse.data.tokenPayload,
          token: token,
          onProgress: (progress) => {
            this.uploadProgress = Math.round(progress * 100);
          },
        });

        // Now store the metadata in our database
        const galleryData = {
          title,
          description,
          category_id: categoryId,
          blob_url: blob.url,
          blob_id: blob.pathname.split('/').pop(), // Extract ID from pathname
          filename: file.name,
          mime_type: file.type,
          size: file.size,
        };

        const response = await axios.post('/apiv/_1/galleries', galleryData);

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
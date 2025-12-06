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

    // Original Supabase upload method
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

    // Vercel Blob upload method - Manual implementation (no SDK)
    async uploadFileToVercel(file, title, description = '', categoryId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      try {
        // Step 1: Get client upload token from our backend
        console.log('Step 1: Requesting client token from backend...');
        const tokenResponse = await axios.post('/apiv/_1/vercel/generate-client-token', {
          filename: file.name,
          content_type: file.type,
          size: file.size,
          title: title,
          description: description,
          category_id: categoryId
        });

        const { clientToken, pathname, metadata } = tokenResponse.data;
        console.log('Client token received. Pathname:', pathname);

        // Step 2: Upload directly to Vercel Blob Storage
        // CORRECT endpoint: https://vercel.com/api/blob (not blob.vercel-storage.com!)
        const uploadUrl = `https://vercel.com/api/blob/${pathname}`;
        console.log('Step 2: Uploading to Vercel Blob:', uploadUrl);

        // Create a clean axios instance without any default headers for Vercel upload
        const vercelAxios = axios.create();

        // Remove all common headers (including CSRF token)
        vercelAxios.defaults.headers.common = {};

        const uploadResponse = await vercelAxios.put(uploadUrl, file, {
          headers: {
            'Authorization': `Bearer ${clientToken}`,
            'Content-Type': file.type,
          },
          onUploadProgress: (progressEvent) => {
            const progress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
            this.uploadProgress = progress;
            console.log(`Upload progress: ${progress}%`);
          }
        });

        console.log('Upload successful! Vercel response:', uploadResponse.data);

        // Step 3: Notify our backend to save in the database
        console.log('Step 3: Saving to database...');
        const callbackResponse = await axios.post('/apiv/_1/vercel/upload-callback', {
          blob: {
            url: uploadResponse.data.url,
            pathname: uploadResponse.data.pathname || pathname,
            size: uploadResponse.data.size || file.size,
            contentType: uploadResponse.data.contentType || file.type,
            downloadUrl: uploadResponse.data.downloadUrl
          },
          metadata: metadata
        });

        console.log('Gallery saved successfully!', callbackResponse.data.gallery);

        // Add the new gallery to the list
        this.galleries.unshift(callbackResponse.data.gallery);
        this.error = null;

        return callbackResponse.data.gallery;
      } catch (error) {
        console.error('Error uploading to Vercel:', error);
        console.error('Error details:', error.response?.data);

        // Provide more detailed error messages
        let errorMessage = 'Failed to upload to Vercel';
        if (error.response?.data?.error) {
          errorMessage = error.response.data.error;
        } else if (error.response?.data?.message) {
          errorMessage = error.response.data.message;
        } else if (error.message) {
          errorMessage = error.message;
        }

        this.error = errorMessage;
        throw new Error(errorMessage);
      } finally {
        this.loading = false;
        this.uploadProgress = 0;
      }
    },

    async deleteGallery(id) {
      try {
        const gallery = this.galleries.find(g => g.id === id);
        
        // If it's a Vercel upload, delete from Vercel
        if (gallery && gallery.storage_provider === 'vercel') {
          await axios.delete('/apiv/_1/vercel/delete-blob', {
            data: { url: gallery.storage_url }
          });
        }

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
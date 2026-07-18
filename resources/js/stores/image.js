// resources/js/stores/image.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useImageStore = defineStore('image', {
  state: () => ({
    images: [],
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
    async fetchImages(page = 1, filters = {}) {
      this.loading = true;
      try {
        const params = {
          page,
          search: filters.search || '',
          file_type: filters.fileType || '',
          sort_by: filters.sortBy || 'newest',
          category_id: filters.categoryId || this.activeCategory || ''
        };

        const response = await axios.get('/apiv/_1/images', { params });

        this.images = response.data.data;
        this.pagination.currentPage = response.data.current_page;
        this.pagination.totalItems = response.data.total;
        this.pagination.perPage = response.data.per_page;
        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch images';
        console.error('Error fetching images:', error);
      } finally {
        this.loading = false;
      }
    },

    // Original Supabase upload method
    async uploadFile(file, title, description = '', categoryIds = [], credentialId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      try {
        // Create form data for the backend upload
        const formData = new FormData();
        formData.append('file', file);
        formData.append('title', title);
        formData.append('description', description || '');
        if (credentialId) {
          formData.append('credential_id', credentialId);
        }
        if (categoryIds && categoryIds.length > 0) {
          categoryIds.forEach((id, index) => {
            formData.append(`category_ids[${index}]`, id);
          });
        }

        // Send to our backend endpoint (now handles the Supabase upload)
        const response = await axios.post('/apiv/_1/images/upload', formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          onUploadProgress: (progressEvent) => {
            this.uploadProgress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          }
        });

        // Add the new image to the list
        this.images.unshift(response.data);
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
    async uploadFileToVercel(file, title, description = '', categoryIds = [], credentialId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      try {
        // Step 1: Get client upload token from our backend
        const tokenResponse = await axios.post('/apiv/_1/vercel/generate-client-token', {
          filename: file.name,
          content_type: file.type,
          size: file.size,
          title: title,
          description: description,
          category_ids: categoryIds,
          credential_id: credentialId ?? null
        });

        const { clientToken, pathname, metadata } = tokenResponse.data;

        // Step 2: Upload directly to Vercel Blob Storage
        // CORRECT endpoint: https://vercel.com/api/blob (not blob.vercel-storage.com!)
        const uploadUrl = `https://vercel.com/api/blob/${pathname}`;

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
          }
        });


        // Step 3: Notify our backend to save in the database
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


        // Add the new image to the list
        this.images.unshift(callbackResponse.data.image);
        this.error = null;

        return callbackResponse.data.image;
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

    async deleteImage(id) {
      const image = this.images.find(img => img.id === id);

      if (!image) {
        console.error('[imageStore] Image not found in local state');
        throw new Error('Image not found');
      }

      try {
        // If it's a Vercel upload, delete from Vercel first
        if (image.storage_provider === 'vercel') {
          await axios.post('/apiv/_1/vercel/delete-blob', {
            url: image.storage_url,
            credential_id: image.storage_credential_id ?? null
          });
        }

        // Then delete from database
        await axios.delete(`/apiv/_1/images/${id}`);

        // Only remove from local state if BOTH deletions succeeded
        this.images = this.images.filter(img => img.id !== id);
        this.error = null;
      } catch (error) {
        console.error('[imageStore] Error deleting image:', error);
        console.error('[imageStore] Error response:', error.response);
        const errorMessage = error.response?.data?.error || error.response?.data?.message || error.message || 'Failed to delete image';
        this.error = errorMessage;
        throw new Error(errorMessage);
      }
    },

    async updateImage(id, data) {
      try {
        // If data contains category_ids, ensure it's an array
        const updateData = { ...data };
        if (updateData.category_ids && !Array.isArray(updateData.category_ids)) {
          updateData.category_ids = [updateData.category_ids];
        }

        const response = await axios.put(`/apiv/_1/images/${id}`, updateData);
        const index = this.images.findIndex(image => image.id === id);

        if (index !== -1) {
          this.images[index] = { ...this.images[index], ...response.data };
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update image';
        console.error('Error updating image:', error);
        throw error;
      }
    },

    async fetchStats() {
      try {
        const response = await axios.get('/apiv/_1/images/stats');
        this.stats = response.data;
        this.error = null;
      } catch (error) {
        console.error('Error fetching image stats:', error);
        // Don't set error state to avoid disrupting UI if stats fetch fails
      }
    },

    setActiveCategory(categoryId) {
      this.activeCategory = categoryId;
    }
  }
});

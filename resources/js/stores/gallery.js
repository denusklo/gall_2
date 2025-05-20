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
    // resources/js/stores/gallery.js - Update the fetchGalleries method
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

        const response = await axios.get('/api/v1/galleries', { params });

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


    // resources/js/stores/gallery.js - Update the uploadFile method
    async uploadFile(file, title, description = '', categoryId = null) {
      this.loading = true;
      this.uploadProgress = 0;

      try {
        // First get a presigned URL from our backend
        const presignedUrlResponse = await axios.post('/api/v1/upload-blob', {
          filename: file.name,
          contentType: file.type,
        });

        if (!presignedUrlResponse.data || !presignedUrlResponse.data.uploadUrl) {
          throw new Error('Failed to get upload URL');
        }

        const { uploadUrl, url, pathname } = presignedUrlResponse.data;

        // Upload directly to Vercel Blob using the presigned URL
        const formData = new FormData();

        // Add all the fields required by Vercel Blob
        Object.entries(presignedUrlResponse.data.blob).forEach(([key, value]) => {
          formData.append(key, value);
        });

        // Add the file
        formData.append('file', file);

        // Use XMLHttpRequest to track upload progress
        const xhr = new XMLHttpRequest();

        // Create a promise for the upload
        const uploadPromise = new Promise((resolve, reject) => {
          xhr.open('POST', uploadUrl, true);

          xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
              this.uploadProgress = Math.round((event.loaded * 100) / event.total);
            }
          };

          xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
              resolve(xhr.response);
            } else {
              reject({
                status: xhr.status,
                statusText: xhr.statusText
              });
            }
          };

          xhr.onerror = () => {
            reject({
              status: xhr.status,
              statusText: xhr.statusText
            });
          };

          xhr.send(formData);
        });

        // Wait for the upload to complete
        await uploadPromise;

        // Now store the metadata in our database
        const galleryData = {
          title,
          description,
          category_id: categoryId,
          blob_url: url,
          blob_id: pathname.split('/').pop(), // Extract ID from pathname
          filename: file.name,
          mime_type: file.type,
          size: file.size,
        };

        const response = await axios.post('/api/v1/galleries', galleryData);

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
        await axios.delete(`/api/v1/galleries/${id}`);
        this.galleries = this.galleries.filter(gallery => gallery.id !== id);
        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete gallery';
        console.error('Error deleting gallery:', error);
      }
    },

    async updateGallery(id, data) {
      try {
        const response = await axios.put(`/api/v1/galleries/${id}`, data);
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
        const response = await axios.get('/api/v1/galleries/stats');
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
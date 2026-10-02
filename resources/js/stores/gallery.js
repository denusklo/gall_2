// resources/js/stores/gallery.js - NEW: For managing gallery albums
import { defineStore } from 'pinia';
import axios from 'axios';

export const useGalleryStore = defineStore('gallery', {
  state: () => ({
    galleries: [],
    currentGallery: null,
    loading: false,
    error: null,
    pagination: {
      currentPage: 1,
      totalItems: 0,
      perPage: 12
    }
  }),

  actions: {
    async fetchGalleries(page = 1, filters = {}) {
      this.loading = true;
      try {
        const params = {
          page,
          search: filters.search || '',
          sort_by: filters.sortBy || 'newest'
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

    async fetchGallery(id) {
      this.loading = true;
      try {
        const response = await axios.get(`/apiv/_1/galleries/${id}`);
        this.currentGallery = response.data;
        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch gallery';
        console.error('Error fetching gallery:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createGallery(data) {
      this.loading = true;
      try {
        const response = await axios.post('/apiv/_1/galleries', {
          title: data.title,
          description: data.description,
          cover_image_id: data.cover_image_id || null
        });


        let newGallery = {
          ...response.data,
          images_count: response.data.images_count || 0
        };

        // If image_ids were provided, add them to the gallery
        if (data.image_ids && data.image_ids.length > 0) {

          // Add images one by one (excluding cover image if already added by backend)
          for (const imageId of data.image_ids) {
            try {
              await this.addImageToGallery(newGallery.id, imageId);
            } catch (err) {
              // If it's a duplicate error, ignore it (cover image was already added)
              if (err.response?.status !== 409) {
                throw err;
              }
            }
          }

          // Fetch the updated gallery with correct images_count
          const updatedResponse = await axios.get(`/apiv/_1/galleries/${newGallery.id}`);
          newGallery = {
            ...updatedResponse.data,
            images_count: updatedResponse.data.images?.length || data.image_ids.length
          };
        }

        this.galleries.unshift(newGallery);
        this.pagination.totalItems += 1;


        this.error = null;
        return newGallery;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create gallery';
        console.error('[GalleryStore] Error creating gallery:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateGallery(id, data) {
      try {
        const response = await axios.put(`/apiv/_1/galleries/${id}`, {
          title: data.title,
          description: data.description,
          cover_image_id: data.cover_image_id
        });

        const index = this.galleries.findIndex(g => g.id === id);
        if (index !== -1) {
          this.galleries[index] = { ...this.galleries[index], ...response.data };
        }

        if (this.currentGallery && this.currentGallery.id === id) {
          this.currentGallery = { ...this.currentGallery, ...response.data };
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update gallery';
        console.error('Error updating gallery:', error);
        throw error;
      }
    },

    async deleteGallery(id) {
      try {
        await axios.delete(`/apiv/_1/galleries/${id}`);
        this.galleries = this.galleries.filter(g => g.id !== id);

        if (this.currentGallery && this.currentGallery.id === id) {
          this.currentGallery = null;
        }

        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete gallery';
        console.error('Error deleting gallery:', error);
        throw error;
      }
    },

    async addImageToGallery(galleryId, imageId) {
      try {
        const response = await axios.post(`/apiv/_1/galleries/${galleryId}/images/${imageId}`);

        if (this.currentGallery && this.currentGallery.id === galleryId) {
          this.currentGallery = response.data.gallery;
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to add image to gallery';
        console.error('Error adding image to gallery:', error);
        throw error;
      }
    },

    async removeImageFromGallery(galleryId, imageId) {
      try {
        const response = await axios.delete(`/apiv/_1/galleries/${galleryId}/images/${imageId}`);

        if (this.currentGallery && this.currentGallery.id === galleryId) {
          this.currentGallery = response.data.gallery;
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to remove image from gallery';
        console.error('Error removing image from gallery:', error);
        throw error;
      }
    },

    async setCoverImage(galleryId, imageId) {
      try {
        const response = await axios.put(`/apiv/_1/galleries/${galleryId}/cover`, {
          image_id: imageId
        });

        const index = this.galleries.findIndex(g => g.id === galleryId);
        if (index !== -1) {
          this.galleries[index] = response.data.gallery;
        }

        if (this.currentGallery && this.currentGallery.id === galleryId) {
          this.currentGallery = response.data.gallery;
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to set cover image';
        console.error('Error setting cover image:', error);
        throw error;
      }
    },

    async reorderImages(galleryId, imageIds) {
      try {
        const response = await axios.put(`/apiv/_1/galleries/${galleryId}/images/reorder`, {
          image_ids: imageIds
        });

        if (this.currentGallery && this.currentGallery.id === galleryId) {
          this.currentGallery = response.data.gallery;
        }

        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reorder images';
        console.error('Error reordering images:', error);
        throw error;
      }
    }
  }
});

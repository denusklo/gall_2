// resources/js/stores/category.js
import { defineStore } from 'pinia';
import axios from 'axios';

export const useCategoryStore = defineStore('category', {
  state: () => ({
    categories: [],
    loading: false,
    error: null
  }),

  getters: {
    categoriesWithCount() {
      return this.categories.map(category => ({
        ...category,
        count: category.galleries_count || 0
      }));
    },

    hasCategoriesWithImages() {
      return this.categories.some(cat => cat.galleries_count > 0);
    }
  },

  actions: {
    async fetchCategories() {
      this.loading = true;
      try {
        const response = await axios.get('/apiv/_1/categories');
        // Ensure we're working with an array
        this.categories = Array.isArray(response.data)
          ? response.data
          : (response.data.data || []); // Try to get data array if response is an object

        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to fetch categories';
        console.error('Error fetching categories:', error);
        this.categories = []; // Reset to empty array on error
      } finally {
        this.loading = false;
      }
    },

    async createCategory(data) {
      this.loading = true;
      try {
        const response = await axios.post('/apiv/_1/categories', data);
        this.categories.push(response.data);
        this.categories.sort((a, b) => a.name.localeCompare(b.name));
        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to create category';
        console.error('Error creating category:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateCategory(id, data) {
      this.loading = true;
      try {
        const response = await axios.put(`/apiv/_1/categories/${id}`, data);
        const index = this.categories.findIndex(category => category.id === id);

        if (index !== -1) {
          this.categories[index] = { ...this.categories[index], ...response.data };
        }

        this.categories.sort((a, b) => a.name.localeCompare(b.name));
        this.error = null;
        return response.data;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to update category';
        console.error('Error updating category:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async deleteCategory(id) {
      this.loading = true;
      try {
        await axios.delete(`/apiv/_1/categories/${id}`);
        this.categories = this.categories.filter(category => category.id !== id);
        this.error = null;
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to delete category';
        console.error('Error deleting category:', error);
        throw error;
      } finally {
        this.loading = false;
      }
    }
  }
});
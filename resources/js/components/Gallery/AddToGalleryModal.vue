<template>
  <transition name="fade">
    <div class="gallery-modal-backdrop" @click="closeModal">
      <div class="gallery-modal" @click.stop>
        <div class="gallery-modal-header">
          <h2>Add to Gallery</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>

        <div class="gallery-modal-body">
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <div v-if="success" class="success-message">
            {{ success }}
          </div>

          <div class="image-preview">
            <img :src="getImageUrl(image)" :alt="image.title" class="preview-image" />
            <p class="image-title">{{ image.title }}</p>
          </div>

          <div v-if="loading" class="loading">
            <div class="spinner"></div>
            <p>Loading galleries...</p>
          </div>

          <div v-else-if="availableGalleries.length === 0" class="no-galleries">
            <p>You don't have any galleries yet.</p>
            <p class="hint">Create a gallery first from the Galleries page.</p>
          </div>

          <div v-else class="galleries-list">
            <p class="select-label">Select a gallery:</p>
            <div
              v-for="gallery in availableGalleries"
              :key="gallery.id"
              class="gallery-option"
              :class="{ selected: selectedGalleryId === gallery.id }"
              @click="selectedGalleryId = gallery.id"
            >
              <div class="option-content">
                <div class="option-cover">
                  <img
                    v-if="getCoverImageUrl(gallery)"
                    :src="getCoverImageUrl(gallery)"
                    :alt="gallery.title"
                    class="cover-thumbnail"
                  />
                  <div v-else class="cover-placeholder">
                    <i class="fas fa-images"></i>
                  </div>
                </div>
                <div class="option-info">
                  <h4>{{ gallery.title }}</h4>
                  <p class="image-count">
                    {{ gallery.images_count || 0 }} {{ (gallery.images_count || 0) === 1 ? 'image' : 'images' }}
                  </p>
                </div>
              </div>
              <div v-if="selectedGalleryId === gallery.id" class="selected-check">
                <i class="fas fa-check-circle"></i>
              </div>
            </div>
          </div>

          <div class="gallery-modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button
              type="button"
              @click="handleAdd"
              class="btn btn-primary"
              :disabled="!selectedGalleryId || adding"
            >
              <span v-if="adding">Adding...</span>
              <span v-else>Add to Gallery</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import axios from 'axios';

console.log('[AddToGalleryModal] Component script loading...');

const props = defineProps({
  image: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close', 'image-added']);

const galleryStore = useGalleryStore();

const selectedGalleryId = ref(null);
const loading = ref(false);
const adding = ref(false);
const error = ref(null);
const success = ref(null);
const availableGalleries = ref([]);
const supabaseUrl = ref('');

console.log('[AddToGalleryModal] Component initialized with image:', props.image);

onMounted(async () => {
  console.log('[AddToGalleryModal] Component mounted');
  await fetchSupabaseUrl();
  await loadGalleries();
});

const fetchSupabaseUrl = async () => {
  const cachedUrl = localStorage.getItem('supabaseUrl');
  if (cachedUrl) {
    supabaseUrl.value = cachedUrl;
    return;
  }

  try {
    const response = await axios.get('/apiv/_1/config/supabase-url');
    supabaseUrl.value = response.data.url;
    localStorage.setItem('supabaseUrl', response.data.url);
  } catch (error) {
    console.error('[AddToGalleryModal] Failed to fetch Supabase URL:', error);
  }
};

const getImageUrl = (image) => {
  if (!image || !image.storage_url) {
    return 'https://placehold.co/400';
  }

  const storedUrl = image.storage_url;

  // If the URL starts with http or https, it's a complete URL
  if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
    return storedUrl;
  }

  // If no Supabase URL is available yet, use a placeholder
  if (!supabaseUrl.value) {
    return 'https://placehold.co/400';
  }

  // If it's a relative path, prepend the Supabase URL
  return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
};

const getCoverImageUrl = (gallery) => {
  if (!gallery.cover_image?.storage_url) {
    return null;
  }

  const storedUrl = gallery.cover_image.storage_url;

  // If the URL starts with http or https, it's a complete URL
  if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
    return storedUrl;
  }

  // If no Supabase URL is available yet, return null
  if (!supabaseUrl.value) {
    return null;
  }

  // If it's a relative path, prepend the Supabase URL
  return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
};

const loadGalleries = async () => {
  loading.value = true;
  try {
    console.log('[AddToGalleryModal] Loading galleries...');
    await galleryStore.fetchGalleries(1, {});
    availableGalleries.value = galleryStore.galleries;
    console.log('[AddToGalleryModal] Galleries loaded:', availableGalleries.value.length);
  } catch (err) {
    console.error('[AddToGalleryModal] Error loading galleries:', err);
    error.value = 'Failed to load galleries';
  } finally {
    loading.value = false;
  }
};

const handleAdd = async () => {
  if (!selectedGalleryId.value) {
    error.value = 'Please select a gallery';
    return;
  }

  adding.value = true;
  error.value = null;
  success.value = null;

  try {
    console.log('[AddToGalleryModal] Adding image to gallery:', selectedGalleryId.value);
    await galleryStore.addImageToGallery(selectedGalleryId.value, props.image.id);

    success.value = 'Image added successfully!';
    console.log('[AddToGalleryModal] Image added successfully');

    // Close modal after a short delay to show success message
    setTimeout(() => {
      emit('image-added');
    }, 1000);
  } catch (err) {
    console.error('[AddToGalleryModal] Error adding image:', err);
    error.value = err.response?.data?.error || err.response?.data?.message || 'Failed to add image to gallery';
  } finally {
    adding.value = false;
  }
};

const closeModal = () => {
  console.log('[AddToGalleryModal] closeModal called');
  emit('close');
};
</script>

<style scoped>
.gallery-modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex !important;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.gallery-modal {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  max-height: 90vh;
  overflow-y: auto;
  display: block !important;
  position: relative;
  z-index: 10000;
}

.gallery-modal-header {
  padding: 20px;
  border-bottom: 1px solid #ddd;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.gallery-modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  color: #666;
}

.close-btn:hover {
  color: #333;
}

.gallery-modal-body {
  padding: 20px;
}

.error-message {
  background-color: #f8d7da;
  color: #721c24;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 20px;
  border: 1px solid #f5c6cb;
}

.success-message {
  background-color: #d4edda;
  color: #155724;
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 20px;
  border: 1px solid #c3e6cb;
}

.image-preview {
  text-align: center;
  margin-bottom: 20px;
  padding: 15px;
  background-color: #f8f9fa;
  border-radius: 8px;
}

.preview-image {
  max-width: 200px;
  max-height: 200px;
  border-radius: 8px;
  object-fit: cover;
}

.image-title {
  margin-top: 10px;
  font-weight: 600;
  color: #333;
}

.loading {
  text-align: center;
  padding: 40px 0;
}

.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 20px;
  border: 4px solid rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  border-top-color: #007bff;
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.no-galleries {
  text-align: center;
  padding: 40px 20px;
  background-color: #f8f9fa;
  border-radius: 8px;
}

.hint {
  font-size: 0.9rem;
  color: #666;
  margin-top: 10px;
}

.select-label {
  font-weight: 600;
  margin-bottom: 15px;
  color: #333;
}

.galleries-list {
  max-height: 400px;
  overflow-y: auto;
}

.gallery-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 8px;
  margin-bottom: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.gallery-option:hover {
  border-color: #007bff;
  background-color: #f8f9fa;
}

.gallery-option.selected {
  border-color: #007bff;
  background-color: #e7f3ff;
}

.option-content {
  display: flex;
  align-items: center;
  gap: 15px;
  flex: 1;
}

.option-cover {
  width: 60px;
  height: 60px;
  border-radius: 6px;
  overflow: hidden;
  background-color: #f0f0f0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cover-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover-placeholder {
  font-size: 1.5rem;
  color: #ccc;
}

.option-info h4 {
  margin: 0 0 5px 0;
  font-size: 1rem;
  color: #333;
}

.image-count {
  margin: 0;
  font-size: 0.85rem;
  color: #666;
}

.selected-check {
  color: #007bff;
  font-size: 1.5rem;
}

.gallery-modal-footer {
  padding: 15px 20px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 500;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0069d9;
}

.btn-primary:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background-color: #5a6268;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

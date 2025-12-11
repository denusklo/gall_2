<template>
  <transition name="fade">
    <div class="modal-backdrop" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>Add Images to Gallery</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>

        <div class="modal-body">
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <div v-if="success" class="success-message">
            {{ success }}
          </div>

          <div v-if="loading" class="loading">
            <div class="spinner"></div>
            <p>Loading images...</p>
          </div>

          <div v-else-if="availableImages.length === 0" class="no-images">
            <p>No images available to add.</p>
            <p class="hint">All your images are already in this gallery.</p>
          </div>

          <div v-else>
            <p class="select-label">Select images to add ({{ selectedImages.length }} selected):</p>

            <div class="images-grid">
              <div
                v-for="image in availableImages"
                :key="image.id"
                class="image-card"
                :class="{ selected: selectedImages.includes(image.id) }"
                @click="toggleImageSelection(image.id)"
              >
                <img :src="getImageUrl(image)" :alt="image.title" />
                <div class="image-overlay">
                  <div v-if="selectedImages.includes(image.id)" class="check-icon">
                    <i class="fas fa-check-circle"></i>
                  </div>
                </div>
                <div class="image-title-overlay">{{ image.title }}</div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button
              type="button"
              @click="handleAdd"
              class="btn btn-primary"
              :disabled="selectedImages.length === 0 || adding"
            >
              <span v-if="adding">Adding {{ addedCount }}/{{ selectedImages.length }}...</span>
              <span v-else>Add {{ selectedImages.length }} {{ selectedImages.length === 1 ? 'Image' : 'Images' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useImageStore } from '../../stores/image';
import { useGalleryStore } from '../../stores/gallery';
import axios from 'axios';

const props = defineProps({
  galleryId: {
    type: [String, Number],
    required: true
  },
  existingImageIds: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['close', 'images-added']);

const imageStore = useImageStore();
const galleryStore = useGalleryStore();

const selectedImages = ref([]);
const loading = ref(false);
const adding = ref(false);
const addedCount = ref(0);
const error = ref(null);
const success = ref(null);
const availableImages = ref([]);
const supabaseUrl = ref('');

onMounted(async () => {
  await fetchSupabaseUrl();
  await loadImages();
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
    console.error('[AddImagesToGalleryModal] Failed to fetch Supabase URL:', error);
  }
};

const loadImages = async () => {
  loading.value = true;
  try {
    await imageStore.fetchImages(1, {});
    // Filter out images that are already in the gallery
    availableImages.value = imageStore.images.filter(
      img => !props.existingImageIds.includes(img.id)
    );
  } catch (err) {
    console.error('[AddImagesToGalleryModal] Error loading images:', err);
    error.value = 'Failed to load images';
  } finally {
    loading.value = false;
  }
};

const getImageUrl = (image) => {
  if (!image || !image.storage_url) {
    return 'https://placehold.co/150';
  }

  const storedUrl = image.storage_url;

  if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
    return storedUrl;
  }

  if (!supabaseUrl.value) {
    return 'https://placehold.co/150';
  }

  return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
};

const toggleImageSelection = (imageId) => {
  const index = selectedImages.value.indexOf(imageId);
  if (index > -1) {
    selectedImages.value.splice(index, 1);
  } else {
    selectedImages.value.push(imageId);
  }
};

const handleAdd = async () => {
  if (selectedImages.value.length === 0) {
    error.value = 'Please select at least one image';
    return;
  }

  adding.value = true;
  addedCount.value = 0;
  error.value = null;
  success.value = null;

  try {
    // Add images one by one
    for (const imageId of selectedImages.value) {
      await galleryStore.addImageToGallery(props.galleryId, imageId);
      addedCount.value++;
    }

    success.value = `Successfully added ${selectedImages.value.length} ${selectedImages.value.length === 1 ? 'image' : 'images'}!`;

    // Close modal after a short delay
    setTimeout(() => {
      emit('images-added');
    }, 1000);
  } catch (err) {
    console.error('[AddImagesToGalleryModal] Error adding images:', err);
    error.value = err.response?.data?.error || err.response?.data?.message || 'Failed to add images to gallery';
  } finally {
    adding.value = false;
  }
};

const closeModal = () => {
  emit('close');
};
</script>

<style scoped>
.modal-backdrop {
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

.modal-content {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: block !important;
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #ddd;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
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

.modal-body {
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

.no-images {
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

.images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
  max-height: 500px;
  overflow-y: auto;
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 20px;
}

.image-card {
  position: relative;
  aspect-ratio: 1;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 3px solid transparent;
  transition: all 0.2s;
}

.image-card:hover {
  transform: scale(1.05);
  border-color: #007bff;
}

.image-card.selected {
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.3);
}

.image-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  opacity: 0;
  transition: opacity 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-card:hover .image-overlay,
.image-card.selected .image-overlay {
  opacity: 1;
}

.check-icon {
  color: white;
  font-size: 2rem;
}

.image-title-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
  color: white;
  padding: 8px 6px 4px;
  font-size: 0.75rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.modal-footer {
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

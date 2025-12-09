<template>
  <transition name="fade">
    <div class="gallery-modal-backdrop" @click="closeModal">
      <div class="gallery-modal" @click.stop>
        <div class="gallery-modal-header">
          <h2>Create New Gallery</h2>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>

        <div class="gallery-modal-body">
          <form @submit.prevent="handleSubmit">
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <div class="form-group">
            <label for="title">Title <span class="required">*</span></label>
            <input
              id="title"
              v-model="formData.title"
              type="text"
              class="form-control"
              placeholder="My Gallery"
              required
            />
          </div>

          <div class="form-group">
            <label for="description">Description</label>
            <textarea
              id="description"
              v-model="formData.description"
              class="form-control"
              placeholder="Optional description..."
              rows="3"
            ></textarea>
          </div>

          <div class="form-group">
            <label>Select Images</label>
            <p v-if="!availableImages.length" class="form-hint">
              No images available. Upload images first to add them to the gallery.
            </p>
            <div v-else class="images-grid">
              <div
                v-for="image in availableImages"
                :key="image.id"
                class="image-card"
                :class="{
                  selected: formData.selectedImages.includes(image.id),
                  'is-cover': formData.cover_image_id === image.id
                }"
                @click="toggleImageSelection(image.id)"
              >
                <img :src="getImageUrl(image)" :alt="image.title" />
                <div class="image-overlay">
                  <div v-if="formData.selectedImages.includes(image.id)" class="check-icon">
                    <i class="fas fa-check-circle"></i>
                  </div>
                  <div v-if="formData.cover_image_id === image.id" class="cover-badge">
                    Cover
                  </div>
                </div>
                <div class="image-title-overlay">{{ image.title }}</div>
              </div>
            </div>
            <p v-if="formData.selectedImages.length > 0" class="form-hint">
              {{ formData.selectedImages.length }} {{ formData.selectedImages.length === 1 ? 'image' : 'images' }} selected
            </p>
          </div>

          <div v-if="formData.selectedImages.length > 0" class="form-group">
            <label for="cover_image_id">Cover Image</label>
            <select
              id="cover_image_id"
              v-model="formData.cover_image_id"
              class="form-control"
            >
              <option :value="null">Select from selected images...</option>
              <option
                v-for="imageId in formData.selectedImages"
                :key="imageId"
                :value="imageId"
              >
                {{ availableImages.find(img => img.id === imageId)?.title }}
              </option>
            </select>
            <p class="form-hint">
              Choose which selected image should be the cover.
            </p>
          </div>

          <div class="gallery-modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="loading">
              <span v-if="loading">Creating...</span>
              <span v-else>Create Gallery</span>
            </button>
          </div>
          </form>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import { useImageStore } from '../../stores/image';
import axios from 'axios';

console.log('[CreateGalleryModal] Component script loading...');

const emit = defineEmits(['close', 'gallery-created']);

const galleryStore = useGalleryStore();
const imageStore = useImageStore();

const formData = ref({
  title: '',
  description: '',
  cover_image_id: null,
  selectedImages: []
});

const loading = ref(false);
const error = ref(null);
const availableImages = ref([]);
const supabaseUrl = ref('');

console.log('[CreateGalleryModal] Component initialized');

onMounted(async () => {
  console.log('[CreateGalleryModal] Component mounted');
  await fetchSupabaseUrl();
  // Fetch images for selection
  try {
    console.log('[CreateGalleryModal] Fetching images...');
    await imageStore.fetchImages(1, {});
    availableImages.value = imageStore.images;
    console.log('[CreateGalleryModal] Images loaded:', availableImages.value.length);
  } catch (err) {
    console.error('[CreateGalleryModal] Failed to load images:', err);
  }
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
    console.error('[CreateGalleryModal] Failed to fetch Supabase URL:', error);
  }
};

const getImageUrl = (image) => {
  if (!image || !image.storage_url) {
    return 'https://placehold.co/150';
  }

  const storedUrl = image.storage_url;

  // If the URL starts with http or https, it's a complete URL
  if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
    return storedUrl;
  }

  // If no Supabase URL is available yet, use a placeholder
  if (!supabaseUrl.value) {
    return 'https://placehold.co/150';
  }

  // If it's a relative path, prepend the Supabase URL
  return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
};

const toggleImageSelection = (imageId) => {
  const index = formData.value.selectedImages.indexOf(imageId);
  if (index > -1) {
    // Remove from selection
    formData.value.selectedImages.splice(index, 1);
    // If this was the cover image, clear it
    if (formData.value.cover_image_id === imageId) {
      formData.value.cover_image_id = null;
    }
  } else {
    // Add to selection
    formData.value.selectedImages.push(imageId);
    // If this is the first image selected, make it the cover
    if (formData.value.selectedImages.length === 1) {
      formData.value.cover_image_id = imageId;
    }
  }
};

const handleSubmit = async () => {
  console.log('[CreateGalleryModal] handleSubmit called');
  if (!formData.value.title.trim()) {
    error.value = 'Title is required';
    console.log('[CreateGalleryModal] Validation failed: title is required');
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    console.log('[CreateGalleryModal] Creating gallery with data:', formData.value);
    const gallery = await galleryStore.createGallery({
      title: formData.value.title.trim(),
      description: formData.value.description.trim(),
      cover_image_id: formData.value.cover_image_id,
      image_ids: formData.value.selectedImages
    });

    console.log('[CreateGalleryModal] Gallery created successfully');
    emit('gallery-created');
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create gallery';
    console.error('[CreateGalleryModal] Error creating gallery:', err);
  } finally {
    loading.value = false;
  }
};

const closeModal = () => {
  console.log('[CreateGalleryModal] closeModal called');
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
  max-width: 500px;
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

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #333;
}

.required {
  color: #dc3545;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

textarea.form-control {
  resize: vertical;
  font-family: inherit;
}

.form-hint {
  font-size: 0.85rem;
  color: #666;
  margin-top: 5px;
  font-style: italic;
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

.images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
  max-height: 400px;
  overflow-y: auto;
  padding: 10px;
  background-color: #f8f9fa;
  border-radius: 8px;
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

.image-card.is-cover {
  border-color: #28a745;
  box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
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

.cover-badge {
  position: absolute;
  top: 5px;
  right: 5px;
  background-color: #28a745;
  color: white;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

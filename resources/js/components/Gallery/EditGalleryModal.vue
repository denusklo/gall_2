<template>
  <transition name="fade">
    <div class="gallery-modal-backdrop" @click="closeModal">
      <div class="gallery-modal" @click.stop>
        <div class="gallery-modal-header">
          <h2>Edit Gallery</h2>
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
            <label for="cover_image_id">Cover Image</label>
            <select
              id="cover_image_id"
              v-model="formData.cover_image_id"
              class="form-control"
            >
              <option :value="null">No cover image</option>
              <option
                v-for="image in availableImages"
                :key="image.id"
                :value="image.id"
              >
                {{ image.title }}
              </option>
            </select>
            <p class="form-hint">
              You can only select from images already in this gallery.
            </p>
          </div>

          <div class="gallery-modal-footer">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="loading">
              <span v-if="loading">Updating...</span>
              <span v-else>Update Gallery</span>
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


const props = defineProps({
  gallery: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close', 'gallery-updated']);

const galleryStore = useGalleryStore();

const formData = ref({
  title: props.gallery.title || '',
  description: props.gallery.description || '',
  cover_image_id: props.gallery.cover_image_id || null
});

const loading = ref(false);
const error = ref(null);
const availableImages = ref([]);


onMounted(async () => {
  // Get images from the current gallery
  if (props.gallery.images) {
    availableImages.value = props.gallery.images;
  }
});

const handleSubmit = async () => {
  if (!formData.value.title.trim()) {
    error.value = 'Title is required';
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    await galleryStore.updateGallery(props.gallery.id, {
      title: formData.value.title.trim(),
      description: formData.value.description.trim(),
      cover_image_id: formData.value.cover_image_id
    });

    emit('gallery-updated');
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to update gallery';
    console.error('[EditGalleryModal] Error updating gallery:', err);
  } finally {
    loading.value = false;
  }
};

const closeModal = () => {
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

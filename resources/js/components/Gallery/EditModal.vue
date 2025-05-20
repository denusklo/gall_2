<!-- resources/js/components/Gallery/EditModal.vue -->
<template>
    <div class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Image</h3>
                <button @click="$emit('close')" class="close-btn">&times;</button>
            </div>

            <div class="modal-body">
                <form @submit.prevent="updateGallery">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" v-model="formData.title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <textarea id="description" v-model="formData.description" class="form-control"
                            rows="3"></textarea>
                    </div>

                    <!-- resources/js/components/Gallery/EditModal.vue - Add to the form -->
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" v-model="formData.category_id" class="form-control" :disabled="loading">
                            <option value="">No Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="image-preview">
                        <img :src="gallery.blob_url" :alt="gallery.title">
                    </div>

                    <div class="modal-footer">
                        <button type="button" @click="$emit('close')" class="btn btn-secondary" :disabled="loading">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="loading">
                            {{ loading ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, onMounted, computed } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import { useCategoryStore } from '../../stores/category';

const props = defineProps({
    gallery: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'update-complete']);
const galleryStore = useGalleryStore();
const loading = ref(false);
const categoryStore = useCategoryStore();
const categories = computed(() => categoryStore.categories);

const formData = ref({
    title: '',
    description: '',
    category_id: ''
});

onMounted(() => {
    formData.value.title = props.gallery.title;
    formData.value.description = props.gallery.description || '';
    formData.value.category_id = props.gallery.category_id || '';

    if (categoryStore.categories.length === 0) {
        categoryStore.fetchCategories();
    }
});


const updateGallery = async () => {
  loading.value = true;
  
  try {
    await galleryStore.updateGallery(props.gallery.id, {
      title: formData.value.title,
      description: formData.value.description,
      category_id: formData.value.category_id
    });
    
    alert('Gallery updated successfully!');
    emit('update-complete');
    emit('close');
  } catch (error) {
    alert('Failed to update gallery. Please try again.');
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
/* Same styles as UploadModal.vue */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #ddd;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.image-preview {
    margin: 15px 0;
    text-align: center;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 4px;
}
</style>
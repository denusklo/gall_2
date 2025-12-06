<!-- resources/js/components/Gallery/UploadModal.vue -->
<template>
    <div class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload New Image</h3>
                <button @click="closeModal" class="close-btn" :disabled="isUploading">&times;</button>
            </div>

            <div class="modal-body">
                <div v-if="uploadError" class="alert alert-danger">
                    {{ uploadError }}
                </div>

                <form @submit.prevent="uploadFile" v-if="!isSuccess">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" v-model="title" class="form-control" required
                            :disabled="isUploading">
                    </div>

                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <textarea id="description" v-model="description" class="form-control" rows="3"
                            :disabled="isUploading"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="file">Select Image</label>
                        <input type="file" id="file" @change="handleFileChange" class="form-control" accept="image/*"
                            required :disabled="isUploading">
                        <small class="form-text text-muted">
                            Max file size: 50MB. Supported formats: JPG, PNG, GIF, WEBP
                        </small>
                    </div>

                    <!-- resources/js/components/Gallery/UploadModal.vue - Add to the form -->
                    <div class="form-group">
                        <label for="category">Category (optional)</label>
                        <select id="category" v-model="category" class="form-control" :disabled="isUploading">
                            <option value="">No Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div v-if="previewUrl" class="image-preview">
                        <img :src="previewUrl" alt="Preview">
                    </div>

                    <div v-if="uploadProgress > 0" class="progress">
                        <div class="progress-bar" :style="{ width: `${uploadProgress}%` }" role="progressbar"
                            :aria-valuenow="uploadProgress" aria-valuemin="0" aria-valuemax="100">
                            {{ uploadProgress }}%
                        </div>
                    </div>

                    <div class="form-group file-details" v-if="selectedFile">
                        <p>
                            <strong>File Name:</strong> {{ selectedFile.name }}<br>
                            <strong>Type:</strong> {{ selectedFile.type }}<br>
                            <strong>Size:</strong> {{ formatFileSize(selectedFile.size) }}
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" @click="closeModal" class="btn btn-secondary" :disabled="isUploading">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" :disabled="!selectedFile || isUploading">
                            <span v-if="isUploading">
                                Uploading... ({{ uploadProgress }}%)
                            </span>
                            <span v-else>Upload to Supabase</span>
                        </button>
                        <button type="button" @click="uploadToVercel" class="btn btn-vercel" :disabled="!selectedFile || isUploading">
                            <span v-if="isUploadingVercel">
                                Uploading to Vercel...
                            </span>
                            <span v-else>Upload to Vercel</span>
                        </button>
                    </div>
                </form>

                <div v-else class="success-message">
                    <div class="text-center">
                        <i class="fa fa-check-circle success-icon"></i>
                        <h4>Upload Successful!</h4>
                        <p>Your image has been uploaded successfully.</p>
                        <button @click="closeModal" class="btn btn-primary">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import { useCategoryStore } from '../../stores/category';

const emit = defineEmits(['close', 'upload-complete']);
const galleryStore = useGalleryStore();
const categoryStore = useCategoryStore();
const category = ref('');
const categories = computed(() => categoryStore.categories);

const title = ref('');
const description = ref('');
const selectedFile = ref(null);
const previewUrl = ref('');
const uploadError = ref('');
const isSuccess = ref(false);
const isUploadingVercel = ref(false);

const isUploading = computed(() => galleryStore.loading || isUploadingVercel.value);
const uploadProgress = computed(() => galleryStore.uploadProgress);

onMounted(() => {
  if (categoryStore.categories.length === 0) {
    categoryStore.fetchCategories();
  }
});

const handleFileChange = (event) => {
    const file = event.target.files[0];

    if (!file) {
        selectedFile.value = null;
        previewUrl.value = '';
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        uploadError.value = 'Invalid file type. Please select a JPG, PNG, GIF, or WEBP image.';
        selectedFile.value = null;
        previewUrl.value = '';
        return;
    }

    // Validate file size (50MB max)
    const maxSize = 50 * 1024 * 1024; // 50MB in bytes
    if (file.size > maxSize) {
        uploadError.value = 'File size exceeds 50MB limit. Please select a smaller file.';
        selectedFile.value = null;
        previewUrl.value = '';
        return;
    }

    // Clear previous error
    uploadError.value = '';
    selectedFile.value = file;

    // Create a preview
    const reader = new FileReader();
    reader.onload = (e) => {
        previewUrl.value = e.target.result;
    };
    reader.readAsDataURL(file);

    // Auto-generate title from filename if title is empty
    if (!title.value) {
        // Remove file extension and replace dashes/underscores with spaces
        title.value = file.name
            .replace(/\.[^/.]+$/, "") // Remove extension
            .replace(/[-_]/g, " ") // Replace dashes and underscores with spaces
            .replace(/\b\w/g, c => c.toUpperCase()); // Capitalize first letter of each word
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const uploadFile = async () => {
  if (!selectedFile.value) {
    uploadError.value = 'Please select a file to upload.';
    return;
  }
  
  uploadError.value = '';
  
  try {
    // Prepare data for the gallery including the category if selected
    const galleryData = {
      file: selectedFile.value,
      title: title.value,
      description: description.value,
      category_id: category.value || null
    };
    
    await galleryStore.uploadFile(
      selectedFile.value, 
      title.value, 
      description.value, 
      category.value
    );
    
    // Show success message
    isSuccess.value = true;
    
    // Notify parent that upload is complete
    emit('upload-complete');
    
    // Reset form after a delay (if not closing)
    setTimeout(() => {
      title.value = '';
      description.value = '';
      category.value = '';
      selectedFile.value = null;
      previewUrl.value = '';
    }, 300);
  } catch (error) {
    uploadError.value = error.message || 'Failed to upload file. Please try again.';
  }
};

const uploadToVercel = async () => {
  if (!selectedFile.value) {
    uploadError.value = 'Please select a file to upload.';
    return;
  }
  
  uploadError.value = '';
  isUploadingVercel.value = true;
  
  try {
    // Use the Vercel upload method from the store
    await galleryStore.uploadFileToVercel(
      selectedFile.value, 
      title.value, 
      description.value, 
      category.value
    );
    
    // Show success message
    isSuccess.value = true;
    
    // Notify parent that upload is complete
    emit('upload-complete');
    
    // Reset form after a delay
    setTimeout(() => {
      title.value = '';
      description.value = '';
      category.value = '';
      selectedFile.value = null;
      previewUrl.value = '';
    }, 300);
  } catch (error) {
    uploadError.value = error.message || 'Failed to upload to Vercel. Please try again.';
  } finally {
    isUploadingVercel.value = false;
  }
};

const closeModal = () => {
    if (isUploading.value || isUploadingVercel.value) {
        if (!confirm('Upload in progress. Are you sure you want to cancel?')) {
            return;
        }
    }

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
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    background-color: #f9f9f9;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 4px;
}

.progress {
    height: 20px;
    margin: 15px 0;
    background-color: #f5f5f5;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    line-height: 20px;
    color: white;
    text-align: center;
    background-color: #007bff;
    transition: width 0.3s ease;
}

.alert {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.success-message {
    text-align: center;
    padding: 30px 0;
}

.success-icon {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 15px;
}

.file-details {
    background-color: #f9f9f9;
    padding: 10px;
    border-radius: 4px;
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

.btn-primary:hover {
    background-color: #0069d9;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.btn-vercel {
    background-color: #000000;
    color: white;
}

.btn-vercel:hover {
    background-color: #333333;
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>
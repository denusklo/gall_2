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
                        <div class="file-input-wrapper">
                            <input type="file" id="file" @change="handleFileChange" class="file-input-hidden" accept="image/*"
                                required :disabled="isUploading">
                            <label for="file" class="file-input-button" :class="{ 'disabled': isUploading }">
                                <i class="fas fa-image"></i>
                                <span>{{ selectedFile ? selectedFile.name : 'Choose Image File' }}</span>
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Max file size: 50MB. Supported formats: JPG, PNG, GIF, WEBP
                        </small>
                    </div>

                    <!-- resources/js/components/Gallery/UploadModal.vue - Add to the form -->
                    <div class="form-group">
                        <label for="categories">Categories (optional)</label>
                        <select id="categories" v-model="categoryIds" class="form-control" :disabled="isUploading" multiple size="5">
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple categories</small>
                    </div>

                    <div class="form-group">
                        <label for="credential">Storage credential</label>
                        <select id="credential" v-model="selectedCredentialId" class="form-control"
                            :disabled="isUploading || credentials.length === 0">
                            <option v-for="cred in credentials" :key="cred.id" :value="cred.id">
                                {{ cred.name }} ({{ cred.provider === 'vercel' ? 'Vercel' : 'Supabase' }}){{ cred.is_default ? ' ★ default' : '' }}
                            </option>
                        </select>
                        <small v-if="credentials.length === 0" class="form-text text-danger">
                            No storage credentials yet. Add one in Storage Settings before uploading.
                        </small>
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
                        <button type="submit" class="btn btn-primary" :disabled="!selectedFile || isUploading || !selectedCredential">
                            <span v-if="isUploading">
                                Uploading... ({{ uploadProgress }}%)
                            </span>
                            <span v-else>Upload</span>
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
import { useImageStore } from '../../stores/image';
import { useCategoryStore } from '../../stores/category';
import { useStorageCredentialsStore } from '../../stores/storageCredentials';

const emit = defineEmits(['close', 'upload-complete']);
const imageStore = useImageStore();
const categoryStore = useCategoryStore();
const credentialsStore = useStorageCredentialsStore();
const categoryIds = ref([]);
const categories = computed(() => categoryStore.categories);

// Storage credential selection (provider is derived from the chosen credential)
const credentials = computed(() => credentialsStore.credentials);
const selectedCredentialId = ref(null);
const selectedCredential = computed(
  () => credentials.value.find(c => c.id === selectedCredentialId.value) || null
);

const title = ref('');
const description = ref('');
const selectedFile = ref(null);
const previewUrl = ref('');
const uploadError = ref('');
const isSuccess = ref(false);
const isUploadingRef = ref(false);

const isUploading = computed(() => imageStore.loading || isUploadingRef.value);
const uploadProgress = computed(() => imageStore.uploadProgress);

onMounted(async () => {
  if (categoryStore.categories.length === 0) {
    categoryStore.fetchCategories();
  }
  await credentialsStore.fetchCredentials();
  const def = credentials.value.find(c => c.is_default) || credentials.value[0];
  selectedCredentialId.value = def ? def.id : null;
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
  if (!selectedCredential.value) {
    uploadError.value = 'Please select a storage credential.';
    return;
  }

  const credential = selectedCredential.value;
  uploadError.value = '';
  isUploadingRef.value = true;

  try {
    // Route by the chosen credential's provider, passing its id
    if (credential.provider === 'vercel') {
      await imageStore.uploadFileToVercel(
        selectedFile.value, title.value, description.value, categoryIds.value, credential.id
      );
    } else {
      await imageStore.uploadFile(
        selectedFile.value, title.value, description.value, categoryIds.value, credential.id
      );
    }

    // Show success message
    isSuccess.value = true;

    // Notify parent that upload is complete
    emit('upload-complete');

    // Reset form after a delay (if not closing)
    setTimeout(() => {
      title.value = '';
      description.value = '';
      categoryIds.value = [];
      selectedFile.value = null;
      previewUrl.value = '';
    }, 300);
  } catch (error) {
    uploadError.value = error.message || 'Failed to upload file. Please try again.';
  } finally {
    isUploadingRef.value = false;
  }
};

const closeModal = () => {
    if (isUploading.value) {
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

.file-input-wrapper {
    position: relative;
}

.file-input-hidden {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.file-input-button {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    width: 100%;
    justify-content: center;
}

.file-input-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.file-input-button.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.file-input-button i {
    font-size: 1.2rem;
}

.file-input-button span {
    flex: 1;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
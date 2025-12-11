<!-- resources/js/components/Gallery/BulkUploadModal.vue -->
<template>
    <div class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Upload Images</h3>
                <button @click="closeModal" class="close-btn" :disabled="isUploading">&times;</button>
            </div>

            <div class="modal-body">
                <div v-if="uploadError" class="alert alert-danger">
                    {{ uploadError }}
                </div>

                <form v-if="!isComplete">
                    <!-- resources/js/components/Gallery/BulkUploadModal.vue - Add before the file selection form group -->
                    <div class="form-group">
                        <label for="bulk-categories">Default Categories (optional)</label>
                        <select id="bulk-categories" v-model="defaultCategoryIds" class="form-control"
                            :disabled="isUploading" multiple size="5">
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            Hold Ctrl (Cmd on Mac) to select multiple categories. These will be applied to all uploaded images.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="files">Select Multiple Images</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="files" @change="handleFilesChange" class="file-input-hidden" accept="image/*"
                                required multiple :disabled="isUploading">
                            <label for="files" class="file-input-button" :class="{ 'disabled': isUploading }">
                                <i class="fas fa-images"></i>
                                <span>{{ selectedFiles.length > 0 ? `${selectedFiles.length} file(s) selected` : 'Choose Multiple Images' }}</span>
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Max 10 files at once. Supported formats: JPG, PNG, GIF, WEBP
                        </small>
                    </div>

                    <div v-if="selectedFiles.length > 0" class="selected-files">
                        <div class="selected-files-header">
                            <h4>{{ selectedFiles.length }} files selected</h4>
                            <button type="button" @click="clearFiles" class="btn btn-sm btn-outline-secondary"
                                :disabled="isUploading">
                                Clear All
                            </button>
                        </div>

                        <div class="file-list">
                            <div v-for="(file, index) in selectedFiles" :key="index" class="file-item">
                                <div class="file-preview">
                                    <img :src="filePreviewUrls[index]" :alt="file.name">
                                </div>
                                <div class="file-info">
                                    <div class="file-name">{{ file.name }}</div>
                                    <div class="file-size">{{ formatFileSize(file.size) }}</div>
                                    <input v-model="fileTitles[index]" placeholder="Title (optional)"
                                        class="file-title-input" :disabled="isUploading">
                                </div>
                                <div class="file-actions" v-if="!isUploading">
                                    <button type="button" @click="removeFile(index)" class="btn-remove">&times;</button>
                                </div>
                                <div class="file-progress" v-if="isUploading">
                                    <div class="progress-bar" :style="{ width: `${fileProgress[index] || 0}%` }"
                                        :class="{ 'progress-complete': fileProgress[index] === 100 }"></div>
                                    <div class="progress-status">
                                        {{ fileStatuses[index] || 'Waiting...' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="overall-progress" v-if="isUploading">
                        <div class="progress">
                            <div class="progress-bar" :style="{ width: `${overallProgress}%` }">
                                {{ overallProgress }}%
                            </div>
                        </div>
                        <div class="upload-status">
                            Uploading {{ uploadedCount }} of {{ selectedFiles.length }}
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" @click="closeModal" class="btn btn-secondary" :disabled="isUploading">
                            Cancel
                        </button>
                        <button type="button" @click="startUploadSupabase" class="btn btn-primary"
                            :disabled="selectedFiles.length === 0 || isUploading">
                            <span v-if="isUploadingSupabase">
                                Uploading to Supabase... ({{ uploadedCount }}/{{ selectedFiles.length }})
                            </span>
                            <span v-else>Upload to Supabase</span>
                        </button>
                        <button type="button" @click="startUploadVercel" class="btn btn-vercel"
                            :disabled="selectedFiles.length === 0 || isUploading">
                            <span v-if="isUploadingVercel">
                                Uploading to Vercel... ({{ uploadedCount }}/{{ selectedFiles.length }})
                            </span>
                            <span v-else>Upload to Vercel</span>
                        </button>
                    </div>
                </form>

                <div v-else class="success-message">
                    <div class="text-center">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h4>Bulk Upload Complete!</h4>
                        <p>Successfully uploaded {{ uploadedCount }} of {{ totalFiles }} files.</p>
                        <p v-if="failedCount > 0" class="text-danger">
                            {{ failedCount }} files failed to upload.
                        </p>
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

const emit = defineEmits(['close', 'upload-complete']);
const imageStore = useImageStore();
const categoryStore = useCategoryStore();
const categories = computed(() => categoryStore.categories);
const defaultCategoryIds = ref([]);

// File selection state
const selectedFiles = ref([]);
const filePreviewUrls = ref([]);
const fileTitles = ref([]);

// Upload state
const isUploadingSupabase = ref(false);
const isUploadingVercel = ref(false);
const uploadError = ref('');
const fileProgress = ref([]);
const fileStatuses = ref([]);
const uploadedCount = ref(0);
const failedCount = ref(0);
const totalFiles = ref(0);
const isComplete = ref(false);

const isUploading = computed(() => isUploadingSupabase.value || isUploadingVercel.value);

const MAX_FILES = 10;

onMounted(() => {
    if (categoryStore.categories.length === 0) {
        categoryStore.fetchCategories();
    }
});

const overallProgress = computed(() => {
    if (selectedFiles.value.length === 0) return 0;

    const totalProgress = fileProgress.value.reduce((sum, progress) => sum + (progress || 0), 0);
    return Math.round(totalProgress / selectedFiles.value.length);
});

const handleFilesChange = (event) => {
    const newFiles = Array.from(event.target.files);

    // Limit to MAX_FILES
    if (newFiles.length > MAX_FILES) {
        uploadError.value = `You can only upload up to ${MAX_FILES} files at once.`;
        return;
    }

    // Validate file types
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const invalidFiles = newFiles.filter(file => !allowedTypes.includes(file.type));

    if (invalidFiles.length > 0) {
        uploadError.value = 'Some files have invalid types. Please select only JPG, PNG, GIF, or WEBP images.';
        return;
    }

    // Clear previous error
    uploadError.value = '';

    // Generate previews and auto-titles
    selectedFiles.value = newFiles;
    filePreviewUrls.value = [];
    fileTitles.value = [];

    newFiles.forEach(file => {
        // Generate preview
        const reader = new FileReader();
        reader.onload = (e) => {
            filePreviewUrls.value.push(e.target.result);
        };
        reader.readAsDataURL(file);

        // Generate title from filename
        const title = file.name
            .replace(/\.[^/.]+$/, "") // Remove extension
            .replace(/[-_]/g, " ") // Replace dashes and underscores with spaces
            .replace(/\b\w/g, c => c.toUpperCase()); // Capitalize first letter of each word

        fileTitles.value.push(title);
    });

    // Initialize progress and status arrays
    fileProgress.value = Array(newFiles.length).fill(0);
    fileStatuses.value = Array(newFiles.length).fill('Waiting...');
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
    filePreviewUrls.value.splice(index, 1);
    fileTitles.value.splice(index, 1);
};

const clearFiles = () => {
    selectedFiles.value = [];
    filePreviewUrls.value = [];
    fileTitles.value = [];
    uploadError.value = '';
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const startUploadSupabase = async () => {
    if (selectedFiles.value.length === 0) {
        uploadError.value = 'Please select files to upload.';
        return;
    }

    isUploadingSupabase.value = true;
    uploadError.value = '';
    uploadedCount.value = 0;
    failedCount.value = 0;
    totalFiles.value = selectedFiles.value.length;

    // Process files sequentially to avoid overwhelming the server
    for (let i = 0; i < selectedFiles.value.length; i++) {
        const file = selectedFiles.value[i];
        const title = fileTitles.value[i] || file.name;

        fileStatuses.value[i] = 'Uploading...';

        try {
            // Use the imageStore's uploadFile method (Supabase)
            await uploadFileSupabase(file, title, i);

            fileProgress.value[i] = 100;
            fileStatuses.value[i] = 'Complete';
            uploadedCount.value++;
        } catch (error) {
            console.error(`Error uploading file ${i}:`, error);
            fileProgress.value[i] = 0;
            fileStatuses.value[i] = 'Failed';
            failedCount.value++;
        }
    }

    isUploadingSupabase.value = false;
    isComplete.value = true;
    emit('upload-complete');
};

const startUploadVercel = async () => {
    if (selectedFiles.value.length === 0) {
        uploadError.value = 'Please select files to upload.';
        return;
    }

    isUploadingVercel.value = true;
    uploadError.value = '';
    uploadedCount.value = 0;
    failedCount.value = 0;
    totalFiles.value = selectedFiles.value.length;

    // Process files sequentially to avoid overwhelming the server
    for (let i = 0; i < selectedFiles.value.length; i++) {
        const file = selectedFiles.value[i];
        const title = fileTitles.value[i] || file.name;

        fileStatuses.value[i] = 'Uploading...';

        try {
            // Use the imageStore's uploadFileToVercel method
            await uploadFileVercel(file, title, i);

            fileProgress.value[i] = 100;
            fileStatuses.value[i] = 'Complete';
            uploadedCount.value++;
        } catch (error) {
            console.error(`Error uploading file ${i}:`, error);
            fileProgress.value[i] = 0;
            fileStatuses.value[i] = 'Failed';
            failedCount.value++;
        }
    }

    isUploadingVercel.value = false;
    isComplete.value = true;
    emit('upload-complete');
};

const uploadFileSupabase = async (file, title, index) => {
    return new Promise(async (resolve, reject) => {
        try {
            fileStatuses.value[index] = 'Uploading to Supabase...';

            // Create FormData similar to single upload
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', title);
            formData.append('description', '');

            if (defaultCategoryIds.value && defaultCategoryIds.value.length > 0) {
                defaultCategoryIds.value.forEach((id, idx) => {
                    formData.append(`category_ids[${idx}]`, id);
                });
            }

            // Upload using axios to track progress
            const response = await axios.post('/apiv/_1/images/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                onUploadProgress: (progressEvent) => {
                    const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    fileProgress.value[index] = progress;
                    fileStatuses.value[index] = `Uploading... ${progress}%`;
                }
            });

            fileProgress.value[index] = 100;
            fileStatuses.value[index] = 'Complete';
            resolve(response.data);
        } catch (error) {
            fileProgress.value[index] = 0;
            fileStatuses.value[index] = 'Failed';
            reject(error);
        }
    });
};

const uploadFileVercel = async (file, title, index) => {
    return new Promise(async (resolve, reject) => {
        try {
            // Step 1: Get client token
            fileStatuses.value[index] = 'Requesting token...';
            fileProgress.value[index] = 10;

            const tokenResponse = await axios.post('/apiv/_1/vercel/generate-client-token', {
                filename: file.name,
                content_type: file.type,
                size: file.size,
                title: title,
                description: '',
                category_ids: defaultCategoryIds.value
            });

            const { clientToken, pathname, metadata } = tokenResponse.data;

            // Step 2: Upload to Vercel Blob
            const uploadUrl = `https://vercel.com/api/blob/${pathname}`;
            fileStatuses.value[index] = 'Uploading to Vercel...';
            fileProgress.value[index] = 20;

            // Create clean axios instance for Vercel
            const vercelAxios = axios.create();
            vercelAxios.defaults.headers.common = {};

            const uploadResponse = await vercelAxios.put(uploadUrl, file, {
                headers: {
                    'Authorization': `Bearer ${clientToken}`,
                    'Content-Type': file.type,
                },
                onUploadProgress: (progressEvent) => {
                    // Map progress from 20% to 80%
                    const uploadProgress = Math.round((progressEvent.loaded * 60) / progressEvent.total);
                    fileProgress.value[index] = 20 + uploadProgress;
                    fileStatuses.value[index] = `Uploading... ${fileProgress.value[index]}%`;
                }
            });

            // Step 3: Save to database
            fileStatuses.value[index] = 'Saving...';
            fileProgress.value[index] = 90;

            const callbackResponse = await axios.post('/apiv/_1/vercel/upload-callback', {
                blob: {
                    url: uploadResponse.data.url,
                    pathname: uploadResponse.data.pathname || pathname,
                    size: uploadResponse.data.size || file.size,
                    contentType: uploadResponse.data.contentType || file.type,
                    downloadUrl: uploadResponse.data.downloadUrl
                },
                metadata: metadata
            });

            fileProgress.value[index] = 100;
            fileStatuses.value[index] = 'Complete';
            resolve(callbackResponse.data.image);
        } catch (error) {
            fileProgress.value[index] = 0;
            fileStatuses.value[index] = 'Failed';
            reject(error);
        }
    });
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
    max-width: 700px;
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

.selected-files {
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 15px 0;
}

.selected-files-header {
    padding: 10px 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.selected-files-header h4 {
    margin: 0;
    font-size: 1rem;
}

.file-list {
    max-height: 400px;
    overflow-y: auto;
}

.file-item {
    display: flex;
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    position: relative;
}

.file-item:last-child {
    border-bottom: none;
}

.file-preview {
    width: 60px;
    height: 60px;
    overflow: hidden;
    border-radius: 4px;
    margin-right: 15px;
    flex-shrink: 0;
}

.file-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-info {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-weight: 500;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size {
    color: #666;
    font-size: 0.85rem;
    margin-bottom: 5px;
}

.file-title-input {
    width: 100%;
    padding: 5px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.file-actions {
    margin-left: 10px;
    display: flex;
    align-items: center;
}

.btn-remove {
    background: none;
    border: none;
    color: #dc3545;
    font-size: 1.2rem;
    cursor: pointer;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.file-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 24px;
    background-color: #f5f5f5;
}

.progress-bar {
    height: 100%;
    background-color: #007bff;
    transition: width 0.2s;
}

.progress-bar.progress-complete {
    background-color: #28a745;
}

.progress-status {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
}

.overall-progress {
    margin: 15px 0;
}

.progress {
    height: 20px;
    background-color: #f5f5f5;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.upload-status {
    text-align: center;
    font-size: 0.9rem;
    color: #666;
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

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
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

.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.btn-outline-secondary {
    color: #6c757d;
    border: 1px solid #6c757d;
    background-color: transparent;
}

.btn-outline-secondary:hover {
    background-color: #6c757d;
    color: white;
}

.text-danger {
    color: #dc3545;
}

.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>
<!-- resources/js/components/Gallery/GalleryDetail.vue -->
<template>
    <div class="modal-backdrop" @click.self="$emit('close')">
        <div class="modal-content" @click.stop>
            <div class="modal-header">
                <h3>{{ gallery.title }}</h3>
                <button @click="$emit('close')" class="close-btn">&times;</button>
            </div>

            <div class="modal-body">
                <div class="image-container">
                    <img :src="gallery.blob_url" :alt="gallery.title" class="full-image" />
                </div>

                <div class="image-details">
                    <p v-if="gallery.description" class="description">
                        {{ gallery.description }}
                    </p>

                    <div class="metadata">
                        <p><strong>Uploaded:</strong> {{ formatDate(gallery.created_at) }}</p>
                        <p><strong>File Type:</strong> {{ gallery.mime_type }}</p>
                        <p><strong>Size:</strong> {{ formatFileSize(gallery.size) }}</p>
                        <p><strong>Filename:</strong> {{ gallery.filename }}</p>
                        <p v-if="gallery.category">
                            <strong>Category:</strong> {{ gallery.category.name }}
                        </p>
                    </div>

                    <div class="actions">
                        <a :href="gallery.blob_url" target="_blank" class="btn btn-primary">View Full Size</a>
                        <a :href="gallery.blob_url" download class="btn btn-secondary">Download</a>
                        <button @click="editGallery" class="btn btn-info">Edit Details</button>
                        <button @click="deleteGallery" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    gallery: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'edit', 'delete']);

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString();
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const editGallery = () => {
    emit('edit', props.gallery);
    emit('close');
};

const deleteGallery = () => {
    if (confirm('Are you sure you want to delete this image?')) {
        emit('delete', props.gallery.id);
        emit('close');
    }
};
</script>

<style scoped>
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

@media (min-width: 768px) {
    .modal-body {
        flex-direction: row;
        gap: 20px;
    }
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}

.image-container {
    flex: 2;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
}

@media (min-width: 768px) {
    .image-container {
        margin-bottom: 0;
    }
}

.full-image {
    max-width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 4px;
}

.image-details {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.description {
    margin-bottom: 20px;
    line-height: 1.5;
}

.metadata {
    margin-bottom: 20px;
}

.metadata p {
    margin: 5px 0;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: auto;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    font-weight: 500;
    flex: 1;
    min-width: calc(50% - 5px);
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn:hover {
    opacity: 0.9;
}
</style>
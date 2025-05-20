<!-- resources/js/components/Gallery/GalleryItem.vue -->
<template>
    <div class="gallery-item" @click="showDetail = true">
        <div class="gallery-image">
            <img :src="gallery.blob_url" :alt="gallery.title" loading="lazy" />
        </div>
        <div class="gallery-info">
            <h3>{{ gallery.title }}</h3>
            <p v-if="gallery.description && gallery.description.length <= 50" class="description">
                {{ gallery.description }}
            </p>
            <p v-else-if="gallery.description" class="description">
                {{ gallery.description.substring(0, 50) }}...
            </p>
            <!-- resources/js/components/Gallery/GalleryItem.vue - Add to the gallery-info section -->
            <p v-if="gallery.category" class="gallery-category">
                <span class="category-badge">{{ gallery.category.name }}</span>
            </p>
            <p class="gallery-meta">
                <small>{{ formatFileSize(gallery.size) }} | {{ formatDate(gallery.created_at) }}</small>
            </p>
            <div class="gallery-actions" @click.stop>
                <button @click="$emit('edit', gallery)" class="btn btn-sm btn-outline-primary">
                    Edit
                </button>
                <button @click="confirmDelete" class="btn btn-sm btn-outline-danger">
                    Delete
                </button>
            </div>
        </div>

        <gallery-detail v-if="showDetail" :gallery="gallery" @close="showDetail = false" @edit="$emit('edit', gallery)"
            @delete="$emit('delete', $event)" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { defineProps, defineEmits } from 'vue';
import GalleryDetail from './GalleryDetail.vue';

const props = defineProps({
    gallery: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['edit', 'delete']);
const showDetail = ref(false);

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const confirmDelete = () => {
    if (confirm('Are you sure you want to delete this image?')) {
        emit('delete', props.gallery.id);
    }
};
</script>

<style scoped>
.gallery-item {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
    cursor: pointer;
    background-color: white;
}

.gallery-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.gallery-image {
    height: 200px;
    overflow: hidden;
    position: relative;
}

.gallery-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.gallery-item:hover .gallery-image img {
    transform: scale(1.05);
}

.gallery-info {
    padding: 15px;
}

.gallery-info h3 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 1.1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.description {
    color: #555;
    font-size: 0.9rem;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
}

.gallery-meta {
    color: #666;
    margin-bottom: 10px;
    font-size: 0.8rem;
}

.gallery-actions {
    display: flex;
    justify-content: space-between;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
    border-radius: 4px;
    cursor: pointer;
}

.btn-outline-primary {
    color: #007bff;
    border: 1px solid #007bff;
    background-color: transparent;
}

.btn-outline-primary:hover {
    background-color: #007bff;
    color: white;
}

.btn-outline-danger {
    color: #dc3545;
    border: 1px solid #dc3545;
    background-color: transparent;
}

.btn-outline-danger:hover {
    background-color: #dc3545;
    color: white;
}
.gallery-category {
  margin-top: 5px;
  margin-bottom: 5px;
}

.category-badge {
  display: inline-block;
  background-color: #e9ecef;
  color: #495057;
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 12px;
}
</style>
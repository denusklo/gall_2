<template>
    <div class="gallery-detail-container">
        <div v-if="galleryStore.loading && !galleryStore.currentGallery" class="loading">
            <div class="spinner"></div>
            <p>Loading gallery...</p>
        </div>

        <div v-else-if="galleryStore.error" class="error">
            <p>{{ galleryStore.error }}</p>
            <button @click="loadGallery" class="btn btn-primary">Try Again</button>
        </div>

        <div v-else-if="galleryStore.currentGallery">
            <!-- Gallery Header -->
            <div class="gallery-header">
                <div class="header-content">
                    <button @click="goBack" class="btn btn-secondary back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Galleries
                    </button>
                    <h1>{{ galleryStore.currentGallery.title }}</h1>
                    <p v-if="galleryStore.currentGallery.description" class="gallery-description">
                        {{ galleryStore.currentGallery.description }}
                    </p>
                    <div class="gallery-meta">
                        <span class="image-count">
                            <i class="fas fa-image"></i>
                            {{ galleryStore.currentGallery.images?.length || 0 }}
                            {{ (galleryStore.currentGallery.images?.length || 0) === 1 ? 'image' : 'images' }}
                        </span>
                        <span class="created-date">
                            Created {{ formatDate(galleryStore.currentGallery.created_at) }}
                        </span>
                    </div>
                    <div class="gallery-actions">
                        <button @click="showEditModal = true" class="btn btn-info">
                            <i class="fas fa-edit"></i> Edit Gallery
                        </button>
                        <button @click="showAddImagesModal = true" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Images
                        </button>
                    </div>
                </div>
            </div>

            <!-- Images Grid -->
            <div v-if="galleryStore.currentGallery.images && galleryStore.currentGallery.images.length > 0" class="images-container">
                <div v-if="reordering" class="reordering-message">
                    <div class="spinner-small"></div>
                    <span>Saving new order...</span>
                </div>
                <div ref="imagesGrid" class="images-grid">
                    <div
                        v-for="image in galleryStore.currentGallery.images"
                        :key="image.id"
                        :data-id="image.id"
                        class="image-card"
                    >
                        <div class="drag-handle" title="Drag to reorder">
                            <i class="fas fa-grip-vertical"></i>
                        </div>
                        <div class="image-wrapper">
                            <img
                                :src="getImageUrl(image)"
                                :alt="image.title"
                                class="gallery-image"
                                @click="viewImage(image)"
                                loading="lazy"
                            />
                            <div v-if="galleryStore.currentGallery.cover_image_id === image.id" class="cover-badge">
                                <i class="fas fa-star"></i> Cover
                            </div>
                        </div>
                        <div class="image-info">
                            <h3 class="image-title">{{ image.title }}</h3>
                            <p v-if="image.description" class="image-description">
                                {{ image.description }}
                            </p>
                            <div class="image-actions">
                                <button
                                    v-if="galleryStore.currentGallery.cover_image_id !== image.id"
                                    @click="setCoverImage(image.id)"
                                    class="btn btn-sm btn-warning"
                                    title="Set as cover image"
                                >
                                    <i class="fas fa-star"></i> Set as Cover
                                </button>
                                <button
                                    @click="confirmRemoveImage(image)"
                                    class="btn btn-sm btn-danger"
                                    title="Remove from gallery"
                                >
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="empty-gallery">
                <i class="fas fa-images empty-icon"></i>
                <p>This gallery is empty.</p>
                <p class="hint">Add images to this gallery from the Images page.</p>
            </div>
        </div>

        <edit-gallery-modal
            v-if="showEditModal"
            :gallery="galleryStore.currentGallery"
            @close="showEditModal = false"
            @gallery-updated="onGalleryUpdated"
        />

        <add-images-to-gallery-modal
            v-if="showAddImagesModal"
            :gallery-id="props.galleryId"
            :existing-image-ids="galleryStore.currentGallery?.images?.map(img => img.id) || []"
            @close="showAddImagesModal = false"
            @images-added="onImagesAdded"
        />

        <confirm-dialog
            :show="showRemoveConfirm"
            title="Remove Image"
            :message="`Are you sure you want to remove '${imageToRemove?.title}' from this gallery?`"
            confirm-text="Remove"
            type="danger"
            @confirm="removeImage"
            @cancel="cancelRemove"
        />
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, defineProps, ref, computed, nextTick } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import EditGalleryModal from './EditGalleryModal.vue';
import AddImagesToGalleryModal from './AddImagesToGalleryModal.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import axios from 'axios';
import Sortable from 'sortablejs';

console.log('[GalleryView] Component script loading...');

const props = defineProps({
    galleryId: {
        type: [String, Number],
        required: true
    }
});

const galleryStore = useGalleryStore();
const showEditModal = ref(false);
const showAddImagesModal = ref(false);
const showRemoveConfirm = ref(false);
const imageToRemove = ref(null);
const supabaseUrl = ref('');
const imagesGrid = ref(null);
const sortableInstance = ref(null);
const reordering = ref(false);

onMounted(async () => {
    console.log('[GalleryView] Component mounted, galleryId:', props.galleryId);
    await fetchSupabaseUrl();
    await loadGallery();
    await nextTick();
    initializeSortable();
});

onUnmounted(() => {
    if (sortableInstance.value) {
        sortableInstance.value.destroy();
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
        console.error('[GalleryView] Failed to fetch Supabase URL:', error);
    }
};

const loadGallery = async () => {
    try {
        console.log('[GalleryView] Loading gallery:', props.galleryId);
        await galleryStore.fetchGallery(props.galleryId);
        console.log('[GalleryView] Gallery loaded:', galleryStore.currentGallery);
    } catch (error) {
        console.error('[GalleryView] Error loading gallery:', error);
    }
};

const getImageUrl = (image) => {
    if (!image || !image.storage_url) {
        return 'https://placehold.co/400';
    }

    const storedUrl = image.storage_url;

    // If the URL starts with http or https, it's a complete URL (Vercel or full Supabase URL)
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

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const goBack = () => {
    window.location.href = '/galleries/albums';
};

const viewImage = (image) => {
    console.log('View image:', image);
    const fullUrl = getImageUrl(image);
    window.open(fullUrl, '_blank');
};

const onGalleryUpdated = async () => {
    console.log('[GalleryView] Gallery updated, reloading...');
    showEditModal.value = false;
    await loadGallery();
};

const onImagesAdded = async () => {
    console.log('[GalleryView] Images added, reloading...');
    showAddImagesModal.value = false;
    await loadGallery();
    // Re-initialize sortable after reload
    await nextTick();
    if (sortableInstance.value) {
        sortableInstance.value.destroy();
    }
    initializeSortable();
};

const setCoverImage = async (imageId) => {
    console.log('[GalleryView] Setting cover image:', imageId);
    try {
        await galleryStore.setCoverImage(props.galleryId, imageId);
        console.log('[GalleryView] Cover image set successfully');
        await loadGallery(); // Reload to show updated cover badge
    } catch (error) {
        console.error('[GalleryView] Error setting cover image:', error);
        alert('Failed to set cover image. Please try again.');
    }
};

const confirmRemoveImage = (image) => {
    console.log('[GalleryView] Confirm remove image:', image.title);
    imageToRemove.value = image;
    showRemoveConfirm.value = true;
};

const removeImage = async () => {
    if (imageToRemove.value) {
        console.log('[GalleryView] Removing image from gallery:', imageToRemove.value.id);
        try {
            await galleryStore.removeImageFromGallery(props.galleryId, imageToRemove.value.id);
            console.log('[GalleryView] Image removed successfully');
            await loadGallery(); // Reload gallery
            showRemoveConfirm.value = false;
            imageToRemove.value = null;
        } catch (error) {
            console.error('[GalleryView] Error removing image:', error);
            alert('Failed to remove image. Please try again.');
        }
    }
};

const cancelRemove = () => {
    console.log('[GalleryView] Remove cancelled');
    showRemoveConfirm.value = false;
    imageToRemove.value = null;
};

const initializeSortable = () => {
    if (!imagesGrid.value) {
        console.warn('[GalleryView] Images grid not found, cannot initialize Sortable');
        return;
    }

    console.log('[GalleryView] Initializing Sortable on images grid');

    sortableInstance.value = new Sortable(imagesGrid.value, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        chosenClass: 'sortable-chosen',
        forceFallback: true,
        fallbackTolerance: 3,
        onEnd: handleReorder
    });
};

const handleReorder = async (event) => {
    console.log('[GalleryView] Reorder event:', event);

    // Get the new order of image IDs
    const imageElements = imagesGrid.value.querySelectorAll('.image-card');
    const newOrder = Array.from(imageElements).map(el => parseInt(el.dataset.id));

    console.log('[GalleryView] New order:', newOrder);

    // Save the new order
    reordering.value = true;
    try {
        await galleryStore.reorderImages(props.galleryId, newOrder);
        console.log('[GalleryView] Images reordered successfully');
        // Reload to get updated data
        await loadGallery();
        // Re-initialize sortable after reload
        await nextTick();
        if (sortableInstance.value) {
            sortableInstance.value.destroy();
        }
        initializeSortable();
    } catch (error) {
        console.error('[GalleryView] Error reordering images:', error);
        alert('Failed to save new order. Please try again.');
        // Reload to restore original order
        await loadGallery();
        await nextTick();
        if (sortableInstance.value) {
            sortableInstance.value.destroy();
        }
        initializeSortable();
    } finally {
        reordering.value = false;
    }
};
</script>

<style scoped>
.gallery-detail-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.loading,
.error {
    text-align: center;
    padding: 40px 0;
    background-color: #f9f9f9;
    border-radius: 8px;
    border: 1px solid #ddd;
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

.gallery-header {
    margin-bottom: 30px;
}

.header-content {
    position: relative;
}

.back-btn {
    margin-bottom: 15px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

h1 {
    font-size: 2.5rem;
    color: #333;
    margin-bottom: 10px;
}

.gallery-description {
    font-size: 1.1rem;
    color: #666;
    margin-bottom: 15px;
    line-height: 1.6;
}

.gallery-meta {
    display: flex;
    gap: 20px;
    font-size: 0.9rem;
    color: #777;
}

.image-count,
.created-date {
    display: flex;
    align-items: center;
    gap: 5px;
}

.gallery-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

.image-card {
    position: relative;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.3s ease, transform 0.2s ease;
}

.image-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.drag-handle {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 10;
    background: rgba(255, 255, 255, 0.95);
    padding: 8px 10px;
    border-radius: 4px;
    cursor: grab;
    color: #666;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: all 0.2s ease;
    opacity: 0.7;
}

.drag-handle:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 1);
    color: #333;
    transform: scale(1.1);
}

.drag-handle:active {
    cursor: grabbing;
}

.sortable-ghost {
    opacity: 0.4;
    background: #f0f0f0;
}

.sortable-drag {
    opacity: 0.8;
    transform: rotate(2deg);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
}

.sortable-chosen {
    cursor: grabbing;
}

.reordering-message {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 123, 255, 0.95);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.spinner-small {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 0.8s linear infinite;
}

.image-wrapper {
    position: relative;
    width: 100%;
    height: 250px;
    overflow: hidden;
    background-color: #f5f5f5;
}

.gallery-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.3s ease;
}

.image-card:hover .gallery-image {
    transform: scale(1.05);
}

.cover-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #ffc107;
    color: #333;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.image-info {
    padding: 15px;
}

.image-title {
    margin: 0 0 8px 0;
    font-size: 1rem;
    color: #333;
    font-weight: 600;
}

.image-description {
    margin: 0 0 10px 0;
    font-size: 0.85rem;
    color: #666;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.image-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
}

.empty-gallery {
    text-align: center;
    padding: 60px 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    border: 2px dashed #ddd;
}

.empty-icon {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 20px;
}

.empty-gallery p {
    font-size: 1.1rem;
    color: #666;
    margin: 10px 0;
}

.hint {
    font-size: 0.9rem;
    color: #999;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0069d9;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875rem;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background-color: #e0a800;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-success:hover {
    background-color: #218838;
}

@media (max-width: 768px) {
    h1 {
        font-size: 2rem;
    }

    .images-grid {
        grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
    }

    .gallery-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

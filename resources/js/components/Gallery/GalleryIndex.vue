<template>
    <div class="gallery-container">
        <h1>My Images</h1>

        <div class="controls">
            <button @click="showUploadModal = true" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Image
            </button>
            <button @click="showBulkUploadModal = true" class="btn btn-secondary">
                <i class="fas fa-cloud-upload-alt"></i> Bulk Upload
            </button>
        </div>

        <gallery-filters ref="filtersRef" @update-filters="applyFilters" />

        <gallery-stats v-if="!imageStore.loading && !imageStore.error && hasImages"
            :stats="imageStore.stats" />

        <div class="main-content">
            <aside class="sidebar">
                <category-selector @category-changed="onCategoryChanged" />
            </aside>

            <main class="content">
                <div v-if="imageStore.loading && !hasImages" class="loading">
                    <div class="spinner"></div>
                    <p>Loading images...</p>
                </div>

                <div v-else-if="imageStore.error" class="error">
                    <p>{{ imageStore.error }}</p>
                    <button @click="refreshGallery" class="btn btn-primary">Try Again</button>
                </div>

                <div v-else-if="!hasImages" class="empty">
                    <div v-if="hasActiveFilters || currentFilters.categoryId">
                        <p>No images match your current filters or category selection.</p>
                        <button @click="clearFilters" class="btn btn-primary">Clear Filters</button>
                    </div>
                    <div v-else>
                        <p>You don't have any images yet. Upload your first image!</p>
                        <button @click="showUploadModal = true" class="btn btn-primary">
                            Upload Now
                        </button>
                    </div>
                </div>

                <div v-else>
                    <div class="gallery-grid">
                        <gallery-item v-for="image in imageStore.images" :key="image.id" :gallery="image"
                            @edit="editImage" @delete="deleteImage" @add-to-gallery="addToGallery" />
                    </div>

                    <gallery-pagination :current-page="imageStore.pagination.currentPage"
                        :total-items="imageStore.pagination.totalItems" :per-page="imageStore.pagination.perPage"
                        @page-change="changePage" />
                </div>
            </main>
        </div>

        <upload-modal v-if="showUploadModal" @close="showUploadModal = false" @upload-complete="refreshGallery" />

        <bulk-upload-modal v-if="showBulkUploadModal" @close="showBulkUploadModal = false"
            @upload-complete="refreshGallery" />

        <edit-modal v-if="imageToEdit" :gallery="imageToEdit" @close="imageToEdit = null"
            @update-complete="refreshGallery" />

        <add-to-gallery-modal
            v-if="imageToAddToGallery"
            :image="imageToAddToGallery"
            @close="imageToAddToGallery = null"
            @image-added="onImageAddedToGallery"
        />
    </div>
    <!-- resources/js/components/Gallery/GalleryIndex.vue - Add at the end before the closing div -->
    <loading-overlay :show="imageStore.loading && !hasImages" message="Loading images..." />
    <!-- resources/js/components/Gallery/GalleryIndex.vue - Add before the closing div -->
    <confirm-dialog :show="showDeleteConfirm" title="Delete Image"
        message="Are you sure you want to delete this image? This action cannot be undone." confirm-text="Delete"
        type="danger" @confirm="confirmDelete" @cancel="cancelDelete" />
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { useImageStore } from '../../stores/image';
import { useCategoryStore } from '../../stores/category';
import GalleryItem from './GalleryItem.vue';
import UploadModal from './UploadModal.vue';
import EditModal from './EditModal.vue';
import GalleryPagination from './GalleryPagination.vue';
import GalleryFilters from './GalleryFilters.vue';
import GalleryStats from './GalleryStats.vue';
import GalleryDashboard from './GalleryDashboard.vue';
import BulkUploadModal from './BulkUploadModal.vue';
import CategorySelector from './CategorySelector.vue';
import LoadingOverlay from './LoadingOverlay.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import AddToGalleryModal from './AddToGalleryModal.vue';

const imageStore = useImageStore();
const categoryStore = useCategoryStore();
const showUploadModal = ref(false);
const imageToEdit = ref(null);
const currentFilters = ref({});
const filtersRef = ref(null);
const showBulkUploadModal = ref(false);
const showDeleteConfirm = ref(false);
const imageToDelete = ref(null);
const imageToAddToGallery = ref(null);

const hasImages = computed(() => imageStore.images.length > 0);
const hasActiveFilters = computed(() => {
    return currentFilters.value.search ||
        currentFilters.value.fileType ||
        (currentFilters.value.sortBy && currentFilters.value.sortBy !== 'newest');
});

onMounted(() => {
    // Initial fetch without filters
    imageStore.fetchImages();
    imageStore.fetchStats();
});

const editImage = (image) => {
    imageToEdit.value = image;
};

const deleteImage = async (id) => {
    if (confirm('Are you sure you want to delete this image?')) {
        await imageStore.deleteImage(id);

        // If we deleted the last item on the current page, go to previous page
        if (imageStore.images.length === 0 && imageStore.pagination.currentPage > 1) {
            changePage(imageStore.pagination.currentPage - 1);
        } else {
            refreshGallery();
        }
    }
};

const changePage = (page) => {
    imageStore.fetchImages(page, currentFilters.value);
    // Scroll to top of gallery
    window.scrollTo({
        top: document.querySelector('.gallery-container').offsetTop,
        behavior: 'smooth'
    });
};

const applyFilters = (filters) => {
    currentFilters.value = { ...filters };
    imageStore.fetchImages(1, currentFilters.value); // Reset to first page when filters change
};

const clearFilters = () => {
    if (filtersRef.value) {
        filtersRef.value.clearAllFilters();
    }
};

const refreshGallery = () => {
    imageStore.fetchImages(
        imageStore.pagination.currentPage,
        currentFilters.value
    );
    imageStore.fetchStats();
    categoryStore.fetchCategories();
};

const onCategoryChanged = (categoryId) => {
    currentFilters.value.categoryId = categoryId;
    imageStore.fetchImages(1, currentFilters.value);
};

const confirmDelete = async () => {
    if (imageToDelete.value) {
        await imageStore.deleteImage(imageToDelete.value);

        // If we deleted the last item on the current page, go to previous page
        if (imageStore.images.length === 0 && imageStore.pagination.currentPage > 1) {
            changePage(imageStore.pagination.currentPage - 1);
        } else {
            refreshGallery();
        }

        showDeleteConfirm.value = false;
        imageToDelete.value = null;
    }
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
    imageToDelete.value = null;
};

const addToGallery = (image) => {
    console.log('[GalleryIndex] Opening add to gallery modal for image:', image.title);
    imageToAddToGallery.value = image;
};

const onImageAddedToGallery = () => {
    console.log('[GalleryIndex] Image added to gallery successfully');
    imageToAddToGallery.value = null;
    // Refresh images to update the galleries relationship
    // This will recalculate the isInAllGalleries computed property
    imageStore.fetchImages(
        imageStore.pagination.currentPage,
        currentFilters.value
    );
};

</script>

<style scoped>
.gallery-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

h1 {
    margin-bottom: 20px;
    font-size: 2rem;
    color: #333;
}

.controls {
    margin-bottom: 20px;
    display: flex;
    justify-content: flex-end;
    column-gap: 0.5rem;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.loading,
.error,
.empty {
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

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0069d9;
}

@media (max-width: 576px) {
    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
    }

    .controls {
        justify-content: center;
    }
}

/* resources/js/components/Gallery/GalleryIndex.vue - Add these styles */
.main-content {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.sidebar {
    width: 280px;
    flex-shrink: 0;
}

.content {
    flex: 1;
    min-width: 0;
    /* Prevents flex items from overflowing */
}

@media (max-width: 768px) {
    .main-content {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        margin-bottom: 20px;
    }
}
</style>
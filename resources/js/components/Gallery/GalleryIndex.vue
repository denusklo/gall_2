<template>
    <div class="gallery-container">
        <h1>My Gallery</h1>

        <div class="controls">
            <button @click="showUploadModal = true" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Image
            </button>
            <button @click="showBulkUploadModal = true" class="btn btn-secondary">
                <i class="fas fa-cloud-upload-alt"></i> Bulk Upload
            </button>
        </div>

        <gallery-filters ref="filtersRef" @update-filters="applyFilters" />

        <gallery-stats v-if="!galleryStore.loading && !galleryStore.error && hasGalleries"
            :stats="galleryStore.stats" />

        <div class="main-content">
            <aside class="sidebar">
                <category-selector @category-changed="onCategoryChanged" />
            </aside>

            <main class="content">
                <div v-if="galleryStore.loading && !hasGalleries" class="loading">
                    <div class="spinner"></div>
                    <p>Loading galleries...</p>
                </div>

                <div v-else-if="galleryStore.error" class="error">
                    <p>{{ galleryStore.error }}</p>
                    <button @click="refreshGallery" class="btn btn-primary">Try Again</button>
                </div>

                <div v-else-if="!hasGalleries" class="empty">
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
                        <gallery-item v-for="gallery in galleryStore.galleries" :key="gallery.id" :gallery="gallery"
                            @edit="editGallery" @delete="deleteGallery" />
                    </div>

                    <gallery-pagination :current-page="galleryStore.pagination.currentPage"
                        :total-items="galleryStore.pagination.totalItems" :per-page="galleryStore.pagination.perPage"
                        @page-change="changePage" />
                </div>
            </main>
        </div>

        <upload-modal v-if="showUploadModal" @close="showUploadModal = false" @upload-complete="refreshGallery" />

        <bulk-upload-modal v-if="showBulkUploadModal" @close="showBulkUploadModal = false"
            @upload-complete="refreshGallery" />

        <edit-modal v-if="galleryToEdit" :gallery="galleryToEdit" @close="galleryToEdit = null"
            @update-complete="refreshGallery" />
    </div>
    <!-- resources/js/components/Gallery/GalleryIndex.vue - Add at the end before the closing div -->
    <loading-overlay :show="galleryStore.loading && !hasGalleries" message="Loading galleries..." />
    <!-- resources/js/components/Gallery/GalleryIndex.vue - Add before the closing div -->
<confirm-dialog
  :show="showDeleteConfirm"
  title="Delete Image"
  message="Are you sure you want to delete this image? This action cannot be undone."
  confirm-text="Delete"
  type="danger"
  @confirm="confirmDelete"
  @cancel="cancelDelete"
/>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
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

const galleryStore = useGalleryStore();
const showUploadModal = ref(false);
const galleryToEdit = ref(null);
const currentFilters = ref({});
const filtersRef = ref(null);
const showBulkUploadModal = ref(false);
const showDeleteConfirm = ref(false);
const galleryToDelete = ref(null);

const hasGalleries = computed(() => galleryStore.galleries.length > 0);
const hasActiveFilters = computed(() => {
    return currentFilters.value.search ||
        currentFilters.value.fileType ||
        (currentFilters.value.sortBy && currentFilters.value.sortBy !== 'newest');
});

onMounted(() => {
    // Initial fetch without filters
    galleryStore.fetchGalleries();
    galleryStore.fetchGalleries();
    galleryStore.fetchStats();
});

const editGallery = (gallery) => {
    galleryToEdit.value = gallery;
};

const deleteGallery = async (id) => {
    if (confirm('Are you sure you want to delete this image?')) {
        await galleryStore.deleteGallery(id);

        // If we deleted the last item on the current page, go to previous page
        if (galleryStore.galleries.length === 0 && galleryStore.pagination.currentPage > 1) {
            changePage(galleryStore.pagination.currentPage - 1);
        } else {
            refreshGallery();
        }
    }
};

const changePage = (page) => {
    galleryStore.fetchGalleries(page, currentFilters.value);
    // Scroll to top of gallery
    window.scrollTo({
        top: document.querySelector('.gallery-container').offsetTop,
        behavior: 'smooth'
    });
};

const applyFilters = (filters) => {
    currentFilters.value = { ...filters };
    galleryStore.fetchGalleries(1, currentFilters.value); // Reset to first page when filters change
};

const clearFilters = () => {
    if (filtersRef.value) {
        filtersRef.value.clearAllFilters();
    }
};

const refreshGallery = () => {
    galleryStore.fetchGalleries(
        galleryStore.pagination.currentPage,
        currentFilters.value
    );
    galleryStore.fetchStats();
};

const onCategoryChanged = (categoryId) => {
    currentFilters.value.categoryId = categoryId;
    galleryStore.fetchGalleries(1, currentFilters.value);
};

const confirmDelete = async () => {
  if (galleryToDelete.value) {
    await galleryStore.deleteGallery(galleryToDelete.value);
    
    // If we deleted the last item on the current page, go to previous page
    if (galleryStore.galleries.length === 0 && galleryStore.pagination.currentPage > 1) {
      changePage(galleryStore.pagination.currentPage - 1);
    } else {
      refreshGallery();
    }
    
    showDeleteConfirm.value = false;
    galleryToDelete.value = null;
  }
};

const cancelDelete = () => {
  showDeleteConfirm.value = false;
  galleryToDelete.value = null;
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
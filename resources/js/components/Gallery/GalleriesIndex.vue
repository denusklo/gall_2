<template>
    <div class="gallery-container">
        <h1>My Galleries</h1>

        <div class="controls">
            <div class="search-box">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search galleries..."
                    @input="onSearchInput"
                    class="search-input"
                />
            </div>
            <button @click="showCreateModal = true" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Gallery
            </button>
        </div>

        <div class="main-content">
            <div v-if="galleryStore.loading && !hasGalleries" class="loading">
                <div class="spinner"></div>
                <p>Loading galleries...</p>
            </div>

            <div v-else-if="galleryStore.error" class="error">
                <p>{{ galleryStore.error }}</p>
                <button @click="refreshGalleries" class="btn btn-primary">Try Again</button>
            </div>

            <div v-else-if="!hasGalleries" class="empty">
                <div v-if="searchQuery">
                    <p>No galleries match your search.</p>
                    <button @click="clearSearch" class="btn btn-primary">Clear Search</button>
                </div>
                <div v-else>
                    <p>You don't have any galleries yet. Create your first gallery!</p>
                    <button @click="showCreateModal = true" class="btn btn-primary">
                        Create Gallery
                    </button>
                </div>
            </div>

            <div v-else>
                <div class="gallery-grid">
                    <div
                        v-for="gallery in galleryStore.galleries"
                        :key="gallery.id"
                        class="gallery-card"
                    >
                        <div class="gallery-cover">
                            <img
                                v-if="getCoverImageUrl(gallery)"
                                :src="getCoverImageUrl(gallery)"
                                :alt="gallery.title"
                                class="cover-image"
                                loading="lazy"
                            />
                            <div v-else class="cover-placeholder">
                                <i class="fas fa-images"></i>
                            </div>
                        </div>
                        <div class="gallery-info">
                            <h3 class="gallery-title">{{ gallery.title }}</h3>
                            <p class="gallery-meta">
                                <span class="image-count">
                                    <i class="fas fa-image"></i>
                                    {{ gallery.images_count || 0 }} {{ gallery.images_count === 1 ? 'photo' : 'photos' }}
                                </span>
                                <span class="created-date">
                                    {{ formatDate(gallery.created_at) }}
                                </span>
                            </p>
                            <p v-if="gallery.description" class="gallery-description">
                                {{ gallery.description }}
                            </p>
                        </div>
                        <div class="gallery-actions">
                            <button @click="viewGallery(gallery.id)" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button @click="editGallery(gallery)" class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button @click="confirmDeleteGallery(gallery)" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="galleryStore.pagination.totalItems > galleryStore.pagination.perPage" class="pagination">
                    <button
                        @click="changePage(galleryStore.pagination.currentPage - 1)"
                        :disabled="galleryStore.pagination.currentPage === 1"
                        class="btn btn-secondary"
                    >
                        Previous
                    </button>
                    <span class="page-info">
                        Page {{ galleryStore.pagination.currentPage }} of {{ totalPages }}
                    </span>
                    <button
                        @click="changePage(galleryStore.pagination.currentPage + 1)"
                        :disabled="galleryStore.pagination.currentPage >= totalPages"
                        class="btn btn-secondary"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <create-gallery-modal
            v-if="showCreateModal"
            @close="showCreateModal = false"
            @gallery-created="onGalleryCreated"
        />

        <edit-gallery-modal
            v-if="galleryToEdit"
            :gallery="galleryToEdit"
            @close="galleryToEdit = null"
            @gallery-updated="onGalleryUpdated"
        />

        <confirm-dialog
            :show="showDeleteConfirm"
            title="Delete Gallery"
            :message="`Are you sure you want to delete '${galleryToDelete?.title}'? This action cannot be undone.`"
            confirm-text="Delete"
            type="danger"
            @confirm="deleteGallery"
            @cancel="cancelDelete"
        />
    </div>
</template>

<script setup>
import { onMounted, ref, computed, watch } from 'vue';
import { useGalleryStore } from '../../stores/gallery';
import CreateGalleryModal from './CreateGalleryModal.vue';
import EditGalleryModal from './EditGalleryModal.vue';
import ConfirmDialog from './ConfirmDialog.vue';
import axios from 'axios';

console.log('[GalleriesIndex] Component script loading...');

const galleryStore = useGalleryStore();
const showCreateModal = ref(false);
const showDeleteConfirm = ref(false);
const galleryToDelete = ref(null);
const galleryToEdit = ref(null);
const searchQuery = ref('');
const supabaseUrl = ref('');
let searchTimeout = null;

// Watch for showCreateModal changes
watch(showCreateModal, (newValue) => {
  console.log('[GalleriesIndex] showCreateModal changed to:', newValue);
});

const hasGalleries = computed(() => galleryStore.galleries.length > 0);
const totalPages = computed(() =>
    Math.ceil(galleryStore.pagination.totalItems / galleryStore.pagination.perPage)
);

onMounted(async () => {
    await fetchSupabaseUrl();
    galleryStore.fetchGalleries();
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
        console.error('[GalleriesIndex] Failed to fetch Supabase URL:', error);
    }
};

const getCoverImageUrl = (gallery) => {
    if (!gallery.cover_image?.storage_url) {
        return null;
    }

    const storedUrl = gallery.cover_image.storage_url;

    // If the URL starts with http or https, it's a complete URL
    if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
        return storedUrl;
    }

    // If no Supabase URL is available yet, return null to show placeholder
    if (!supabaseUrl.value) {
        return null;
    }

    // If it's a relative path, prepend the Supabase URL
    return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const viewGallery = (id) => {
    window.location.href = `/galleries/${id}`;
};

const editGallery = async (gallery) => {
    console.log('[GalleriesIndex] editGallery called for:', gallery.id);
    // Fetch full gallery details including images for cover image selection
    try {
        const fullGallery = await galleryStore.fetchGallery(gallery.id);
        galleryToEdit.value = fullGallery;
    } catch (error) {
        console.error('[GalleriesIndex] Error fetching gallery for edit:', error);
        galleryToEdit.value = gallery; // Fallback to basic gallery data
    }
};

const onGalleryUpdated = () => {
    console.log('[GalleriesIndex] Gallery updated! Closing modal and refreshing...');
    galleryToEdit.value = null;
    refreshGalleries();
};

const confirmDeleteGallery = (gallery) => {
    galleryToDelete.value = gallery;
    showDeleteConfirm.value = true;
};

const deleteGallery = async () => {
    if (galleryToDelete.value) {
        await galleryStore.deleteGallery(galleryToDelete.value.id);

        // If we deleted the last item on the current page, go to previous page
        if (galleryStore.galleries.length === 0 && galleryStore.pagination.currentPage > 1) {
            changePage(galleryStore.pagination.currentPage - 1);
        } else {
            refreshGalleries();
        }

        showDeleteConfirm.value = false;
        galleryToDelete.value = null;
    }
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
    galleryToDelete.value = null;
};

const changePage = (page) => {
    const filters = searchQuery.value ? { search: searchQuery.value } : {};
    galleryStore.fetchGalleries(page, filters);
    window.scrollTo({
        top: document.querySelector('.gallery-container').offsetTop,
        behavior: 'smooth'
    });
};

const onSearchInput = () => {
    // Debounce search
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const filters = searchQuery.value ? { search: searchQuery.value } : {};
        galleryStore.fetchGalleries(1, filters);
    }, 300);
};

const clearSearch = () => {
    searchQuery.value = '';
    galleryStore.fetchGalleries(1, {});
};

const refreshGalleries = () => {
    const filters = searchQuery.value ? { search: searchQuery.value } : {};
    galleryStore.fetchGalleries(
        galleryStore.pagination.currentPage,
        filters
    );
};

const onGalleryCreated = () => {
    console.log('[GalleriesIndex] Gallery created! Closing modal and refreshing...');
    showCreateModal.value = false;
    console.log('[GalleriesIndex] Current galleries count:', galleryStore.galleries.length);
    console.log('[GalleriesIndex] Current galleries:', galleryStore.galleries);
    // No need to refresh since the store already added the new gallery
    // refreshGalleries();
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
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.search-box {
    flex: 1;
    max-width: 400px;
}

.search-input {
    width: 100%;
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.search-input:focus {
    outline: none;
    border-color: #007bff;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.gallery-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.gallery-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.gallery-cover {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background-color: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cover-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cover-placeholder {
    font-size: 3rem;
    color: #ccc;
}

.gallery-info {
    padding: 15px;
}

.gallery-title {
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    color: #333;
    font-weight: 600;
}

.gallery-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 8px;
}

.image-count {
    display: flex;
    align-items: center;
    gap: 5px;
}

.gallery-description {
    font-size: 0.9rem;
    color: #666;
    margin: 0;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.gallery-actions {
    padding: 15px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
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

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    padding: 20px 0;
}

.page-info {
    font-size: 0.9rem;
    color: #666;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875rem;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
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

.btn-secondary:hover:not(:disabled) {
    background-color: #5a6268;
}

.btn-secondary:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

@media (max-width: 768px) {
    .controls {
        flex-direction: column;
        align-items: stretch;
    }

    .search-box {
        max-width: 100%;
    }

    .gallery-grid {
        grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
    }
}
</style>

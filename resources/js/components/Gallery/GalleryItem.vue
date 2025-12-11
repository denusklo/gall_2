<!-- Replace the entire template section in GalleryItem.vue -->
<template>
    <div class="gallery-item">
        <!-- Add a div that handles the click event with stopPropagation -->
        <div class="gallery-item-inner" @click.stop="handleItemClick">
            <div class="gallery-image">
                <img 
                    :src="imageUrl" 
                    :alt="gallery.title" 
                    loading="lazy" 
                    @error="handleImageError"
                    onerror="this.onerror=null; this.src='https://placehold.co/400';"
                />
            </div>
            <div class="gallery-info">
                <h3>{{ gallery.title }}</h3>
                <p v-if="gallery.description && gallery.description.length <= 50" class="description">
                    {{ gallery.description }}
                </p>
                <p v-else-if="gallery.description" class="description">
                    {{ gallery.description.substring(0, 50) }}...
                </p>
                <p v-if="gallery.categories && gallery.categories.length > 0" class="gallery-category">
                    <span v-for="category in gallery.categories" :key="category.id" class="category-badge">
                        {{ category.name }}
                    </span>
                </p>
                <p class="gallery-meta">
                    <small>{{ formatFileSize(gallery.size) }} | {{ formatDate(gallery.created_at) }}</small>
                </p>
                <div class="gallery-actions" @click.stop>
                    <button
                        @click.stop="$emit('add-to-gallery', gallery)"
                        class="btn btn-sm btn-outline-success"
                        :disabled="isInAllGalleries"
                        :title="isInAllGalleries ? 'Image is already in all galleries' : 'Add to Gallery'"
                    >
                        <i class="fas fa-folder-plus"></i> Add to Gallery
                    </button>
                    <button @click.stop="$emit('edit', gallery)" class="btn btn-sm btn-outline-primary">
                        Edit
                    </button>
                    <button @click.stop="confirmDelete" class="btn btn-sm btn-outline-danger">
                        Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Use Teleport to render the modal outside the gallery-item DOM -->
        <teleport to="body" v-if="showDetail">
            <gallery-detail 
                :gallery="gallery" 
                @close="closeDetail" 
                @edit="$emit('edit', gallery)"
                @delete="$emit('delete', $event)" 
            />
        </teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { defineProps, defineEmits } from 'vue';
import GalleryDetail from './GalleryDetail.vue';
import { useGalleryStore } from '../../stores/gallery';
import axios from 'axios'; // Make sure axios is imported

const props = defineProps({
    gallery: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['edit', 'delete', 'add-to-gallery']);
const galleryStore = useGalleryStore();
const showDetail = ref(false);
const refreshedUrl = ref(null);
const urlError = ref(false);
const supabaseUrl = ref(''); // Store the URL in a ref

const fetchSupabaseUrl = async () => {
    // First check localStorage
    const cachedUrl = localStorage.getItem('supabaseUrl');
    if (cachedUrl) {
        supabaseUrl.value = cachedUrl;
        return;
    }
    
    try {
        const response = await axios.get('/apiv/_1/config/supabase-url');
        supabaseUrl.value = response.data.url;
        
        // Save to localStorage for future use
        localStorage.setItem('supabaseUrl', response.data.url);
    } catch (error) {
        console.error('Failed to fetch Supabase URL:', error);
    }
};

onMounted(() => {
    fetchSupabaseUrl();
    // Load galleries if not already loaded
    if (galleryStore.galleries.length === 0) {
        galleryStore.fetchGalleries();
    }
});

// Check if image is in all available galleries
const isInAllGalleries = computed(() => {
    // If no galleries exist, button should be disabled
    if (galleryStore.galleries.length === 0) {
        return true;
    }

    // If image has no galleries relationship, can't determine - enable button
    if (!props.gallery.galleries || !Array.isArray(props.gallery.galleries)) {
        return false;
    }

    // Check if image is in all galleries
    const imageGalleryIds = props.gallery.galleries.map(g => g.id);
    const allGalleryIds = galleryStore.galleries.map(g => g.id);

    // Image is in all galleries if every gallery ID is in the image's gallery list
    return allGalleryIds.every(id => imageGalleryIds.includes(id));
});

const imageUrl = computed(() => {
    // If we have a refreshed URL from a recent API call, use that
    if (refreshedUrl.value) {
        return refreshedUrl.value;
    }
    
    // Otherwise, check if the stored URL needs the Supabase base URL
    const storedUrl = props.gallery.storage_url || '';
    
    // If the URL starts with http or https, assume it's a complete URL
    if (storedUrl.startsWith('http://') || storedUrl.startsWith('https://')) {
        return storedUrl;
    }
    
    // If no Supabase URL is available yet (still loading), use a placeholder
    if (!supabaseUrl.value) {
        return 'https://placehold.co/400';
    }
    
    // If not, prepend the Supabase URL
    return `${supabaseUrl.value}/storage/v1${storedUrl.startsWith('/') ? '' : '/'}${storedUrl}`;
});

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

// Image error handling
const handleImageError = async () => {
    if (!urlError.value) {
        urlError.value = true;
        await refreshImageUrl();
    }
};

const refreshImageUrl = async () => {
    try {
        const response = await axios.get(`/apiv/_1/galleries/${props.gallery.id}/signed-url`);
        
        // Check the actual structure of the response
        if (response.data && response.data.signedUrl) {
            refreshedUrl.value = response.data.signedUrl;
        } else {
            console.error('Signed URL not found in response:', response.data);
        }
    } catch (error) {
        console.error('Failed to refresh image URL:', error);
    }
};

// New methods to handle the modal properly
const handleItemClick = () => {
    // Using nextTick ensures the DOM updates are complete before opening the modal
    showDetail.value = true;
};

const closeDetail = () => {
    showDetail.value = false;
};
</script>

<style scoped>
.gallery-item {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
    background-color: white;
}

/* Make the inner content the clickable part */
.gallery-item-inner {
    cursor: pointer;
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

.btn-outline-success {
    color: #28a745;
    border: 1px solid #28a745;
    background-color: transparent;
}

.btn-outline-success:hover:not(:disabled) {
    background-color: #28a745;
    color: white;
}

.btn-outline-success:disabled {
    color: #6c757d;
    border-color: #6c757d;
    background-color: #e9ecef;
    cursor: not-allowed;
    opacity: 0.65;
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
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
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
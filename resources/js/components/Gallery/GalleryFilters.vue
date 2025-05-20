<!-- resources/js/components/Gallery/GalleryFilters.vue -->
<template>
  <div class="gallery-filters">
    <div class="filter-container">
      <input 
        type="text" 
        v-model="filters.search" 
        @input="updateFilters"
        placeholder="Search by title or description" 
        class="search-input"
      />
      
      <div class="sort-container">
        <label for="sort-select">Sort by:</label>
        <select 
          id="sort-select" 
          v-model="filters.sortBy" 
          @change="updateFilters" 
          class="sort-select"
        >
          <option value="newest">Newest First</option>
          <option value="oldest">Oldest First</option>
          <option value="title_asc">Title (A-Z)</option>
          <option value="title_desc">Title (Z-A)</option>
          <option value="size_asc">Size (Smallest)</option>
          <option value="size_desc">Size (Largest)</option>
        </select>
      </div>
      
      <div class="filter-by-type">
        <label for="type-select">File Type:</label>
        <select 
          id="type-select" 
          v-model="filters.fileType" 
          @change="updateFilters" 
          class="type-select"
        >
          <option value="">All Types</option>
          <option value="image/jpeg">JPEG</option>
          <option value="image/png">PNG</option>
          <option value="image/gif">GIF</option>
          <option value="image/webp">WebP</option>
        </select>
      </div>
    </div>
    
    <div class="active-filters" v-if="hasActiveFilters">
      <div class="filter-tags">
        <div v-if="filters.search" class="filter-tag">
          Search: {{ filters.search }}
          <button @click="clearSearch" class="clear-tag">&times;</button>
        </div>
        
        <div v-if="filters.fileType" class="filter-tag">
          Type: {{ formatFileType(filters.fileType) }}
          <button @click="clearFileType" class="clear-tag">&times;</button>
        </div>
        
        <div v-if="filters.sortBy !== 'newest'" class="filter-tag">
          Sort: {{ formatSortBy(filters.sortBy) }}
          <button @click="clearSort" class="clear-tag">&times;</button>
        </div>
      </div>
      
      <button @click="clearAllFilters" class="clear-all-btn">
        Clear All Filters
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, defineEmits } from 'vue';

const emit = defineEmits(['update-filters']);

const defaultFilters = {
  search: '',
  sortBy: 'newest',
  fileType: ''
};

const filters = ref({ ...defaultFilters });

const hasActiveFilters = computed(() => {
  return filters.value.search !== '' || 
         filters.value.sortBy !== 'newest' || 
         filters.value.fileType !== '';
});

const formatFileType = (mimeType) => {
  const types = {
    'image/jpeg': 'JPEG',
    'image/png': 'PNG',
    'image/gif': 'GIF',
    'image/webp': 'WebP'
  };
  
  return types[mimeType] || mimeType;
};

const formatSortBy = (sortBy) => {
  const formats = {
    'newest': 'Newest First',
    'oldest': 'Oldest First',
    'title_asc': 'Title (A-Z)',
    'title_desc': 'Title (Z-A)',
    'size_asc': 'Size (Smallest)',
    'size_desc': 'Size (Largest)'
  };
  
  return formats[sortBy] || sortBy;
};

const updateFilters = () => {
  emit('update-filters', { ...filters.value });
};

const clearSearch = () => {
  filters.value.search = '';
  updateFilters();
};

const clearFileType = () => {
  filters.value.fileType = '';
  updateFilters();
};

const clearSort = () => {
  filters.value.sortBy = 'newest';
  updateFilters();
};

const clearAllFilters = () => {
  filters.value = { ...defaultFilters };
  updateFilters();
};

// Initialize with any provided filters
const initFilters = (initialFilters) => {
  if (initialFilters) {
    filters.value = { 
      ...defaultFilters,
      ...initialFilters
    };
  }
};

// Expose initFilters to parent component
defineExpose({ initFilters });
</script>

<style scoped>
.gallery-filters {
  margin-bottom: 20px;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 15px;
  background-color: #f9f9f9;
}

.filter-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 15px;
}

.search-input, .sort-select, .type-select {
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 0.9rem;
}

.search-input {
  flex: 2;
  min-width: 200px;
}

.sort-container, .filter-by-type {
  display: flex;
  align-items: center;
  gap: 5px;
}

.sort-select, .type-select {
  min-width: 120px;
  cursor: pointer;
}

.active-filters {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.filter-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-tag {
  background-color: #e9ecef;
  border-radius: 30px;
  padding: 5px 12px;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 5px;
}

.clear-tag {
  background: none;
  border: none;
  font-size: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  padding: 0;
  line-height: 1;
  opacity: 0.7;
}

.clear-tag:hover {
  opacity: 1;
}

.clear-all-btn {
  background-color: transparent;
  border: 1px solid #dc3545;
  color: #dc3545;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 0.85rem;
  cursor: pointer;
}

.clear-all-btn:hover {
  background-color: #dc3545;
  color: white;
}

@media (max-width: 768px) {
  .filter-container {
    flex-direction: column;
  }
  
  .search-input, .sort-select, .type-select {
    width: 100%;
  }
  
  .sort-container, .filter-by-type {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .active-filters {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
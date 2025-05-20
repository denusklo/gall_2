<!-- resources/js/components/Gallery/GalleryPagination.vue -->
<template>
  <div class="pagination-container" v-if="totalPages > 1">
    <button 
      @click="onPageChange(currentPage - 1)" 
      class="pagination-btn"
      :disabled="currentPage === 1"
    >
      Previous
    </button>
    
    <div class="page-numbers">
      <button 
        v-for="page in displayedPages" 
        :key="page"
        @click="onPageChange(page)"
        class="page-number"
        :class="{ active: currentPage === page }"
      >
        {{ page }}
      </button>
    </div>
    
    <button 
      @click="onPageChange(currentPage + 1)" 
      class="pagination-btn"
      :disabled="currentPage === totalPages"
    >
      Next
    </button>
  </div>
</template>

<script setup>
import { computed, defineProps, defineEmits } from 'vue';

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  totalItems: {
    type: Number,
    required: true
  },
  perPage: {
    type: Number,
    default: 12
  }
});

const emit = defineEmits(['page-change']);

const totalPages = computed(() => {
  return Math.ceil(props.totalItems / props.perPage);
});

const displayedPages = computed(() => {
  let pages = [];
  const maxVisiblePages = 5;
  
  if (totalPages.value <= maxVisiblePages) {
    // If there are 5 or fewer pages, show all
    for (let i = 1; i <= totalPages.value; i++) {
      pages.push(i);
    }
  } else {
    // Always show first page
    pages.push(1);
    
    let startPage = Math.max(2, props.currentPage - 1);
    let endPage = Math.min(totalPages.value - 1, props.currentPage + 1);
    
    // Add ellipsis if needed
    if (startPage > 2) {
      pages.push('...');
    }
    
    // Add middle pages
    for (let i = startPage; i <= endPage; i++) {
      pages.push(i);
    }
    
    // Add ellipsis if needed
    if (endPage < totalPages.value - 1) {
      pages.push('...');
    }
    
    // Always show last page
    pages.push(totalPages.value);
  }
  
  return pages;
});

const onPageChange = (page) => {
  if (typeof page === 'number' && page !== props.currentPage) {
    emit('page-change', page);
  }
};
</script>

<style scoped>
.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 20px 0;
}

.pagination-btn {
  padding: 8px 16px;
  background-color: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  cursor: pointer;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-numbers {
  display: flex;
  margin: 0 10px;
}

.page-number {
  width: 40px;
  height: 40px;
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 5px;
  border: 1px solid #dee2e6;
  border-radius: 4px;
  background-color: #fff;
  cursor: pointer;
}

.page-number.active {
  background-color: #007bff;
  color: white;
  border-color: #007bff;
}
</style>
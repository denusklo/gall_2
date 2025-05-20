<!-- resources/js/components/Gallery/GalleryStats.vue -->
<template>
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-image"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value">{{ stats.totalImages }}</div>
        <div class="stat-label">Total Images</div>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-hdd"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value">{{ formatFileSize(stats.totalStorage) }}</div>
        <div class="stat-label">Total Storage</div>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-icon">
        <i class="fas fa-calendar"></i>
      </div>
      <div class="stat-info">
        <div class="stat-value">{{ stats.recentUploads }}</div>
        <div class="stat-label">Uploads This Month</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps } from 'vue';

const props = defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      totalImages: 0,
      totalStorage: 0,
      recentUploads: 0
    })
  }
});

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
</script>


<!-- resources/js/components/Gallery/GalleryStats.vue -->
<style scoped>
.stats-container {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  flex: 1;
  min-width: 200px;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 20px;
  display: flex;
  align-items: center;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  background-color: #f0f7ff;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-right: 15px;
  font-size: 1.5rem;
  color: #007bff;
}

.stat-info {
  flex: 1;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #333;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 0.9rem;
  color: #666;
}

@media (max-width: 768px) {
  .stat-card {
    min-width: 100%;
  }
}
</style>
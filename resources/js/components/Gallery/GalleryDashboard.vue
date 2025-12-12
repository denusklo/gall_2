<!-- resources/js/components/Gallery/GalleryDashboard.vue -->
<template>
  <div class="dashboard-container">
    <h2>Gallery Dashboard</h2>
    
    <gallery-stats :stats="stats" />
    
    <div class="charts-container">
      <div class="chart-card">
        <h3>Upload Timeline</h3>
        <div class="chart-wrapper">
          <bar-chart :data="timelineData" :options="barChartOptions" />
        </div>
      </div>
      
      <div class="chart-card">
        <h3>File Types</h3>
        <div class="chart-wrapper">
          <pie-chart :data="fileTypeData" :options="pieChartOptions" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Bar as BarChart, Pie as PieChart } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import GalleryStats from './GalleryStats.vue';

// Register Chart.js components
ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

const props = defineProps({
  stats: {
    type: Object,
    required: true
  }
});

// Prepare data for the bar chart (timeline)
const timelineData = computed(() => {
  if (!props.stats.timeline || props.stats.timeline.length === 0) {
    return {
      labels: ['No Data'],
      datasets: [{
        label: 'Uploads',
        data: [0],
        backgroundColor: '#007bff',
      }]
    };
  }
  
  return {
    labels: props.stats.timeline.map(item => item.month),
    datasets: [{
      label: 'Uploads',
      data: props.stats.timeline.map(item => item.count),
      backgroundColor: '#007bff',
    }]
  };
});

// Prepare data for the pie chart (file types)
const fileTypeData = computed(() => {
  if (!props.stats.fileTypes || props.stats.fileTypes.length === 0) {
    return {
      labels: ['No Data'],
      datasets: [{
        data: [1],
        backgroundColor: ['#ccc'],
      }]
    };
  }
  
  const fileTypeColors = {
    'image/jpeg': '#FF6384',
    'image/png': '#36A2EB',
    'image/gif': '#FFCE56',
    'image/webp': '#4BC0C0',
    'default': '#9966FF'
  };
  
  return {
    labels: props.stats.fileTypes.map(item => {
      const type = item.mime_type.split('/')[1].toUpperCase();
      return `${type} (${item.count})`;
    }),
    datasets: [{
      data: props.stats.fileTypes.map(item => item.count),
      backgroundColor: props.stats.fileTypes.map(item => 
        fileTypeColors[item.mime_type] || fileTypeColors.default
      ),
    }]
  };
});

// Chart options
const barChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          return `Uploads: ${context.parsed.y}`;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0
      }
    }
  }
};

const pieChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right'
    }
  }
};
</script>

<style scoped>
.dashboard-container {
  margin-bottom: 40px;
}

h2 {
  margin-bottom: 20px;
  font-size: 1.5rem;
  color: #333;
}

.charts-container {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-top: 30px;
}

.chart-card {
  flex: 1;
  min-width: 300px;
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  padding: 20px;
}

.chart-card h3 {
  margin-top: 0;
  margin-bottom: 15px;
  font-size: 1.2rem;
  color: #333;
}

.chart-wrapper {
  height: 300px;
}

@media (max-width: 768px) {
  .chart-card {
    min-width: 100%;
  }
}
</style>
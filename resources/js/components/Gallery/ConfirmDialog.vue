<!-- resources/js/components/Gallery/ConfirmDialog.vue -->
<template>
  <transition name="fade">
    <div v-if="show" class="confirm-dialog-backdrop" @click="cancelAction">
      <div class="confirm-dialog" @click.stop>
        <div class="confirm-dialog-header">
          <h3>{{ title }}</h3>
          <button @click="cancelAction" class="close-btn">&times;</button>
        </div>
        
        <div class="confirm-dialog-body">
          <p>{{ message }}</p>
        </div>
        
        <div class="confirm-dialog-footer">
          <button 
            @click="cancelAction" 
            class="btn btn-secondary"
          >
            {{ cancelText }}
          </button>
          <button 
            @click="confirmAction" 
            class="btn"
            :class="confirmButtonClass"
          >
            {{ confirmText }}
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { defineProps, defineEmits, computed } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Confirm Action'
  },
  message: {
    type: String,
    default: 'Are you sure you want to perform this action?'
  },
  confirmText: {
    type: String,
    default: 'Confirm'
  },
  cancelText: {
    type: String,
    default: 'Cancel'
  },
  type: {
    type: String,
    default: 'primary', // primary, danger, warning
    validator: (value) => ['primary', 'danger', 'warning'].includes(value)
  }
});

const emit = defineEmits(['confirm', 'cancel']);

const confirmButtonClass = computed(() => {
  switch (props.type) {
    case 'danger':
      return 'btn-danger';
    case 'warning':
      return 'btn-warning';
    default:
      return 'btn-primary';
  }
});

const confirmAction = () => {
  emit('confirm');
};

const cancelAction = () => {
  emit('cancel');
};
</script>

<style scoped>
.confirm-dialog-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
}

.confirm-dialog {
  background-color: white;
  border-radius: 8px;
  width: 90%;
  max-width: 500px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.confirm-dialog-header {
  padding: 15px;
  border-bottom: 1px solid #ddd;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.confirm-dialog-header h3 {
  margin: 0;
  font-size: 1.2rem;
}

.confirm-dialog-body {
  padding: 20px 15px;
}

.confirm-dialog-body p {
  margin: 0;
  line-height: 1.5;
}

.confirm-dialog-footer {
  padding: 15px;
  border-top: 1px solid #ddd;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-danger {
  background-color: #dc3545;
  color: white;
}

.btn-warning {
  background-color: #ffc107;
  color: #212529;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
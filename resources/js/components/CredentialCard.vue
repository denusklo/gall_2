<template>
  <div class="card mb-3" :class="{ 'border-primary': credential.is_default }">
    <div class="card-header d-flex justify-content-between align-items-center" :class="{ 'bg-primary text-white': credential.is_default }">
      <div class="d-flex align-items-center">
        <h6 class="mb-0 me-2">{{ credential.name }}</h6>
        <span v-if="credential.is_default" class="badge bg-light text-dark">Default</span>
        <span v-if="credential.is_verified" class="badge bg-success ms-1">Verified</span>
        <span v-else class="badge bg-warning ms-1">Not Verified</span>
      </div>
      <div class="dropdown">
        <button class="btn btn-sm" :class="credential.is_default ? 'btn-light' : 'btn-outline-secondary'" type="button" data-toggle="dropdown">
          <i class="fa fa-ellipsis-v"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-right">
          <button v-if="!credential.is_default" class="dropdown-item" @click="$emit('set-default', credential)">
            <i class="fa fa-star me-2"></i>Set as Default
          </button>
          <button class="dropdown-item" @click="$emit('edit', credential)">
            <i class="fa fa-edit me-2"></i>Edit
          </button>
          <div class="dropdown-divider"></div>
          <button class="dropdown-item text-danger" @click="$emit('delete', credential)">
            <i class="fa fa-trash me-2"></i>Delete
          </button>
        </div>
      </div>
    </div>
    <div class="card-body">
      <!-- Supabase info -->
      <div v-if="credential.provider === 'supabase'">
        <div class="mb-2">
          <small class="text-muted">API URL</small>
          <div class="text-truncate">{{ credential.supabase_url || '-' }}</div>
        </div>
        <div class="mb-2">
          <small class="text-muted">Bucket</small>
          <div>{{ credential.supabase_bucket || 'images' }}</div>
        </div>
        <div class="mb-3">
          <small class="text-muted">Public Key</small>
          <div><code>{{ credential.supabase_key_masked || '-' }}</code></div>
        </div>
        <button
          class="btn btn-outline-primary btn-sm"
          @click="$emit('test', credential)"
          :disabled="testing || !canTestSupabase"
        >
          <span v-if="testing" class="spinner-border spinner-border-sm me-2"></span>
          {{ testing ? 'Testing...' : 'Test Connection' }}
        </button>
      </div>

      <!-- Vercel info -->
      <div v-if="credential.provider === 'vercel'">
        <div class="mb-2">
          <small class="text-muted">Store URL</small>
          <div class="text-truncate">{{ credential.vercel_blob_store_url || '-' }}</div>
        </div>
        <div class="mb-3">
          <small class="text-muted">Token</small>
          <div><code>{{ credential.vercel_blob_token_masked || '-' }}</code></div>
        </div>
        <button
          class="btn btn-outline-primary btn-sm"
          @click="$emit('test', credential)"
          :disabled="testing || !canTestVercel"
        >
          <span v-if="testing" class="spinner-border spinner-border-sm me-2"></span>
          {{ testing ? 'Testing...' : 'Test Connection' }}
        </button>
      </div>

      <!-- Test Result -->
      <div v-if="testResult" class="alert mt-3 mb-0 p-2" :class="testResult.success ? 'alert-success' : 'alert-danger'">
        <small>
          <strong>{{ testResult.success ? 'Success!' : 'Failed' }}</strong>
          {{ testResult.message }}
        </small>
      </div>
    </div>
    <div class="card-footer text-muted small">
      Created {{ formatDate(credential.created_at) }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  credential: {
    type: Object,
    required: true
  }
});

defineEmits(['edit', 'delete', 'set-default', 'test']);

const testing = computed(() => false); // Could be passed from parent if needed

const testResult = computed(() => null); // Could be passed from parent if needed

const canTestSupabase = computed(() => {
  return props.credential.provider === 'supabase' && props.credential.supabase_url;
});

const canTestVercel = computed(() => {
  return props.credential.provider === 'vercel' && props.credential.vercel_blob_token_masked;
});

function formatDate(dateString) {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString();
}
</script>

<style scoped>
.code {
  font-size: 0.75rem;
}
</style>

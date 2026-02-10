<template>
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <h1 class="mb-4">Storage Settings</h1>
        <p>Debug: store exists = {{ !!store }}</p>
        <p>Debug: store.loading = {{ store.loading }}</p>
        <p>Debug: store.error = {{ store.error }}</p>
        <p>Debug: settings = {{ JSON.stringify(store.settings) }}</p>
        <p>Debug: hasSupabaseConfig = {{ store.hasSupabaseConfig }}</p>
        <div v-if="store.loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>

      </div>
        <!-- Success message -->
        <div v-if="saveSuccess" class="alert alert-success alert-dismissible fade show" role="alert">
          Storage settings saved successfully!
          <button type="button" class="btn-close" @click="saveSuccess = false"></button>
        </div>

      <!-- Default Storage Provider Selection -->
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Default Storage Provider</h5>
        </div>
        <div class="card-body">
          <div class="form-check mb-2">
            <input
              class="form-check-input"
              type="radio"
              id="provider-supabase"
              value="supabase"
              v-model="form.storage_provider"
            />
            <label class="form-check-label" for="provider-supabase">
              Supabase
            </label>
          </div>
          <div class="form-check">
            <input
              class="form-check-input"
              type="radio"
              id="provider-vercel"
              value="vercel"
              v-model="form.storage_provider"
            />
            <label class="form-check-label" for="provider-vercel">
              Vercel Blob
            </label>
          </div>
        </div>
      </div>

      <!-- Supabase Configuration -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Supabase Configuration</h5>
          <span v-if="store.hasSupabaseConfig && store.isVerified" class="badge bg-success">
            Verified
          </span>
          <span v-else-if="store.hasSupabaseConfig && !store.isVerified" class="badge bg-warning">
            Not Verified
          </span>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label for="supabase-url" class="form-label">API URL</label>
            <input
              type="url"
              class="form-control"
              id="supabase-url"
              placeholder="https://xxxxxxxxx.supabase.co"
              v-model="form.supabase_url"
              :disabled="store.loading"
            />
            <small class="form-text text-muted">
              Your Supabase project URL (found in Project Settings > API)
            </small>
          </div>

          <div class="mb-3">
            <label for="supabase-key" class="form-label">Public Anon Key</label>
            <div class="input-group">
              <input
                :type="showSupabaseKey ? 'text' : 'password'"
                class="form-control"
                id="supabase-key"
                placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                v-model="form.supabase_key"
                :disabled="store.loading"
              />
              <button
                class="btn btn-outline-secondary"
                type="button"
                @click="showSupabaseKey = !showSupabaseKey"
              >
                <i :class="showSupabaseKey ? 'fa-eye-slash' : 'fa-eye'"></i>
              </button>
            </div>
            <small class="form-text text-muted">
              Found in Project Settings > API > public anon key
            </small>
          </div>

          <div class="mb-3">
            <label for="supabase-service-key" class="form-label">Service Role Key</label>
            <div class="input-group">
              <input
                :type="showSupabaseServiceKey ? 'text' : 'password'"
                class="form-control"
                id="supabase-service-key"
                placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
                v-model="form.supabase_service_key"
                :disabled="store.loading"
              />
              <button
                class="btn btn-outline-secondary"
                type="button"
                @click="showSupabaseServiceKey = !showSupabaseServiceKey"
              >
                <i :class="showSupabaseServiceKey ? 'fa-eye-slash' : 'fa-eye'"></i>
              </button>
            </div>
            <small class="form-text text-muted">
              Found in Project Settings > API > service_role key (keep secret!)
            </small>
          </div>

          <div class="mb-3">
            <label for="supabase-bucket" class="form-label">Bucket Name</label>
            <input
              type="text"
              class="form-control"
              id="supabase-bucket"
              placeholder="images"
              v-model="form.supabase_bucket"
              :disabled="store.loading"
            />
            <small class="form-text text-muted">
              The name of your storage bucket
            </small>
          </div>

          <!-- Test Connection Button -->
          <div class="d-flex gap-2 mb-3">
            <button
              class="btn btn-outline-primary"
              @click="testSupabaseConnection"
              :disabled="store.testing.supabase || !canTestSupabase"
            >
              <span v-if="store.testing.supabase" class="spinner-border spinner-border-sm me-2"></span>
              {{ store.testing.supabase ? 'Testing...' : 'Test Connection' }}
            </button>
          </div>

          <!-- Test Result -->
          <div v-if="store.testResults.supabase" class="alert" :class="store.testResults.supabase.success ? 'alert-success' : 'alert-danger'">
            <strong>{{ store.testResults.supabase.success ? 'Success!' : 'Failed' }}</strong>
            {{ store.testResults.supabase.message }}
            <div v-if="store.testResults.supabase.details && store.testResults.supabase.details.buckets" class="mt-2">
              <small>Available buckets: {{ store.testResults.supabase.details.buckets.join(', ') }}</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Vercel Blob Configuration -->
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Vercel Blob Configuration</h5>
          <span v-if="store.hasVercelConfig" class="badge bg-success">
            Configured
          </span>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label for="vercel-token" class="form-label">Read/Write Token</label>
            <div class="input-group">
              <input
                :type="showVercelToken ? 'text' : 'password'"
                class="form-control"
                id="vercel-token"
                placeholder="vercel_blob_rw_xxxxxxxxxxxxx_xxxxxxxxxxxxxxxxxxxx"
                v-model="form.vercel_blob_token"
                :disabled="store.loading"
              />
              <button
                class="btn btn-outline-secondary"
                type="button"
                @click="showVercelToken = !showVercelToken"
              >
                <i :class="showVercelToken ? 'fa-eye-slash' : 'fa-eye'"></i>
              </button>
            </div>
            <small class="form-text text-muted">
              Found in your Vercel project dashboard > Storage > Tokens
            </small>
          </div>

          <div class="mb-3">
            <label for="vercel-store-url" class="form-label">Store URL</label>
            <input
              type="url"
              class="form-control"
              id="vercel-store-url"
              placeholder="https://blob.vercel-storage.com"
              v-model="form.vercel_blob_store_url"
              :disabled="store.loading"
            />
            <small class="form-text text-muted">
              Vercel Blob API URL (usually the default is fine)
            </small>
          </div>

          <!-- Test Connection Button -->
          <div class="d-flex gap-2 mb-3">
            <button
              class="btn btn-outline-primary"
              @click="testVercelConnection"
              :disabled="store.testing.vercel || !canTestVercel"
            >
              <span v-if="store.testing.vercel" class="spinner-border spinner-border-sm me-2"></span>
              {{ store.testing.vercel ? 'Testing...' : 'Test Connection' }}
            </button>
          </div>

          <!-- Test Result -->
          <div v-if="store.testResults.vercel" class="alert" :class="store.testResults.vercel.success ? 'alert-success' : 'alert-danger'">
            <strong>{{ store.testResults.vercel.success ? 'Success!' : 'Failed' }}</strong>
            {{ store.testResults.vercel.message }}
            <div v-if="store.testResults.vercel.store_id" class="mt-2">
              <small>Store ID: {{ store.testResults.vercel.store_id }}</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Save Button -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <small v-if="store.lastVerified" class="text-muted">
                Last verified: {{ new Date(store.lastVerified).toLocaleString() }}
              </small>
            </div>
            <div>
              <button
                v-if="store.hasSupabaseConfig"
                class="btn btn-outline-danger me-2"
                @click="deleteProviderCredentials('supabase')"
                :disabled="store.saving"
              >
                Clear Supabase
              </button>
              <button
                v-if="store.hasVercelConfig"
                class="btn btn-outline-danger me-2"
                @click="deleteProviderCredentials('vercel')"
                :disabled="store.saving"
              >
                Clear Vercel
              </button>
              <button
                class="btn btn-primary"
                @click="saveSettings"
                :disabled="store.saving || !hasChanges"
              >
                <span v-if="store.saving" class="spinner-border spinner-border-sm me-2"></span>
                {{ store.saving ? 'Saving...' : 'Save Settings' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorageSettingsStore } from '../stores/storageSettings';

const store = useStorageSettingsStore();

console.log('StorageSettings component - store:', store);
console.log('StorageSettings component - settings value:', store.settings);

const form = ref({
  storage_provider: 'supabase',
  supabase_url: '',
  supabase_key: '',
  supabase_service_key: '',
  supabase_bucket: 'images',
  vercel_blob_token: '',
  vercel_blob_store_url: 'https://blob.vercel-storage.com'
});

const showSupabaseKey = ref(false);
const showSupabaseServiceKey = ref(false);
const showVercelToken = ref(false);
const saveSuccess = ref(false);

const canTestSupabase = computed(() => {
  return form.value.supabase_url && form.value.supabase_key;
});

const canTestVercel = computed(() => {
  return form.value.vercel_blob_token;
});

const hasChanges = computed(() => {
  return (
    form.value.supabase_url !== (store.settings?.supabase_url || '') ||
    form.value.supabase_key !== (store.settings?.supabase_key_masked?.replace(/\*/g, '') || '') ||
    form.value.supabase_service_key !== (store.settings?.supabase_service_key_masked?.replace(/\*/g, '') || '') ||
    form.value.supabase_bucket !== (store.settings?.supabase_bucket || 'images') ||
    form.value.storage_provider !== (store.settings?.storage_provider || 'supabase')
  );
});

onMounted(async () => {
  await store.fetchSettings();
  if (store.settings) {
    form.value.storage_provider = store.settings.storage_provider || 'supabase';
    form.value.supabase_url = store.settings.supabase_url || '';
    form.value.supabase_bucket = store.settings.supabase_bucket || 'images';
    form.value.vercel_blob_store_url = store.settings.vercel_blob_store_url || 'https://blob.vercel-storage.com';
  }
});

async function testSupabaseConnection() {
  store.clearTestResults();
  await store.testSupabaseCredentials(
    form.value.supabase_url,
    form.value.supabase_key,
    form.value.supabase_service_key
  );
}

async function testVercelConnection() {
  store.clearTestResults();
  await store.testVercelCredentials(form.value.vercel_blob_token);
}

async function saveSettings() {
  try {
    await store.updateSettings({
      storage_provider: form.value.storage_provider,
      supabase_url: form.value.supabase_url || undefined,
      supabase_key: form.value.supabase_key || undefined,
      supabase_service_key: form.value.supabase_service_key || undefined,
      supabase_bucket: form.value.supabase_bucket || undefined,
      vercel_blob_token: form.value.vercel_blob_token || undefined,
      vercel_blob_store_url: form.value.vercel_blob_store_url || undefined
    });
    saveSuccess.value = true;
    setTimeout(() => {
      saveSuccess.value = false;
    }, 3000);
  } catch (error) {
    console.error('Failed to save settings:', error);
  }
}

async function deleteProviderCredentials(provider) {
  if (confirm(`Are you sure you want to delete your ${provider} credentials?`)) {
    await store.deleteProvider(provider);
  }
}
</script>

<style scoped>
.card-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
}

.input-group .btn-outline-secondary {
  border-color: #ced4da;
}

.spinner-border-sm {
  width: 1rem;
  height: 1rem;
  border-width: 0.125em;
}
</style>

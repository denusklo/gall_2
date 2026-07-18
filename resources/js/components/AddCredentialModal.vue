<template>
  <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Storage Credential</h5>
          <button type="button" class="btn btn-light" @click="$emit('close')">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <!-- Provider Selection -->
          <div class="mb-4">
            <label class="form-label d-block mb-2">Storage Provider</label>
            <div class="d-flex flex-column gap-2">
              <div class="form-check">
                <input
                  type="radio"
                  class="form-check-input"
                  id="provider-supabase"
                  value="supabase"
                  v-model="form.provider"
                />
                <label class="form-check-label" for="provider-supabase">
                  <i class="fas fa-server me-2"></i> Supabase
                </label>
              </div>

              <div class="form-check">
                <input
                  type="radio"
                  class="form-check-input"
                  id="provider-vercel"
                  value="vercel"
                  v-model="form.provider"
                />
                <label class="form-check-label" for="provider-vercel">
                  <i class="fas fa-cloud me-2"></i> Vercel Blob
                </label>
              </div>
            </div>
          </div>

          <!-- Supabase Form -->
          <div v-if="form.provider === 'supabase'">
            <div class="mb-3">
              <label for="supabase-url" class="form-label">API URL</label>
              <input
                type="url"
                class="form-control"
                id="supabase-url"
                placeholder="https://xxxxxxxxx.supabase.co"
                v-model="form.supabase_url"
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
                />
                <button class="btn btn-outline-secondary" type="button" @click="showSupabaseKey = !showSupabaseKey">
                  <i :class="showSupabaseKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
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
                />
                <button class="btn btn-outline-secondary" type="button" @click="showSupabaseServiceKey = !showSupabaseServiceKey">
                  <i :class="showSupabaseServiceKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label for="supabase-bucket" class="form-label">Bucket Name</label>
              <input
                type="text"
                class="form-control"
                id="supabase-bucket"
                placeholder="images"
                v-model="form.supabase_bucket"
              />
            </div>
          </div>

          <!-- Vercel Form -->
          <div v-if="form.provider === 'vercel'">
            <div class="mb-3">
              <label for="vercel-token" class="form-label">Read/Write Token</label>
              <div class="input-group">
                <input
                  :type="showVercelToken ? 'text' : 'password'"
                  class="form-control"
                  id="vercel-token"
                  placeholder="vercel_blob_rw_xxxxxxxxxxxxx_xxxxxxxxxxxxxxxxxxxx"
                  v-model="form.vercel_blob_token"
                />
                <button class="btn btn-outline-secondary" type="button" @click="showVercelToken = !showVercelToken">
                  <i :class="showVercelToken ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
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
              />
            </div>
          </div>

          <!-- Set as Default -->
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is-default" v-model="form.is_default" />
            <label class="form-check-label" for="is-default">
              Set as default credential for uploads
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
          <button
            type="button"
            class="btn btn-primary"
            @click="handleSave"
            :disabled="!canSave || saving"
          >
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            {{ saving ? 'Saving...' : 'Save Credential' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';

const emit = defineEmits(['close', 'save']);
const store = useStorageCredentialsStore();

const form = ref({
  provider: 'supabase',
  is_default: false,
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
const saving = ref(false);

const canSave = computed(() => {
  if (form.value.provider === 'supabase') {
    return form.value.supabase_url && form.value.supabase_key;
  }
  if (form.value.provider === 'vercel') {
    return form.value.vercel_blob_token;
  }
  return false;
});

async function handleSave() {
  if (!canSave.value) return;

  saving.value = true;
  try {
    await store.createCredential(form.value);
    emit('save');
  } catch (error) {
    console.error('Failed to save credential:', error);
  } finally {
    saving.value = false;
  }
}
</script>

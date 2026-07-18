<template>
  <div v-if="credential" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit {{ credential.name }}</h5>
          <button type="button" class="btn btn-light" @click="$emit('close')">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3">
            <i class="fas fa-info-circle me-2"></i>
            Provider cannot be changed after creation. Name is auto-generated and cannot be modified.
          </p>

          <!-- Supabase Form -->
          <div v-if="credential.provider === 'supabase'">
            <div class="mb-3">
              <label for="edit-supabase-url" class="form-label">API URL</label>
              <input
                type="url"
                class="form-control"
                id="edit-supabase-url"
                v-model="form.supabase_url"
              />
            </div>

            <div class="mb-3">
              <label for="edit-supabase-key" class="form-label">Public Anon Key</label>
              <div class="input-group">
                <input
                  :type="showSupabaseKey ? 'text' : 'password'"
                  class="form-control"
                  id="edit-supabase-key"
                  placeholder="Leave empty to keep current"
                  v-model="form.supabase_key"
                />
                <button class="btn btn-outline-secondary" type="button" @click="showSupabaseKey = !showSupabaseKey">
                  <i :class="showSupabaseKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label for="edit-supabase-service-key" class="form-label">Service Role Key</label>
              <div class="input-group">
                <input
                  :type="showSupabaseServiceKey ? 'text' : 'password'"
                  class="form-control"
                  id="edit-supabase-service-key"
                  placeholder="Leave empty to keep current"
                  v-model="form.supabase_service_key"
                />
                <button class="btn btn-outline-secondary" type="button" @click="showSupabaseServiceKey = !showSupabaseServiceKey">
                  <i :class="showSupabaseServiceKey ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label for="edit-supabase-bucket" class="form-label">Bucket Name</label>
              <input
                type="text"
                class="form-control"
                id="edit-supabase-bucket"
                v-model="form.supabase_bucket"
              />
            </div>
          </div>

          <!-- Vercel Form -->
          <div v-if="credential.provider === 'vercel'">
            <div class="mb-3">
              <label for="edit-vercel-token" class="form-label">Read/Write Token</label>
              <div class="input-group">
                <input
                  :type="showVercelToken ? 'text' : 'password'"
                  class="form-control"
                  id="edit-vercel-token"
                  placeholder="Leave empty to keep current"
                  v-model="form.vercel_blob_token"
                />
                <button class="btn btn-outline-secondary" type="button" @click="showVercelToken = !showVercelToken">
                  <i :class="showVercelToken ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label for="edit-vercel-store-url" class="form-label">Store URL</label>
              <input
                type="url"
                class="form-control"
                id="edit-vercel-store-url"
                v-model="form.vercel_blob_store_url"
              />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="$emit('close')">Cancel</button>
          <button
            type="button"
            class="btn btn-primary"
            @click="handleSave"
            :disabled="saving"
          >
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            {{ saving ? 'Saving...' : 'Update Credential' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';

const props = defineProps({
  credential: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['close', 'save']);
const store = useStorageCredentialsStore();

const form = ref({
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

// Initialize form when credential changes
watch(() => props.credential, (newCred) => {
  if (newCred) {
    form.value = {
      supabase_url: newCred.supabase_url || '',
      supabase_key: '', // Don't pre-fill for security
      supabase_service_key: '', // Don't pre-fill for security
      supabase_bucket: newCred.supabase_bucket || 'images',
      vercel_blob_token: '', // Don't pre-fill for security
      vercel_blob_store_url: newCred.vercel_blob_store_url || 'https://blob.vercel-storage.com'
    };
  }
}, { immediate: true });

async function handleSave() {
  saving.value = true;
  try {
    // Only send fields that have values
    const data = {};
    if (form.value.supabase_url) data.supabase_url = form.value.supabase_url;
    if (form.value.supabase_key) data.supabase_key = form.value.supabase_key;
    if (form.value.supabase_service_key) data.supabase_service_key = form.value.supabase_service_key;
    if (form.value.supabase_bucket) data.supabase_bucket = form.value.supabase_bucket;
    if (form.value.vercel_blob_token) data.vercel_blob_token = form.value.vercel_blob_token;
    if (form.value.vercel_blob_store_url) data.vercel_blob_store_url = form.value.vercel_blob_store_url;

    await emit('save', { id: props.credential.id, data });
  } catch (error) {
    console.error('Failed to update credential:', error);
  } finally {
    saving.value = false;
  }
}
</script>

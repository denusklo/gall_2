<template>
  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1 class="mb-0">Storage Settings</h1>
          <button class="btn btn-primary" @click="openAddModal">
            <i class="fa fa-plus me-2"></i>Add Credential
          </button>
        </div>

        <!-- Loading state -->
        <div v-if="store.loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="store.error" class="alert alert-danger alert-dismissible fade show" role="alert">
          {{ store.error }}
          <button type="button" class="btn-close" @click="store.clearError()"></button>
        </div>

        <!-- No credentials state -->
        <div v-if="!store.loading && !store.hasCredentials" class="card mb-4">
          <div class="card-body text-center py-5">
            <i class="fa fa-database fa-3x text-muted mb-3"></i>
            <h5>No Storage Credentials</h5>
            <p class="text-muted">Add your first storage credential to start uploading images.</p>
            <button class="btn btn-primary" @click="openAddModal">
              <i class="fa fa-plus me-2"></i>Add Credential
            </button>
          </div>
        </div>

        <!-- Supabase Credentials -->
        <div v-if="!store.loading && store.supabaseCredentials.length > 0" class="mb-4">
          <h5 class="mb-3">
            <i class="fa fa-server me-2"></i>Supabase Credentials
          </h5>
          <CredentialCard
            v-for="cred in store.supabaseCredentials"
            :key="cred.id"
            :credential="cred"
            @edit="openEditModal"
            @delete="confirmDelete"
            @set-default="confirmSetDefault"
            @test="testSupabase"
          />
        </div>

        <!-- Vercel Credentials -->
        <div v-if="!store.loading && store.vercelCredentials.length > 0" class="mb-4">
          <h5 class="mb-3">
            <i class="fa fa-cloud me-2"></i>Vercel Blob Credentials
          </h5>
          <CredentialCard
            v-for="cred in store.vercelCredentials"
            :key="cred.id"
            :credential="cred"
            @edit="openEditModal"
            @delete="confirmDelete"
            @set-default="confirmSetDefault"
            @test="testVercel"
          />
        </div>
      </div>
    </div>

    <!-- Add Credential Modal -->
    <AddCredentialModal
      v-if="showAddModal"
      @close="closeAddModal"
      @save="handleAddCredential"
    />

    <!-- Edit Credential Modal -->
    <EditCredentialModal
      v-if="showEditModal"
      :credential="editingCredential"
      @close="closeEditModal"
      @save="handleUpdateCredential"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';
import CredentialCard from './CredentialCard.vue';
import AddCredentialModal from './AddCredentialModal.vue';
import EditCredentialModal from './EditCredentialModal.vue';

const store = useStorageCredentialsStore();

const showAddModal = computed(() => store.showAddModal);
const showEditModal = computed(() => store.showEditModal);
const editingCredential = computed(() => store.editingCredential);

onMounted(async () => {
  await store.fetchCredentials();
});

function openAddModal() {
  store.openAddModal();
}

function closeAddModal() {
  store.closeAddModal();
}

function openEditModal(credential) {
  store.openEditModal(credential);
}

function closeEditModal() {
  store.closeEditModal();
}

async function handleAddCredential(data) {
  try {
    await store.createCredential(data);
    closeAddModal();
  } catch (error) {
    console.error('Failed to add credential:', error);
  }
}

async function handleUpdateCredential({ id, data }) {
  try {
    await store.updateCredential(id, data);
    closeEditModal();
  } catch (error) {
    console.error('Failed to update credential:', error);
  }
}

function confirmDelete(credential) {
  if (confirm(`Are you sure you want to delete "${credential.name}"?`)) {
    store.deleteCredential(credential.id);
  }
}

async function confirmSetDefault(credential) {
  try {
    await store.setAsDefault(credential.id);
  } catch (error) {
    console.error('Failed to set default:', error);
  }
}

async function testSupabase(credential) {
  try {
    await store.testCredential(credential);
  } catch (error) {
    console.error('Test failed:', error);
  }
}

async function testVercel(credential) {
  try {
    await store.testCredential(credential);
  } catch (error) {
    console.error('Test failed:', error);
  }
}
</script>

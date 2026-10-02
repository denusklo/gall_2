<template>
  <section class="container storage-settings" aria-labelledby="storage-settings-title">
    <header class="settings-header">
      <div>
        <h1 id="storage-settings-title">Storage settings</h1>
        <p>Save your Supabase and Vercel Blob accounts, then choose one for each upload.</p>
      </div>
      <button type="button" class="btn settings-primary" @click="openAddModal" :disabled="store.loading">
        <i class="fa fa-plus" aria-hidden="true"></i> Add account
      </button>
    </header>

    <p v-if="notice" class="settings-notice" role="status">{{ notice }}</p>

    <div v-if="store.error && !showAddModal" class="settings-error" role="alert">
      <div>
        <strong>Could not complete the request.</strong>
        <ul><li v-for="(message, index) in errorMessages" :key="index">{{ message }}</li></ul>
      </div>
      <button type="button" class="btn settings-secondary" @click="loadAccounts" :disabled="store.loading">Reload accounts</button>
    </div>

    <div class="accounts-surface" :aria-busy="store.loading">
      <div v-if="store.loading" class="accounts-loading" role="status">
        <p>Loading accounts…</p>
        <div class="loading-line" aria-hidden="true"></div>
        <div class="loading-line loading-line-short" aria-hidden="true"></div>
      </div>

      <div v-else-if="!store.hasCredentials && !store.error" class="accounts-empty">
        <i class="fa fa-database" aria-hidden="true"></i>
        <h2>No accounts yet</h2>
        <p>Choose <strong>Add account</strong> to connect your storage. You can save multiple accounts for either provider.</p>
      </div>

      <div v-else-if="!store.hasCredentials" class="accounts-unavailable">
        Your accounts could not be loaded. Reload them before making changes.
      </div>

      <template v-else>
        <section v-for="group in accountGroups.filter(group => group.accounts.length)" :key="group.provider"
          class="account-group" :aria-labelledby="`accounts-${group.provider}`">
          <header class="account-group-header">
            <h2 :id="`accounts-${group.provider}`">{{ group.label }}</h2>
            <span>{{ group.accounts.length }} {{ group.accounts.length === 1 ? 'account' : 'accounts' }}</span>
          </header>
          <CredentialCard v-for="credential in group.accounts" :key="credential.id" :credential="credential"
            @edit="openEditModal" @delete="confirmDelete" @set-default="confirmSetDefault" @test="testCredential" />
        </section>
      </template>
    </div>

    <AddCredentialModal v-if="showAddModal" @close="closeAddModal" @save="handleAddCredential" />
    <EditCredentialModal v-if="showEditModal" :credential="editingCredential"
      @close="closeEditModal" @save="handleUpdateCredential" />
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';
import CredentialCard from './CredentialCard.vue';
import AddCredentialModal from './AddCredentialModal.vue';
import EditCredentialModal from './EditCredentialModal.vue';

const store = useStorageCredentialsStore();
const notice = ref('');
const showAddModal = computed(() => store.showAddModal);
const showEditModal = computed(() => store.showEditModal);
const editingCredential = computed(() => store.editingCredential);
const accountGroups = computed(() => [
  { provider: 'supabase', label: 'Supabase', accounts: store.supabaseCredentials },
  { provider: 'vercel', label: 'Vercel Blob', accounts: store.vercelCredentials }
]);
const errorMessages = computed(() => {
  if (typeof store.error === 'string') return [store.error];
  return Object.values(store.error || {}).flat().map(String);
});

onMounted(loadAccounts);
async function loadAccounts() {
  try { await store.fetchCredentials(); } catch { /* The store exposes the inline error. */ }
}
function openAddModal() {
  notice.value = '';
  store.clearError();
  store.openAddModal();
}
function closeAddModal() {
  store.closeAddModal();
  store.clearError();
}
function handleAddCredential() {
  // The dialog owns the single create request; this event only confirms success.
  store.closeAddModal();
  notice.value = 'Account added.';
}
function openEditModal(credential) {
  notice.value = '';
  store.clearError();
  store.openEditModal(credential);
}
function closeEditModal() {
  store.closeEditModal();
}
function handleUpdateCredential({ refreshFailed = false } = {}) {
  // The dialog owns the single update request; this event only confirms success.
  store.closeEditModal();
  notice.value = refreshFailed ? 'Account updated. Reload accounts to see your changes.' : 'Account updated.';
  if (refreshFailed) store.error = 'Could not refresh the account list. Your changes were saved.';
}
async function confirmDelete(credential) {
  if (confirm(`Are you sure you want to delete "${credential.name}"?`)) {
    try { await store.deleteCredential(credential.id); } catch { /* Keep the account and show the error. */ }
  }
}
async function confirmSetDefault(credential) {
  try { await store.setAsDefault(credential.id); } catch { /* The store exposes the inline error. */ }
}
async function testCredential(credential) {
  try { await store.testCredential(credential); } catch { /* Test state remains in the existing store. */ }
}
</script>

<style scoped>
.storage-settings { max-width: 56rem; padding-top: 1.75rem; padding-bottom: 2rem; color: #263445; }
.settings-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1.5rem; margin-bottom: 1.5rem; }
.settings-header h1 { margin: 0 0 .5rem; font-size: 1.5rem; font-weight: 700; letter-spacing: -.02em; }
.settings-header p { margin: 0; max-width: 38rem; color: #536475; line-height: 1.6; }
.settings-primary { flex: 0 0 auto; min-height: 2.625rem; white-space: nowrap; color: #fff; background: #2474b4; border: 1px solid #2474b4; }
.settings-primary:hover { color: #fff; background: #1c5d91; border-color: #1c5d91; }
.settings-primary i { margin-right: .25rem; }
.settings-secondary { min-height: 2.625rem; white-space: nowrap; color: #263445; background: #fff; border: 1px solid #aab8c6; }
.settings-secondary:hover { background: #e7edf3; }
.accounts-surface { padding: 1.5rem; background: #fff; border: 1px solid #d9e1e8; border-radius: .5rem; }
.accounts-empty { padding: 1.25rem 0; max-width: 32rem; }
.accounts-empty > i { font-size: 1.25rem; color: #536475; margin-bottom: 1rem; }
.accounts-empty h2, .account-group-header h2 { font-size: 1.0625rem; font-weight: 700; margin: 0; }
.accounts-empty p { margin: .625rem 0 0; color: #536475; line-height: 1.6; }
.account-group + .account-group { margin-top: 1.75rem; }
.account-group-header { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; margin-bottom: .875rem; }
.account-group-header span { font-size: .8125rem; color: #536475; font-variant-numeric: tabular-nums; }
.accounts-loading p { margin: 0 0 .875rem; color: #536475; }
.loading-line { height: .75rem; max-width: 24rem; border-radius: .25rem; background: #e7edf3; }
.loading-line-short { max-width: 16rem; margin-top: .625rem; }
.accounts-unavailable { color: #536475; line-height: 1.6; }
.settings-error { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem; margin-bottom: 1rem; border-left: 3px solid #a52834; background: #fff1f2; color: #82212a; }
.settings-error ul { padding-left: 1.25rem; margin: .25rem 0 0; overflow-wrap: anywhere; }
.settings-notice { padding: .75rem 1rem; background: #eff6fc; border-left: 3px solid #2474b4; color: #263445; }
button:focus-visible { outline: 3px solid #2474b4; outline-offset: 3px; box-shadow: none; }
@media (max-width: 575px) {
  .storage-settings { padding-top: 1rem; }
  .settings-header { flex-direction: column; gap: 1rem; }
  .accounts-surface { padding: 1rem; }
  .settings-error { flex-direction: column; }
}
</style>

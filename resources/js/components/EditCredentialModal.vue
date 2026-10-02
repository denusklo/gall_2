<template>
  <dialog ref="dialog" class="account-dialog" aria-labelledby="edit-account-title"
    aria-describedby="edit-account-description" @cancel.prevent="requestClose" @keydown.tab="trapFocus">
    <form @submit.prevent="handleSave" :aria-busy="saving" novalidate>
      <header class="account-dialog-header">
        <div>
          <h2 id="edit-account-title">Edit account</h2>
          <p id="edit-account-description">Update the connection details. Leave replacement keys blank to keep the stored keys.</p>
        </div>
        <button type="button" class="account-close" aria-label="Close edit account" :disabled="saving" @click="requestClose">
          <span aria-hidden="true">&times;</span>
        </button>
      </header>

      <div class="account-dialog-body">
        <div v-if="saveError" class="account-error" role="alert" tabindex="-1">{{ saveError }}</div>

        <dl class="account-summary">
          <div><dt>Account name</dt><dd>{{ credential.name }}</dd></div>
          <div><dt>Storage provider</dt><dd>{{ providerLabel }}</dd></div>
        </dl>
        <p class="field-help">The name and provider cannot be changed. Your default account stays the same.</p>

        <fieldset class="account-fields connection-fields" :disabled="saving">
          <legend>{{ credential.provider === 'supabase' ? 'Supabase project' : 'Vercel Blob store' }}</legend>
          <div v-for="field in providerFields" :key="field.name" class="account-field">
            <label :for="field.id">{{ field.label }} <span v-if="!field.required" class="field-optional">optional</span></label>
            <div :class="{ 'secret-input': field.secret }">
              <input :id="field.id" :name="field.name" v-model="form[field.name]" class="form-control"
                :type="field.secret && !visible[field.name] ? 'password' : field.type"
                :required="field.required" :minlength="field.minlength" :placeholder="field.placeholder"
                :autofocus="field === providerFields[0]"
                :autocomplete="field.secret ? 'new-password' : 'off'" autocapitalize="none" :spellcheck="false"
                :aria-invalid="fieldErrors[field.name] ? 'true' : undefined"
                :aria-describedby="`${field.id}-help${fieldErrors[field.name] ? ` ${field.id}-error` : ''}`">
              <button v-if="field.secret" type="button" class="secret-toggle"
                :aria-label="`${visible[field.name] ? 'Hide' : 'Show'} ${field.label.toLowerCase()}`"
                :aria-pressed="!!visible[field.name]" :aria-controls="field.id"
                @click="visible[field.name] = !visible[field.name]">
                {{ visible[field.name] ? 'Hide' : 'Show' }}
              </button>
            </div>
            <small :id="`${field.id}-help`" class="field-help">{{ field.help }}</small>
            <p v-if="fieldErrors[field.name]" :id="`${field.id}-error`" class="field-error">
              {{ fieldErrors[field.name].join(' ') }}
            </p>
          </div>
        </fieldset>
      </div>

      <footer class="account-dialog-footer">
        <span v-if="saving" role="status" class="save-status">Saving account…</span>
        <button type="button" class="btn account-cancel" :disabled="saving" @click="requestClose">Cancel</button>
        <button type="submit" class="btn account-primary" :disabled="saving || !providerFields.length">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </footer>
    </form>
  </dialog>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';

const props = defineProps({ credential: { type: Object, required: true } });
const emit = defineEmits(['close', 'save']);
const store = useStorageCredentialsStore();
const dialog = ref(null);
let previousFocus;
let settingsRoot;
const form = ref({});
const visible = ref({});
const saving = ref(false);
const saveError = ref('');
const fieldErrors = ref({});
const fields = {
  supabase: [
    { name: 'supabase_url', id: 'edit-supabase-url', label: 'Project URL', type: 'url', required: true,
      placeholder: 'https://your-project.supabase.co', help: 'Use the project URL for this account.' },
    { name: 'supabase_key', id: 'edit-supabase-key', label: 'Public anon key', type: 'text', secret: true, minlength: 20,
      placeholder: 'Leave blank to keep current key', help: 'Leave blank to keep the stored public anon key.' },
    { name: 'supabase_service_key', id: 'edit-supabase-service-key', label: 'Service role key', type: 'text', secret: true, minlength: 20,
      placeholder: 'Leave blank to keep current key', help: 'Leave blank to keep the stored service role key.' },
    { name: 'supabase_bucket', id: 'edit-supabase-bucket', label: 'Bucket name', type: 'text',
      placeholder: 'images', help: 'Use an existing bucket. Leave blank to keep the current bucket.' }
  ],
  vercel: [
    { name: 'vercel_blob_token', id: 'edit-vercel-token', label: 'Read/write token', type: 'text', secret: true, minlength: 30,
      placeholder: 'Leave blank to keep current token', help: 'Leave blank to keep the stored read/write token.' },
    { name: 'vercel_blob_store_url', id: 'edit-vercel-store-url', label: 'Store URL', type: 'url', required: true,
      placeholder: 'https://blob.vercel-storage.com', help: 'Keep the current URL unless this account uses a different store URL.' }
  ]
};
const providerFields = computed(() => fields[props.credential.provider] || []);
const providerLabel = computed(() => props.credential.provider === 'supabase' ? 'Supabase' : 'Vercel Blob');

watch(() => props.credential, credential => {
  if (!credential) return;
  // Read only connection details. Never copy stored or masked secrets into inputs.
  form.value = Object.fromEntries((fields[credential.provider] || [])
    .map(field => [field.name, field.secret ? '' : (credential[field.name] || '')]));
  visible.value = {};
  fieldErrors.value = {};
  saveError.value = '';
}, { immediate: true });

onMounted(() => {
  previousFocus = document.activeElement;
  settingsRoot = dialog.value.closest('.storage-settings');
  dialog.value.showModal();
});
onBeforeUnmount(() => {
  const name = props.credential.name;
  dialog.value?.close();
  nextTick(() => {
    if (previousFocus?.isConnected && previousFocus !== document.body && previousFocus !== document.documentElement
      && previousFocus.tabIndex >= 0 && !previousFocus.matches(':disabled') && previousFocus.getClientRects().length) {
      previousFocus.focus();
      return;
    }
    // Refresh replaces the account cards. Restore to the same account's menu,
    // or the page action if the account list is unavailable after saving.
    const card = [...(settingsRoot?.querySelectorAll('.card') || [])]
      .find(element => element.querySelector('h6')?.textContent.trim() === name);
    (card?.querySelector('[data-toggle="dropdown"]') || settingsRoot?.querySelector('.settings-primary'))?.focus();
  });
});
function requestClose() {
  if (!saving.value) emit('close');
}
function trapFocus(event) {
  const controls = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled)')];
  const first = controls[0];
  const last = controls[controls.length - 1];
  if (!first) {
    event.preventDefault();
  } else if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
async function handleSave() {
  if (saving.value || !providerFields.value.length) return;
  saveError.value = '';
  fieldErrors.value = {};
  const payload = {};
  for (const field of providerFields.value) {
    const value = form.value[field.name].trim();
    if (field.required && !value) {
      fieldErrors.value[field.name] = [`Enter the ${field.label.toLowerCase()}.`];
    } else if (value && field.type === 'url') {
      try {
        const url = new URL(value);
        if (!/^https?:\/\//i.test(value) || !url.hostname) throw new Error();
      } catch {
        fieldErrors.value[field.name] = ['Enter a complete http:// or https:// URL.'];
      }
    } else if (value && field.minlength && value.length < field.minlength) {
      fieldErrors.value[field.name] = [`Use at least ${field.minlength} characters, or leave blank to keep the stored key.`];
    }
    // Omit empty replacements, immutable identity and default settings.
    if (value) payload[field.name] = value;
  }
  if (Object.keys(fieldErrors.value).length) {
    saveError.value = 'Check the account details below, then try again.';
    await nextTick();
    dialog.value?.querySelector('[aria-invalid="true"]')?.focus();
    return;
  }
  saving.value = true;
  try {
    await store.updateCredential(props.credential.id, payload);
    emit('save'); // Notification only. The parent must not update again.
  } catch (error) {
    // updateCredential refreshes after PUT. A failed refresh must not invite
    // another PUT for changes that were already saved.
    if (error.config?.method?.toLowerCase() === 'get' && error.config?.url === '/apiv/_1/storage-credentials') {
      emit('save', { refreshFailed: true });
      return;
    }
    const errors = error.response?.data?.errors;
    if (errors && typeof errors === 'object') {
      fieldErrors.value = Object.fromEntries(Object.entries(errors)
        .filter(([name]) => providerFields.value.some(field => field.name === name))
        .map(([name, messages]) => [name, Array.isArray(messages) ? messages : [String(messages)]]));
    }
    saveError.value = Object.keys(fieldErrors.value).length
      ? 'Check the account details below, then try again.' : 'Could not update this account. Try again.';
  } finally {
    saving.value = false;
    if (saveError.value) {
      await nextTick();
      (dialog.value?.querySelector('[aria-invalid="true"]') || dialog.value?.querySelector('.account-error'))?.focus();
    }
  }
}
</script>

<style scoped>
.account-dialog {
  width: calc(100% - 2rem); max-width: 38rem; max-height: calc(100vh - 2rem);
  max-height: calc(100dvh - 2rem); margin: auto; padding: 0;
  border: 1px solid #c4cfda; border-radius: .5rem; color: #263445; background: #fff;
  box-shadow: 0 1rem 3rem rgba(38, 52, 69, .18); overflow: hidden;
}
.account-dialog > form { display: flex; flex-direction: column; max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); }
.account-dialog-header, .account-dialog-footer { flex-shrink: 0; }
.account-dialog::backdrop { background: rgba(38, 52, 69, .48); }
.account-dialog-header { display: flex; align-items: flex-start; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid #e0e6ec; }
.account-dialog-header h2 { font-size: 1.25rem; font-weight: 700; margin: 0 0 .375rem; }
.account-dialog-header p { margin: 0; color: #536475; line-height: 1.5; }
.account-close { margin-left: auto; flex: 0 0 2.5rem; height: 2.5rem; border: 0; border-radius: .25rem; background: #f1f4f7; color: #263445; font-size: 1.5rem; }
.account-dialog-body { padding: 1rem 1.25rem; min-height: 0; overflow-y: auto; }
.account-summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; margin: 0 0 .625rem; }
.account-summary dt { font-size: .8125rem; font-weight: 400; color: #536475; margin-bottom: .25rem; }
.account-summary dd { margin: 0; font-weight: 700; overflow-wrap: anywhere; }
.account-fields { min-width: 0; padding: 0; margin: 0; border: 0; }
.account-fields legend { font-size: .9375rem; font-weight: 700; margin: 0 0 .625rem; }
.connection-fields { margin-top: 1.25rem; }
.account-field + .account-field { margin-top: .875rem; }
.account-field label { margin-bottom: .375rem; font-weight: 600; }
.field-optional { font-size: .8125rem; font-weight: 400; color: #536475; margin-left: .25rem; }
.form-control { min-height: 2.625rem; color: #263445; border-color: #aab8c6; border-radius: .25rem; }
.form-control::placeholder { color: #617184; opacity: 1; }
.form-control[aria-invalid="true"] { border-color: #a52834; }
.secret-input { display: flex; }
.secret-input .form-control { min-width: 0; border-radius: .25rem 0 0 .25rem; }
.secret-toggle { min-width: 4rem; border: 1px solid #aab8c6; border-left: 0; border-radius: 0 .25rem .25rem 0; color: #263445; background: #f8fafc; }
.secret-toggle:hover, .account-close:hover, .account-cancel:hover { background: #e7edf3; }
.field-help { display: block; font-size: .8125rem; line-height: 1.5; color: #536475; margin-top: .25rem; }
.field-error { margin: .25rem 0 0; font-size: .875rem; color: #a52834; }
.account-error { padding: .75rem; margin-bottom: 1rem; border-left: 3px solid #a52834; background: #fff1f2; color: #82212a; }
.account-dialog-footer { display: flex; justify-content: flex-end; align-items: center; gap: .625rem; padding: .875rem 1.25rem; background: #f8fafc; border-top: 1px solid #e0e6ec; }
.save-status { margin-right: auto; font-size: .875rem; color: #536475; }
.account-cancel { color: #263445; background: #fff; border: 1px solid #aab8c6; }
.account-primary { color: #fff; background: #2474b4; border: 1px solid #2474b4; }
.account-primary:hover { color: #fff; background: #1c5d91; border-color: #1c5d91; }
.btn { min-height: 2.625rem; white-space: nowrap; }
button:disabled { cursor: not-allowed; }
button:focus-visible, input:focus-visible { outline: 3px solid #2474b4; outline-offset: 3px; box-shadow: none; }
.secret-input:focus-within { outline: 3px solid #2474b4; outline-offset: 3px; border-radius: .25rem; }
.secret-input .form-control:focus, .secret-input .form-control:focus-visible, .secret-toggle:focus, .secret-toggle:focus-visible { outline: 0; box-shadow: none; }
.secret-toggle:focus-visible { background: #e7edf3; text-decoration: underline; }
@media (max-width: 575px) {
  .account-dialog { width: calc(100% - 1rem); max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); }
  .account-dialog > form { max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); }
  .account-dialog-header, .account-dialog-body { padding: 1rem; }
  .account-dialog-footer { padding: .875rem 1rem; flex-wrap: wrap; }
  .account-summary { grid-template-columns: 1fr; gap: .5rem; }
  .save-status { flex-basis: 100%; }
}
</style>

<template>
  <dialog ref="dialog" class="account-dialog" aria-labelledby="add-account-title"
    aria-describedby="add-account-description" @cancel.prevent="requestClose" @keydown.tab="trapFocus">
    <form @submit.prevent="handleSave" :aria-busy="saving">
      <header class="account-dialog-header">
        <div>
          <h2 id="add-account-title">Add account</h2>
          <p id="add-account-description">Choose where to store your images, then add the account details.</p>
        </div>
        <button type="button" class="account-close" aria-label="Close add account" :disabled="saving" @click="requestClose">
          <span aria-hidden="true">&times;</span>
        </button>
      </header>

      <div class="account-dialog-body">
        <div v-if="saveError" class="account-error" role="alert">
          {{ saveError }}
        </div>

        <fieldset class="account-fields" :disabled="saving">
          <legend>Storage provider</legend>
          <div class="provider-options">
            <label v-for="provider in providers" :key="provider.value" class="provider-option"
              :class="{ 'is-selected': form.provider === provider.value }">
              <input type="radio" name="storage-provider" :id="`provider-${provider.value}`"
                :value="provider.value" v-model="form.provider" :autofocus="provider.value === 'supabase'">
              <span>
                <strong>{{ provider.label }}</strong>
                <small>{{ provider.description }}</small>
              </span>
            </label>
          </div>
        </fieldset>

        <fieldset class="account-fields connection-fields" :disabled="saving">
          <legend>{{ form.provider === 'supabase' ? 'Supabase project' : 'Vercel Blob store' }}</legend>
          <div v-for="field in providerFields" :key="field.name" class="account-field">
            <label :for="field.id">{{ field.label }} <span v-if="!field.required" class="field-optional">optional</span></label>
            <div :class="{ 'secret-input': field.secret }">
              <input :id="field.id" :name="field.name" v-model="form[field.name]" class="form-control"
                :type="field.secret && !visible[field.name] ? 'password' : field.type"
                :required="field.required" :minlength="field.minlength" :placeholder="field.placeholder"
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

        <label class="default-choice">
          <input type="checkbox" id="is-default" v-model="form.is_default" :disabled="saving">
          <span><strong>Use as default account</strong><small>Preselect this account when uploading. You can choose another each time.</small></span>
        </label>
      </div>

      <footer class="account-dialog-footer">
        <span v-if="saving" role="status" class="save-status">Saving account…</span>
        <button type="button" class="btn account-cancel" :disabled="saving" @click="requestClose">Cancel</button>
        <button type="submit" class="btn account-primary" :disabled="!canSave || saving">
          {{ saving ? 'Saving…' : 'Add account' }}
        </button>
      </footer>
    </form>
  </dialog>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useStorageCredentialsStore } from '../stores/storageCredentials';

const emit = defineEmits(['close', 'save']);
const store = useStorageCredentialsStore();
const dialog = ref(null);
let previousFocus;
const form = ref({
  provider: 'supabase', is_default: false,
  supabase_url: '', supabase_key: '', supabase_service_key: '', supabase_bucket: 'images',
  vercel_blob_token: '', vercel_blob_store_url: 'https://blob.vercel-storage.com'
});
const visible = ref({});
const saving = ref(false);
const saveError = ref('');
const fieldErrors = ref({});
const providers = [
  { value: 'supabase', label: 'Supabase', description: 'Project URL, keys and bucket.' },
  { value: 'vercel', label: 'Vercel Blob', description: 'Blob store read/write token.' }
];
const fields = {
  supabase: [
    { name: 'supabase_url', id: 'supabase-url', label: 'Project URL', type: 'url', required: true,
      placeholder: 'https://your-project.supabase.co', help: 'Find this in your Supabase project settings under API.' },
    { name: 'supabase_key', id: 'supabase-key', label: 'Public anon key', type: 'text', secret: true, required: true, minlength: 20,
      help: 'Use the public API key for this project.' },
    { name: 'supabase_service_key', id: 'supabase-service-key', label: 'Service role key', type: 'text', secret: true, minlength: 20,
      help: 'Optional to save. Required for uploads and signed image URLs.' },
    { name: 'supabase_bucket', id: 'supabase-bucket', label: 'Bucket name', type: 'text',
      placeholder: 'images', help: 'Use an existing bucket. Defaults to images if left blank.' }
  ],
  vercel: [
    { name: 'vercel_blob_token', id: 'vercel-token', label: 'Read/write token', type: 'text', secret: true, required: true, minlength: 30,
      help: 'Find the token in your Vercel project under Storage.' },
    { name: 'vercel_blob_store_url', id: 'vercel-store-url', label: 'Store URL', type: 'url',
      placeholder: 'https://blob.vercel-storage.com', help: 'Keep the default unless your account uses a different store URL.' }
  ]
};
const providerFields = computed(() => fields[form.value.provider]);
const canSave = computed(() => providerFields.value
  .filter(field => field.required).every(field => form.value[field.name].trim()));

watch(() => form.value.provider, () => {
  visible.value = {};
  fieldErrors.value = {};
  saveError.value = '';
});
onMounted(() => {
  previousFocus = document.activeElement;
  dialog.value.showModal();
});
onBeforeUnmount(() => {
  dialog.value?.close();
  if (previousFocus?.isConnected) previousFocus.focus();
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
  if (saving.value || !canSave.value) return;
  saving.value = true;
  saveError.value = '';
  fieldErrors.value = {};
  // Only the selected provider's fields belong in this request.
  const payload = { provider: form.value.provider, is_default: form.value.is_default };
  for (const field of providerFields.value) {
    const value = form.value[field.name].trim();
    if (value) payload[field.name] = value;
  }
  try {
    await store.createCredential(payload);
    emit('save'); // Notification only. The parent must not create again.
  } catch (error) {
    // The store refreshes after a successful POST. A failed GET must not invite
    // another POST for an account that was already saved.
    if (error.config?.method?.toLowerCase() === 'get' && error.config?.url === '/apiv/_1/storage-credentials') {
      emit('save');
      return;
    }
    const errors = error.response?.data?.errors;
    if (errors && typeof errors === 'object') {
      fieldErrors.value = Object.fromEntries(Object.entries(errors)
        .map(([field, messages]) => [field, Array.isArray(messages) ? messages : [String(messages)]]));
    }
    saveError.value = Object.keys(fieldErrors.value).length
      ? 'Check the account details below, then try again.' : 'Could not add this account. Try again.';
  } finally {
    saving.value = false;
    if (saveError.value) {
      await nextTick();
      dialog.value?.querySelector('[aria-invalid="true"]')?.focus();
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
.account-fields { min-width: 0; padding: 0; margin: 0; border: 0; }
.account-fields legend { font-size: .9375rem; font-weight: 700; margin: 0 0 .625rem; }
.provider-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
.provider-option { display: flex; align-items: flex-start; gap: .625rem; padding: .875rem; margin: 0; border: 1px solid #c4cfda; border-radius: .375rem; cursor: pointer; }
.provider-option input, .default-choice input { flex: 0 0 auto; margin-top: .25rem; accent-color: #2474b4; }
.provider-option strong, .default-choice strong { display: block; font-weight: 700; }
.provider-option small, .default-choice small { display: block; color: #536475; line-height: 1.5; margin-top: .125rem; font-size: .8125rem; }
.provider-option:hover { border-color: #2474b4; }
.provider-option.is-selected { border-color: #2474b4; background: #eff6fc; }
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
.default-choice { display: flex; align-items: flex-start; gap: .625rem; margin: 1.25rem 0 0; padding-top: 1rem; border-top: 1px solid #e0e6ec; cursor: pointer; }
.account-dialog-footer { display: flex; justify-content: flex-end; align-items: center; gap: .625rem; padding: .875rem 1.25rem; background: #f8fafc; border-top: 1px solid #e0e6ec; }
.save-status { margin-right: auto; font-size: .875rem; color: #536475; }
.account-cancel { color: #263445; background: #fff; border: 1px solid #aab8c6; }
.account-primary { color: #fff; background: #2474b4; border: 1px solid #2474b4; }
.account-primary:hover { color: #fff; background: #1c5d91; border-color: #1c5d91; }
.btn { min-height: 2.625rem; white-space: nowrap; }
button:disabled { cursor: not-allowed; }
button:focus-visible, input:focus-visible { outline: 3px solid #2474b4; outline-offset: 3px; box-shadow: none; }
.provider-option:focus-within { outline: 3px solid #2474b4; outline-offset: 3px; }
@media (max-width: 575px) {
  .account-dialog { width: calc(100% - 1rem); max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); }
  .account-dialog > form { max-height: calc(100vh - 1rem); max-height: calc(100dvh - 1rem); }
  .account-dialog-header, .account-dialog-body { padding: 1rem; }
  .account-dialog-footer { padding: .875rem 1rem; flex-wrap: wrap; }
  .provider-options { grid-template-columns: 1fr; gap: .5rem; }
  .save-status { flex-basis: 100%; }
}
</style>

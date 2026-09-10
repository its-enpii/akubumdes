<script setup>
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AppRichEditor from '../../Components/AppRichEditor.vue';
import AppModal from '../../Components/AppModal.vue';
import SignaturePad from '../../Components/SignaturePad.vue';
import AppTabs from '../../Components/AppTabs.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    identity: { type: Object, required: true },
    logoUrl: { type: String, default: null },
    whatsapp: { type: Object, required: true },
    signatures: { type: Object, required: true },
    signatureImages: { type: Object, default: () => ({}) },
    offline: { type: Object, default: () => ({ is_enabled: false, user_id: null, users: [] }) },
});

const page = usePage();
const route = computed(() => page.url);
const flash = computed(() => page.props.flash?.success);

const tabs = [
    { key: 'identity', label: 'Identitas Lembaga', icon: 'badge' },
    { key: 'logo', label: 'Logo Lembaga', icon: 'image' },
    { key: 'offline', label: 'Akses Offline', icon: 'cloud_off' },
    { key: 'signatures', label: 'Tanda Tangan', icon: 'draw' },
];

const activeTab = ref(getInitialTab());

function getInitialTab() {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) return tab;
    return flash.value?.tab ?? 'identity';
}

function go(tab) {
    router.get('/settings', { tab }, { preserveState: true, preserveScroll: true });
}

watch(() => route.value, () => {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) activeTab.value = tab;
});

// === Identity form ===
const identityForm = useForm({
    legal_name: props.identity.legal_name ?? '',
    short_name: props.identity.short_name ?? '',
    registration_number: props.identity.registration_number ?? '',
    tax_number: props.identity.tax_number ?? '',
    address: props.identity.address ?? '',
    phone: props.identity.phone ?? '',
    email: props.identity.email ?? '',
    website: props.identity.website ?? '',
    timezone: props.identity.timezone ?? 'Asia/Jakarta',
    operational_start_date: props.identity.operational_start_date ?? '',
});
function submitIdentity() {
    identityForm.put('/settings/identity', { preserveScroll: true });
}

// === Logo ===
const logoForm = useForm({ logo: null });
const logoPreview = ref(props.logoUrl);
const logoDragOver = ref(false);
function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    setLogoFile(file);
}
function onLogoDrop(event) {
    event.preventDefault();
    logoDragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setLogoFile(file);
}
function setLogoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}
function submitLogo() {
    logoForm.post('/settings/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => logoForm.reset(),
    });
}
const { confirm: confirmAction } = useConfirm();
const toast = useToast();
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function destroyLogo() {
    if (!await confirmAction({ title: 'Hapus Logo', message: 'Hapus logo organisasi?' })) return;
    router.delete('/settings/logo', { preserveScroll: true });
}

// === Offline Access ===
const offlineForm = useForm({
    is_enabled: props.offline.is_enabled ?? false,
    user_id: props.offline.user_id ?? null,
});

const offlineUserOptions = computed(() => (props.offline.users ?? []).map((u) => ({
    value: u.row_id,
    label: u.name + (u.username ? ` (${u.username})` : ''),
})));

const outbox = ref({ pending: 0, failed: 0, synced: 0 });
async function loadOutbox() {
    try {
        const response = await fetch('/desktop/sync/status', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        outbox.value = data.outbox ?? outbox.value;
    } catch {
        outbox.value = { pending: 0, failed: 0, synced: 0 };
    }
}
onMounted(loadOutbox);

function submitOffline() {
    if (offlineForm.is_enabled && !offlineForm.user_id) {
        alert('Pilih satu pengguna offline terlebih dahulu.');
        return;
    }
    offlineForm.put('/settings/offline-access', { preserveScroll: true });
}
// === Signatures ===
const signatureReportKey = ref(props.signatures.reportTypes?.[0]?.key ?? 'default');
const signatureDrafts = ref({ ...(props.signatures.templates ?? {}) });
const signatureForm = useForm({ templates: {} });
const signatureReportOptions = computed(() =>
    (props.signatures.reportTypes ?? []).map((t) => ({ value: t.key, label: t.label })),
);
const currentSignatureHtml = computed({
    get: () => signatureDrafts.value[signatureReportKey.value] ?? '',
    set: (val) => {
        signatureDrafts.value = {
            ...signatureDrafts.value,
            [signatureReportKey.value]: val ?? '',
        };
    },
});
function submitSignatures() {
    signatureForm.templates = { ...signatureDrafts.value };
    signatureForm.put('/settings/signatures', { preserveScroll: true });
}
const showSignaturePad = ref(false);
const signatureImagePad = ref(null);
const signatureImageForm = useForm({ report_key: '', image: '' });
const signatureDeleteForm = useForm({ report_key: '' });

const currentSignatureImageUrl = computed(() => props.signatureImages?.[signatureReportKey.value] ?? null);

function openSignaturePad() {
    signatureImageForm.report_key = signatureReportKey.value;
    signatureImageForm.image = '';
    showSignaturePad.value = true;
}

async function saveSignatureImage(dataUrl) {
    if (!dataUrl) return;
    signatureImageForm.image = dataUrl;
    try {
        await signatureImageForm.post('/settings/signatures/image', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showSignaturePad.value = false;
                signatureImageForm.reset();
            },
        });
    } catch (error) {
        console.error('Gagal menyimpan tanda tangan:', error);
    }
}

async function removeSignatureImage() {
    if (!currentSignatureImageUrl.value) return;
    const confirmed = await confirmAction({
        title: 'Hapus Tanda Tangan',
        message: 'Hapus tanda tangan digital untuk jenis dokumen ini?',
    });
    if (!confirmed) return;

    signatureDeleteForm.report_key = signatureReportKey.value;
    router.delete('/settings/signatures/image', {
        data: { report_key: signatureDeleteForm.report_key },
        preserveScroll: true,
        onSuccess: () => signatureDeleteForm.reset(),
    });
}

async function handleUploadSignatureImage(event) {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        signatureImageForm.report_key = signatureReportKey.value;
        signatureImageForm.image = String(reader.result ?? '');
        signatureImageForm.post('/settings/signatures/image', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => signatureImageForm.reset(),
        });
    };
    reader.readAsDataURL(file);

    event.target.value = '';
}

function applySignatureStarter() {
    currentSignatureHtml.value = `<table style="width:100%"><tbody><tr><td style="width:33%;text-align:center"><p>Mengetahui,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Dibuat oleh,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Bendahara,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td></tr></tbody></table>`;
}
</script>

<template>
    <Head title="Pengaturan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Pengaturan</h1>
                <p class="mt-1 text-on-surface-variant">Konfigurasi lembaga, pinjaman, logo, WhatsApp, dan tanda tangan.</p>
            </header>


            <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
                <div class="rounded-xl bg-surface-container-low p-2 lg:sticky lg:top-20 lg:self-start">
                    <AppTabs
                        v-model="activeTab"
                        :items="tabs"
                        variant="pill"
                        aria-label="Tab pengaturan"
                        @update:model-value="go($event)"
                    />
                </div>

                <div class="space-y-6">
                    <AppCard v-show="activeTab === 'identity'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Identitas Lembaga</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Profil hukum dan kontak lembaga. Data ini muncul pada laporan dan dokumen resmi.</p>
                        <form class="space-y-5" @submit.prevent="submitIdentity">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput v-model="identityForm.legal_name" label="Nama Legal" required :error="identityForm.errors.legal_name" />
                                <AppInput v-model="identityForm.short_name" label="Nama Pendek" :error="identityForm.errors.short_name" />
                                <AppInput v-model="identityForm.registration_number" label="Nomor Badan Hukum" :error="identityForm.errors.registration_number" />
                                <AppInput v-model="identityForm.tax_number" label="NPWP" :error="identityForm.errors.tax_number" />
                                <AppInput v-model="identityForm.phone" label="Telepon" :error="identityForm.errors.phone" />
                                <AppInput v-model="identityForm.email" label="Email" type="email" :error="identityForm.errors.email" />
                                <AppInput v-model="identityForm.website" label="Website" type="url" :error="identityForm.errors.website" class="sm:col-span-2" />
                                <AppTextarea v-model="identityForm.address" label="Alamat" :rows="3" :error="identityForm.errors.address" class="sm:col-span-2" />
                                <AppInput v-model="identityForm.timezone" label="Zona Waktu" required :error="identityForm.errors.timezone" />
                                <AppDatePicker v-model="identityForm.operational_start_date" label="Tanggal Operasional Mulai" :error="identityForm.errors.operational_start_date" />
                            </div>
                            <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="submit" :loading="identityForm.processing" :disabled="identityForm.processing" icon="save">Simpan Identitas</AppButton>
                            </div>
                        </form>
                    </AppCard>


                    <AppCard v-show="activeTab === 'logo'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Logo Lembaga</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Logo akan tampil di sidebar aplikasi. Format: PNG, JPG, atau WebP. Maks 2 MB.</p>
                        <div class="flex flex-col items-center gap-5">
                            <div class="grid size-40 place-items-center overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest">
                                <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="size-full object-contain" />
                                <AppIcon v-else name="image" class="text-5xl text-on-surface-variant" />
                            </div>
                            <form class="w-full space-y-3" @submit.prevent="submitLogo">
                                <label
                                    class="block cursor-pointer rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-lowest p-6 text-center transition-colors hover:border-primary hover:bg-surface-container-low"
                                    :class="logoDragOver ? 'border-primary bg-primary-container/20' : ''"
                                    @dragover.prevent="logoDragOver = true"
                                    @dragleave="logoDragOver = false"
                                    @drop="onLogoDrop"
                                >
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange" />
                                    <AppIcon name="upload" class="text-2xl text-on-surface-variant" />
                                    <p class="mt-2 text-sm font-bold text-primary">Tarik gambar ke sini atau klik untuk pilih</p>
                                    <p class="mt-1 text-xs text-on-surface-variant">PNG / JPG / WebP · Maks 2 MB</p>
                                </label>
                                <p v-if="logoForm.errors.logo" class="text-sm text-error">{{ logoForm.errors.logo }}</p>
                                <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                                    <AppButton v-if="props.logoUrl" type="button" variant="danger" icon="delete" :loading="logoForm.processing" @click="destroyLogo">Hapus Logo</AppButton>
                                    <AppButton type="submit" :loading="logoForm.processing" :disabled="!logoForm.logo || logoForm.processing" icon="upload">Unggah Logo</AppButton>
                                </div>
                            </form>
                        </div>
                    </AppCard>

                    <AppCard v-show="activeTab === 'signatures'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Tanda Tangan</h2>
                        <p class="mb-4 text-sm text-on-surface-variant">
                            Blok penandatangan per jenis laporan. Disimpan per lembaga.
                        </p>
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                            <AppIcon name="info" class="mt-0.5 text-primary" />
                            <p class="text-sm text-on-surface-variant">
                                Tanda tangan gambar akan otomatis disisipkan ke PDF melalui placeholder
                                <code class="rounded bg-surface-container px-1.5 py-0.5 font-mono text-xs">{ttd_image}</code>
                                atau langsung ke baris kosong pertama pada template.
                            </p>
                        </div>

                        <div class="mb-6 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                            <div class="mb-3 flex items-center justify-between gap-4">
                                <h3 class="text-base font-bold text-primary">Tanda Tangan Digital</h3>
                                <div class="flex flex-wrap items-center gap-2">
                                    <AppButton
                                        type="button"
                                        variant="secondary"
                                        size="compact"
                                        icon="draw"
                                        @click="openSignaturePad"
                                    >
                                        Gambar Tanda Tangan
                                    </AppButton>
                                    <label>
                                        <input
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            class="sr-only"
                                            @change="handleUploadSignatureImage"
                                        />
                                        <AppButton
                                            variant="secondary"
                                            size="compact"
                                            icon="upload"
                                            @click="event => event.currentTarget.closest('label')?.querySelector('input')?.click()"
                                        >
                                            Unggah Gambar
                                        </AppButton>
                                    </label>
                                    <AppButton
                                        v-if="currentSignatureImageUrl"
                                        type="button"
                                        variant="danger"
                                        size="compact"
                                        icon="delete"
                                        :loading="signatureDeleteForm.processing"
                                        @click="removeSignatureImage"
                                    >
                                        Hapus
                                    </AppButton>
                                </div>
                            </div>

                            <div v-if="currentSignatureImageUrl" class="flex justify-center rounded-lg bg-white p-4">
                                <img :src="currentSignatureImageUrl" alt="Tanda Tangan Digital" class="max-h-32 object-contain" />
                            </div>
                            <p v-else class="py-6 text-center text-sm text-on-surface-variant">
                                Belum ada tanda tangan digital untuk jenis dokumen ini.
                            </p>
                            <p v-if="signatureImageForm.errors.image" class="mt-2 text-sm text-error">
                                {{ signatureImageForm.errors.image }}
                            </p>
                            <p v-if="signatureDeleteForm.errors.report_key" class="mt-2 text-sm text-error">
                                {{ signatureDeleteForm.errors.report_key }}
                            </p>
                        </div>
                        <form class="space-y-5" @submit.prevent="submitSignatures">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <SmartSelect
                                        v-model="signatureReportKey"
                                        label="Jenis Laporan"
                                        :options="signatureReportOptions"
                                        required
                                    />
                                </div>
                                <AppButton
                                    type="button"
                                    variant="secondary"
                                    size="large"
                                    class="h-14 shrink-0"
                                    icon="table"
                                    @click="applySignatureStarter"
                                >
                                    Isi Template 1×3
                                </AppButton>
                            </div>
                            <AppRichEditor
                                :key="signatureReportKey"
                                v-model="currentSignatureHtml"
                                placeholder="Sisipkan tabel penandatangan (ikon tabel di toolbar)…"
                            />
                            <p v-if="signatureForm.errors.templates" class="text-sm text-error">
                                {{ signatureForm.errors.templates }}
                            </p>
                            <div class="flex justify-end border-t border-outline-variant pt-4">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="signatureForm.processing"
                                    :disabled="signatureForm.processing"
                                >
                                    Simpan Tanda Tangan
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <AppModal v-model="showSignaturePad" title="Gambar Tanda Tangan" size="md">
                        <SignaturePad ref="signatureImagePad" @save="saveSignatureImage" @cancel="showSignaturePad = false" />
                    </AppModal>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

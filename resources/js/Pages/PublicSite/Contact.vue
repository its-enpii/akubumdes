<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppInput from '../../Components/AppInput.vue';
import AppTextarea from '../../Components/AppTextarea.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    settings: { type: Object, default: () => ({}) },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    website: '', // honeypot — hidden via CSS
});

function submit() {
    form.post(route('public.contact.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'phone', 'subject', 'message', 'website'),
    });
}

const orgName = computed(() => props.organization?.name ?? 'Desa');
const address = computed(() => props.settings?.contact_address ?? props.organization?.address ?? null);
const phone = computed(() => props.settings?.contact_phone ?? props.organization?.phone ?? null);
const email = computed(() => props.settings?.contact_email ?? props.organization?.email ?? null);
const social = computed(() => props.settings?.social ?? {});
</script>

<template>
    <Head :title="`Kontak — ${orgName}`">
        <meta head-key="description" name="description" :content="`Hubungi ${orgName} — alamat, telepon, dan formulir pesan.`" />
        <meta head-key="og:title" property="og:title" :content="`Kontak — ${orgName}`" />
        <meta head-key="og:description" property="og:description" :content="`Hubungi ${orgName} — alamat, telepon, dan formulir pesan.`" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
    </Head>

    <div class="min-h-screen bg-surface">
        <header class="sticky top-0 z-10 border-b border-outline-variant bg-surface/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <a href="/" class="flex items-center gap-3">
                    <img v-if="organization.logo_url" :src="organization.logo_url" :alt="orgName" class="size-9 rounded-lg object-contain" />
                    <span class="font-extrabold tracking-tight text-primary">{{ orgName }}</span>
                </a>
                <nav class="flex items-center gap-5 text-sm font-semibold">
                    <a href="/" class="text-on-surface-variant hover:text-primary">Beranda</a>
                    <a href="/berita" class="text-on-surface-variant hover:text-primary">Berita</a>
                    <a href="/kontak" class="text-primary">Kontak</a>
                    <a href="/login" class="rounded-full bg-primary px-4 py-2 text-on-primary hover:bg-primary/90">Masuk Sistem</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
            <div class="grid gap-8 lg:grid-cols-5">
                <!-- Info -->
                <div class="space-y-6 lg:col-span-2">
                    <div>
                        <h1 class="text-3xl font-extrabold tracking-tight text-primary">Kontak Kami</h1>
                        <p class="mt-2 text-sm leading-relaxed text-on-surface-variant">
                            Silakan hubungi kami melalui alamat, telepon, email, atau formulir di samping.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div v-if="address" class="flex gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                            <span class="material-symbols-rounded text-primary">place</span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-primary">Alamat</p>
                                <p class="mt-1 text-sm text-on-surface-variant">{{ address }}</p>
                            </div>
                        </div>
                        <div v-if="phone" class="flex gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                            <span class="material-symbols-rounded text-primary">call</span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-primary">Telepon</p>
                                <a :href="`tel:${phone}`" class="mt-1 block text-sm font-semibold text-primary hover:underline">{{ phone }}</a>
                            </div>
                        </div>
                        <div v-if="email" class="flex gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                            <span class="material-symbols-rounded text-primary">mail</span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-primary">Email</p>
                                <a :href="`mailto:${email}`" class="mt-1 block text-sm font-semibold text-primary hover:underline">{{ email }}</a>
                            </div>
                        </div>
                    </div>

                    <div v-if="social.facebook || social.instagram || social.youtube" class="flex gap-2">
                        <a v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary text-on-primary hover:bg-primary/90" aria-label="Facebook">f</a>
                        <a v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary text-on-primary hover:bg-primary/90" aria-label="Instagram">◎</a>
                        <a v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener" class="grid size-9 place-items-center rounded-full bg-primary text-on-primary hover:bg-primary/90" aria-label="YouTube">▶</a>
                    </div>
                </div>

                <!-- Form -->
                <div class="lg:col-span-3">
                    <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-6 shadow-sm sm:p-7">
                        <div v-if="flashSuccess" class="mb-4 rounded-xl bg-success-container px-4 py-3 text-sm font-medium text-on-success-container">{{ flashSuccess }}</div>
                        <div v-if="flashError" class="mb-4 rounded-xl bg-error-container px-4 py-3 text-sm font-medium text-on-error-container">{{ flashError }}</div>

                        <form class="space-y-4" @submit.prevent="submit">
                            <!-- honeypot: visually hidden, not display:none so bots still fill it -->
                            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                                <label for="website">Website</label>
                                <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <AppInput v-model="form.name" label="Nama" required maxlength="120" placeholder="Nama lengkap" :error="form.errors.name" />
                                    <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <AppInput v-model="form.email" type="email" label="Email" maxlength="255" placeholder="nama@email.com" :error="form.errors.email" />
                                    <p v-if="form.errors.email" class="mt-1 text-xs text-error">{{ form.errors.email }}</p>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <AppInput v-model="form.phone" label="Telepon" maxlength="40" placeholder="08xx-xxxx-xxxx" :error="form.errors.phone" />
                                    <p v-if="form.errors.phone" class="mt-1 text-xs text-error">{{ form.errors.phone }}</p>
                                </div>
                                <div>
                                    <AppInput v-model="form.subject" label="Subjek" maxlength="200" placeholder="Perihal pesan" :error="form.errors.subject" />
                                    <p v-if="form.errors.subject" class="mt-1 text-xs text-error">{{ form.errors.subject }}</p>
                                </div>
                            </div>

                            <div>
                                <AppTextarea v-model="form.message" label="Pesan" required rows="5" maxlength="5000" placeholder="Tuliskan pesan Anda di sini..." :error="form.errors.message" />
                                <p v-if="form.errors.message" class="mt-1 text-xs text-error">{{ form.errors.message }}</p>
                            </div>

                            <div class="flex justify-end pt-2">
                                <AppButton type="submit" :disabled="form.processing" :loading="form.processing">Kirim Pesan</AppButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <footer class="border-t border-outline-variant py-6 text-center text-xs text-on-surface-variant">
            <p v-if="settings.footer_note">{{ settings.footer_note }}</p>
            <p v-else>© {{ new Date().getFullYear() }} {{ orgName }}</p>
        </footer>
    </div>
</template>

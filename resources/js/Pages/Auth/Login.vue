<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Masuk - akubumdes" />

    <main class="flex min-h-screen bg-surface font-sans text-on-surface">
        <section class="bg-navy-gradient relative hidden w-[55%] flex-col overflow-hidden p-12 lg:flex" aria-label="akubumdes — BUMDesma/LKD">
            <div class="pointer-events-none absolute right-0 top-1/4 h-80 w-80 border-l border-t border-white/10" aria-hidden="true" />
            <div class="pointer-events-none absolute bottom-24 left-10 h-24 w-1 bg-secondary" aria-hidden="true" />

            <div class="relative z-10 flex items-center gap-4">
                <span class="grid size-14 place-items-center rounded-lg bg-white text-primary" aria-hidden="true">
                    <svg class="size-8" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="24" cy="24" r="20" stroke="#0f766e" stroke-width="2.5" />
                        <path d="M32.5 15.5c-1.8 10.4-7.2 15.4-15.8 17.2 7.8-3.6 11.7-9 13.2-14.6-4.2 1-7.2 3.6-9.4 7.4" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <div>
                    <p class="font-display text-xl font-bold tracking-[-0.02em] text-white">akubumdes</p>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-on-primary-container/90">BUMDesma/LKD Financial Management</p>
                </div>
            </div>

            <div class="relative z-10 mx-auto my-auto max-w-lg">
                <svg class="mx-auto size-44" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="100" cy="100" r="76" stroke="white" stroke-opacity="0.16" stroke-width="8" />
                    <path d="M136 64c-6 38-26 58-62 66 30-14 45-34 51-56" stroke="#5eead4" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                    <rect x="62" y="118" width="12" height="34" rx="3" fill="white" fill-opacity="0.32" />
                    <rect x="88" y="98" width="12" height="54" rx="3" fill="#34d399" />
                    <rect x="114" y="80" width="12" height="72" rx="3" fill="white" />
                </svg>
                <h2 class="mt-8 font-display text-3xl font-semibold leading-snug tracking-[-0.02em] text-white">Transformasi Digital Ekonomi Desa</h2>
                <p class="mt-4 max-w-md text-lg text-on-primary-container/90">
                    Mewujudkan kemandirian finansial masyarakat melalui pengelolaan dana bergulir yang transparan dan akuntabel.
                </p>
            </div>

            <footer class="relative z-10 mt-auto flex flex-col gap-4 border-t border-white/10 pt-8 sm:flex-row sm:items-center sm:justify-between">
                <p class="max-w-sm text-base font-medium italic text-white">&ldquo;Mengelola Dana Desa, Membangun Kesejahteraan Bersama&rdquo;</p>
                <div class="flex items-center gap-2" aria-hidden="true">
                    <span class="size-2 rounded-full bg-secondary" />
                    <span class="size-2 rounded-full bg-white/20" />
                    <span class="size-2 rounded-full bg-white/20" />
                </div>
            </footer>
        </section>

        <section class="flex w-full flex-col justify-center bg-surface px-6 py-10 md:p-12 lg:w-[45%]">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-10 flex flex-col items-center text-center lg:hidden">
                    <span class="grid size-14 place-items-center rounded-lg bg-primary-container text-on-primary" aria-hidden="true">
                        <AppIcon name="account_balance" class="text-3xl" />
                    </span>
                    <p class="mt-3 font-display text-2xl font-bold tracking-[-0.02em] text-primary">akubumdes</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-on-surface-variant">BUMDesma/LKD</p>
                </div>

                <header class="mb-10">
                    <p class="eyebrow">Sistem Informasi Dana Bergulir Masyarakat</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-[-0.02em] text-on-surface">Masuk ke Akun Anda</h1>
                    <p class="mt-2 text-base text-on-surface-variant">Kelola keuangan BUMDesma/LKD Anda dari satu tempat.</p>
                </header>

                <div v-if="form.hasErrors" class="mb-6" role="alert" aria-live="assertive">
                    <AppBadge tone="error">{{ form.errors.identifier || form.errors.password || 'Kredensial tidak sesuai. Silakan coba lagi.' }}</AppBadge>
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <AppInput
                        v-model="form.identifier"
                        label="Username atau Email"
                        icon="person"
                        autocomplete="username"
                        placeholder="Contoh: admin_desa"
                        required
                        autofocus
                        :error="form.errors.identifier"
                    />

                    <AppInput
                        v-model="form.password"
                        label="Kata Sandi"
                        icon="lock"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        required
                        :error="form.errors.password"
                    >
                        <template #trailing>
                            <AppIconButton
                                :name="showPassword ? 'visibility_off' : 'visibility'"
                                size="sm"
                                tone="neutral"
                                rounded="lg"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                @click="showPassword = !showPassword"
                            />
                        </template>
                    </AppInput>

                    <div class="flex items-center justify-between text-sm">
                        <AppCheckbox v-model="form.remember" variant="inline" label="Ingat saya" />
                        <Link :href="route('password.request')" class="font-bold text-primary hover:underline">
                            Lupa password?
                        </Link>
                    </div>

                    <AppButton
                        type="submit"
                        variant="success"
                        size="large"
                        class="w-full"
                        :loading="form.processing"
                        icon="login"
                    >
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </AppButton>
                </form>

                <div class="mt-8 rounded-lg border border-outline-variant bg-surface-container-lowest p-4 text-sm leading-relaxed text-on-surface-variant">
                    <p class="font-semibold text-primary">Butuh bantuan akses?</p>
                    <p class="mt-1">Hubungi Administrator Utama BUMDesma atau Dinas PMD setempat.</p>
                </div>
            </div>
        </section>
    </main>
</template>

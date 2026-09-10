<script setup>
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AppBadge from "../../../Components/AppBadge.vue";
import AppButton from "../../../Components/AppButton.vue";
import AppCard from "../../../Components/AppCard.vue";
import AppEmptyState from "../../../Components/AppEmptyState.vue";
import AppInput from "../../../Components/AppInput.vue";
import AppModal from "../../../Components/AppModal.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import AuthenticatedLayout from "../../../Layouts/AuthenticatedLayout.vue";
import { useCan } from "../../../composables/useCan";
import { useConfirm } from "../../../composables/useConfirm";

const props = defineProps({
  messages: { type: Object, required: true },
  search: { type: String, default: "" },
  unreadCount: { type: Number, default: 0 },
});

const page = usePage();
const { can } = useCan();
const { confirm } = useConfirm();

const canManage = computed(() => can("website.manage"));
const q = ref(props.search);
const detail = ref(null);
const showDetail = ref(false);

const messageColumns = [
  { key: "name", label: "Pengirim" },
  { key: "subject", label: "Subjek" },
  { key: "status", label: "Status" },
  { key: "created_at", label: "Tanggal" },
  { key: "actions", label: "Aksi", align: "right" },
];

function formatDateTime(value) {
  return value ? new Date(value).toLocaleString("id-ID") : "—";
}

function applySearch() {
  router.get(
    route("website.messages.index"),
    { q: q.value || undefined },
    { preserveState: true, preserveScroll: true },
  );
}

function openDetail(row) {
  detail.value = row;
  showDetail.value = true;
  if (!row.is_read && canManage.value) {
    router.post(
      route("website.messages.read", row.row_id),
      {},
      { preserveScroll: true },
    );
    row.is_read = true;
  }
}

function remove(row) {
  confirm({
    title: "Hapus pesan?",
    message: `Pesan dari "${row.name}" akan dihapus permanen.`,
    confirmText: "Hapus",
    variant: "danger",
    onConfirm: () =>
      router.delete(route("website.messages.destroy", row.row_id)),
  });
}

function markRead(row) {
  router.post(
    route("website.messages.read", row.row_id),
    {},
    { preserveScroll: true },
  );
}
</script>

<template>
  <Head title="Pesan Masuk" />

  <AuthenticatedLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">Pesan Masuk</h1>
          <p class="mt-1 text-on-surface-variant">
            Pesan dari formulir kontak publik
            <span
              v-if="unreadCount"
              class="ml-2 inline-flex items-center rounded-full bg-warning-container px-2 py-0.5 text-xs font-bold text-on-warning-container"
              >{{ unreadCount }} belum dibaca</span
            >
          </p>
        </div>
      </header>
      <AppCard :padded="false">
        <div class="p-6">
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex min-w-[16rem] flex-1 items-center gap-2">
              <AppInput
                v-model="q"
                type="search"
                label="Cari pesan"
                hide-label
                placeholder="Cari nama / subjek / isi..."
                @keydown.enter="applySearch"
              />
              <AppButton
                variant="secondary"
                size="compact"
                icon="search"
                @click="applySearch"
                >Cari</AppButton
              >
            </div>
          </div>

          <div v-if="messages.data.length === 0" class="py-8">
            <AppEmptyState
              icon="inbox"
              title="Belum ada pesan"
              description="Pesan dari halaman kontak publik akan tampil di sini."
            />
          </div>

          <div v-else class="mt-5">
            <ReportTable
              :columns="messageColumns"
              :rows="messages.data"
              row-key="row_id"
              size="compact"
            >
              <template #cell-name="{ row }">
                <p class="font-semibold text-primary">{{ row.name }}</p>
                <p
                  v-if="row.email || row.phone"
                  class="text-xs text-on-surface-variant"
                >
                  {{ [row.email, row.phone].filter(Boolean).join(" · ") }}
                </p>
              </template>
              <template #cell-subject="{ row }">
                <p class="max-w-[20rem] truncate font-medium">
                  {{ row.subject || "—" }}
                </p>
                <p
                  class="max-w-[20rem] truncate text-xs text-on-surface-variant"
                >
                  {{ row.message }}
                </p>
              </template>
              <template #cell-status="{ row }">
                <AppBadge :tone="row.is_read ? 'neutral' : 'warning'">{{
                  row.is_read ? "Sudah dibaca" : "Baru"
                }}</AppBadge>
              </template>
              <template #cell-created_at="{ row }"
                ><span class="text-xs text-on-surface-variant">{{
                  formatDateTime(row.created_at)
                }}</span></template
              >
              <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1.5">
                  <AppButton
                    variant="outline"
                    size="compact"
                    icon="visibility"
                    @click="openDetail(row)"
                    >Lihat</AppButton
                  >
                  <AppButton
                    v-if="!row.is_read && canManage"
                    variant="secondary"
                    size="compact"
                    icon="mark_email_read"
                    @click="markRead(row)"
                    >Tandai dibaca</AppButton
                  >
                  <AppButton
                    v-if="canManage"
                    variant="danger"
                    size="compact"
                    icon="delete"
                    @click="remove(row)"
                    >Hapus</AppButton
                  >
                </div>
              </template>
            </ReportTable>
          </div>

          <div
            v-if="messages.links?.length > 3"
            class="mt-4 flex flex-wrap gap-1.5"
          >
            <Link
              v-for="link in messages.links"
              :key="link.label"
              :href="link.url ?? '#'"
              :class="[
                'rounded-lg px-3 py-1.5 text-sm',
                link.active
                  ? 'bg-primary text-on-primary'
                  : 'border border-outline-variant hover:bg-surface-container-low',
                !link.url && 'pointer-events-none opacity-40',
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </AppCard>

      <AppModal
        v-model="showDetail"
        :title="detail ? `Pesan dari ${detail.name}` : 'Detail pesan'"
        size="md"
      >
        <div v-if="detail" class="space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <p
                class="font-bold uppercase tracking-wider text-on-surface-variant"
              >
                Email
              </p>
              <p class="mt-1 text-primary">{{ detail.email || "—" }}</p>
            </div>
            <div>
              <p
                class="font-bold uppercase tracking-wider text-on-surface-variant"
              >
                Telepon
              </p>
              <p class="mt-1 text-primary">{{ detail.phone || "—" }}</p>
            </div>
          </div>
          <div v-if="detail.subject">
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Subjek
            </p>
            <p class="mt-1 font-semibold text-primary">{{ detail.subject }}</p>
          </div>
          <div>
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Pesan
            </p>
            <p class="mt-1 whitespace-pre-wrap leading-relaxed text-on-surface">
              {{ detail.message }}
            </p>
          </div>
          <p class="text-xs text-on-surface-variant">
            {{
              detail.created_at
                ? new Date(detail.created_at).toLocaleString("id-ID")
                : ""
            }}
          </p>
        </div>
        <template #footer>
          <AppButton variant="secondary" @click="showDetail = false"
            >Tutup</AppButton
          >
        </template>
      </AppModal>
    </div>
  </AuthenticatedLayout>
</template>

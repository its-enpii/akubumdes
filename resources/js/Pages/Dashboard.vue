<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AppBadge from "../Components/AppBadge.vue";
import AppButton from "../Components/AppButton.vue";
import AppCard from "../Components/AppCard.vue";
import AppEmptyState from "../Components/AppEmptyState.vue";
import AppIcon from "../Components/AppIcon.vue";
import ReportTable from "../Components/Reports/ReportTable.vue";
import { useMoney } from "../composables/useMoney";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  unitName: { type: String, default: null },
  as_of: { type: String, required: true },
  cards: { type: Array, required: true },
  recent_journals: { type: Array, required: true },
  counts: { type: Object, required: true },
});

const { money } = useMoney();
const numberFmt = new Intl.NumberFormat("id-ID");

function formatValue(card) {
  if (card.format === "money")
    return money.format(Math.round(Number(card.value || 0)));
  return numberFmt.format(Number(card.value || 0));
}

function formatMoney(value) {
  return money(Math.round(Number(value || 0)));
}

function formatDate(value) {
  if (!value) return "—";
  const d = new Date(`${value}T00:00:00`);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

const quickActions = [
  {
    label: "Jurnal Umum",
    href: "/accounting/journal-entries/create",
    icon: "receipt_long",
  },
  { label: "E-Budgeting", href: "/budgeting", icon: "account_balance_wallet" },
];

const sourceLabel = {
  manual: "Manual",
  general: "Umum",
};

const journalColumns = [
  { key: "transaction_date", label: "Tanggal" },
  { key: "description", label: "No / Uraian" },
  { key: "source", label: "Sumber" },
  { key: "amount", label: "Jumlah", align: "right" },
];
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout :unit-name="unitName">
    <div class="mx-auto max-w-7xl space-y-8">
      <section
        class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
      >
        <div>
          <p
            class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant"
          >
            Ringkasan operasional
          </p>
          <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">
            {{ unitName || "Dashboard" }}
          </h1>
          <p class="mt-1 text-on-surface-variant">
            Data live per {{ formatDate(as_of) }} · {{ counts.members }} anggota
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            v-for="action in quickActions"
            :key="action.href"
            :href="action.href"
          >
            <AppButton variant="secondary" size="compact" :icon="action.icon">{{
              action.label
            }}</AppButton>
          </Link>
        </div>
      </section>

      <section
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        aria-label="KPI utama"
      >
        <AppCard v-for="card in cards" :key="card.key" class="stat-card">
          <div class="mb-3 flex items-start justify-between gap-3">
            <div
              class="grid size-10 place-items-center rounded-lg"
              :class="
                card.tone === 'error'
                  ? 'bg-error-container text-on-error-container'
                  : 'bg-primary-fixed/40 text-primary'
              "
            >
              <AppIcon :name="card.icon" />
            </div>
            <AppBadge v-if="card.tone === 'error'" tone="error"
              >Perhatian</AppBadge
            >
          </div>
          <p
            class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant"
          >
            {{ card.label }}
          </p>
          <p
            class="mt-2 text-2xl font-bold"
            :class="card.tone === 'error' ? 'text-error' : 'text-primary'"
          >
            {{ formatValue(card) }}
          </p>
          <p v-if="card.hint" class="mt-1 text-xs text-on-surface-variant">
            {{ card.hint }}
          </p>
        </AppCard>
      </section>

      <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-2">
        <section
          class="card-shadow flex max-h-[28rem] min-h-0 flex-col rounded-xl bg-surface-container-lowest xl:col-span-2"
        >
          <header
            class="flex shrink-0 items-center justify-between border-b border-outline-variant px-6 py-4"
          >
            <div>
          <h2 class="accent-bar pl-4 text-lg font-bold text-primary">Jurnal Terbaru</h2>
              <p class="text-sm text-on-surface-variant">
                Posted, {{ recent_journals.length }} entri terakhir
              </p>
            </div>
            <Link href="/accounting/journal-entries/create">
              <AppButton variant="ghost" size="compact">Buat jurnal</AppButton>
            </Link>
          </header>

          <div
            v-if="recent_journals.length"
            class="min-h-0 flex-1 overflow-auto"
          >
            <ReportTable
              :columns="journalColumns"
              :rows="recent_journals"
              row-key="row_id"
              sticky-header
            >
              <template #cell-transaction_date="{ row }">
                <span class="whitespace-nowrap text-on-surface-variant">{{
                  formatDate(row.transaction_date)
                }}</span>
              </template>
              <template #cell-description="{ row }">
                <p class="font-semibold text-primary">
                  {{ row.journal_number || `#${row.row_id}` }}
                </p>
                <p class="line-clamp-1 text-xs text-on-surface-variant">
                  {{ row.description || "—" }}
                </p>
              </template>
              <template #cell-source="{ row }">
                <AppBadge tone="neutral">{{
                  sourceLabel[row.source_type] || row.source_type || "—"
                }}</AppBadge>
              </template>
              <template #cell-amount="{ row }">
                <span class="whitespace-nowrap font-semibold text-primary">{{
                  formatMoney(row.amount)
                }}</span>
              </template>
            </ReportTable>
          </div>
          <div v-else class="flex flex-1 items-center p-6">
            <AppEmptyState
              icon="receipt_long"
              title="Belum ada jurnal posted"
              description="Transaksi yang di-post akan tampil di sini."
            />
          </div>
        </section>
      </div>

      <section
        class="relative overflow-hidden rounded-xl bg-primary p-6 text-on-primary"
      >
        <AppIcon
          name="verified"
          class="absolute -bottom-8 -right-5 text-[8rem] text-on-primary/5"
        />
        <div class="relative space-y-2">
          <h2 class="text-lg font-bold">Siap operasional</h2>
          <p class="text-sm leading-6 text-primary-fixed-dim">
            KPI dihitung dari jurnal posted — tanpa salinan saldo legacy.
          </p>
          <Link
            href="/accounting/tax-estimate"
            class="inline-flex text-sm font-bold text-on-primary underline-offset-2 hover:underline"
          >
            Lihat taksiran pajak →
          </Link>
        </div>
      </section>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import AppBadge from "../../../Components/AppBadge.vue";
import AppButton from "../../../Components/AppButton.vue";
import AppCard from "../../../Components/AppCard.vue";
import AppIcon from "../../../Components/AppIcon.vue";
import ReportTable from "../../../Components/Reports/ReportTable.vue";
import AppInput from "../../../Components/AppInput.vue";
import SmartSelect from "../../../Components/SmartSelect.vue";
import AdminLayout from "../../../Layouts/AdminLayout.vue";
import { useMoney } from "../../../composables/useMoney.js";

const props = defineProps({
  tenants: { type: Object, required: true },
  summary: { type: Object, required: true },
  plans: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const { money } = useMoney();

const search = ref(props.filters.search || "");
const status = ref(props.filters.status || "");
const planId = ref(props.filters.plan_id || "");
const perPage = ref(String(props.filters.per_page || 15));

const statusOptions = [
  { value: "", label: "Semua Status Pembayaran" },
  { value: "paid", label: "Lunas" },
  { value: "pending", label: "Menunggu Pembayaran" },
  { value: "overdue", label: "Jatuh Tempo / Overdue" },
  { value: "no_invoice", label: "Belum Ada Tagihan" },
];

const planOptions = [
  { value: "", label: "Semua Paket" },
  ...props.plans.map((p) => ({ value: String(p.row_id), label: p.name })),
];

const perPageOptions = [
  { value: "15", label: "15 per halaman" },
  { value: "30", label: "30 per halaman" },
  { value: "50", label: "50 per halaman" },
];

function applyFilters(page = 1) {
  router.get(
    "/admin/revenue",
    {
      search: search.value || undefined,
      status: status.value || undefined,
      plan_id: planId.value || undefined,
      per_page: perPage.value || undefined,
      page: page > 1 ? page : undefined,
    },
    { preserveState: true, replace: true },
  );
}

let searchTimeout;
watch(search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => applyFilters(1), 350);
});

watch([status, planId, perPage], () => {
  applyFilters(1);
});

const revenueColumns = [
  { key: "tenant", label: "Tenant / BUMDesma" },
  { key: "plan", label: "Paket & Tarif" },
  { key: "latest_invoice", label: "Tagihan Terakhir", align: "right" },
  { key: "due", label: "Jatuh Tempo" },
  { key: "payment_status", label: "Status Pembayaran" },
  { key: "lifetime_paid", label: "Total Terbayar", align: "right" },
  { key: "actions", label: "Aksi", align: "right" },
];
</script>

<template>
  <Head title="Monitor Pendapatan & Tagihan Tenant" />
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <!-- Header -->
      <header
        class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
      >
        <div>
          <h1 class="text-2xl font-bold text-primary">
            Monitor Pendapatan &amp; Tagihan Tenant
          </h1>
          <p class="mt-1 text-on-surface-variant">
            Pantau nominal tagihan, tanggal jatuh tempo, dan status pembayaran
            per masing-masing tenant.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link href="/admin/invoices/create">
            <AppButton icon="add">Terbitkan Tagihan</AppButton>
          </Link>
        </div>
      </header>

      <!-- Summary KPI Metric Cards -->
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <AppCard>
          <div class="flex items-center justify-between">
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Tenant Lunas
            </p>
            <AppIcon
              name="check_circle"
              tone="success"
              container-size="8"
              container-shape="pill"
            />
          </div>
          <p class="mt-2 text-2xl font-extrabold text-secondary">
            {{ summary.tenants_paid }}
            <span class="text-xs font-medium text-on-surface-variant"
              >/ {{ summary.total_tenants }} tenant</span
            >
          </p>
          <p class="mt-1.5 text-xs text-on-surface-variant">
            Total Pemasukan:
            <strong class="text-secondary">{{
              money(summary.total_collected)
            }}</strong>
          </p>
        </AppCard>

        <AppCard>
          <div class="flex items-center justify-between">
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Menunggu Pembayaran
            </p>
            <AppIcon
              name="hourglass_top"
              tone="warning"
              container-size="8"
              container-shape="pill"
            />
          </div>
          <p class="mt-2 text-2xl font-extrabold text-warning">
            {{ summary.tenants_pending }}
          </p>
          <p class="mt-1.5 text-xs text-on-surface-variant">
            Invoice aktif belum jatuh tempo
          </p>
        </AppCard>

        <AppCard>
          <div class="flex items-center justify-between">
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Jatuh Tempo (Overdue)
            </p>
            <AppIcon
              name="warning"
              tone="error"
              container-size="8"
              container-shape="pill"
            />
          </div>
          <p class="mt-2 text-2xl font-extrabold text-error">
            {{ summary.tenants_overdue }}
          </p>
          <p class="mt-1.5 text-xs text-on-surface-variant">
            Membutuhkan tindak lanjut penagihan
          </p>
        </AppCard>

        <AppCard>
          <div class="flex items-center justify-between">
            <p
              class="text-xs font-bold uppercase tracking-wider text-on-surface-variant"
            >
              Total Tagihan Belum Bayar
            </p>
            <AppIcon
              name="account_balance_wallet"
              tone="primary"
              container-size="8"
              container-shape="pill"
            />
          </div>
          <p
            class="mt-2 text-2xl font-extrabold"
            :class="
              summary.total_outstanding > 0 ? 'text-error' : 'text-primary'
            "
          >
            {{ money(summary.total_outstanding) }}
          </p>
          <p class="mt-1.5 text-xs text-on-surface-variant">
            {{ summary.tenants_no_invoice }} tenant belum memiliki tagihan
          </p>
        </AppCard>
      </div>

      <!-- Filter Controls -->
      <AppCard>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <AppInput
            v-model="search"
            label="Cari Tenant / Invoice"
            placeholder="Ketik nama tenant atau no invoice..."
            icon="search"
          />
          <SmartSelect
            v-model="status"
            label="Status Pembayaran"
            :options="statusOptions"
          />
          <SmartSelect
            v-model="planId"
            label="Paket Langganan"
            :options="planOptions"
          />
          <SmartSelect
            v-model="perPage"
            label="Tampilkan"
            :options="perPageOptions"
          />
        </div>
      </AppCard>

      <!-- Main Table: Per-Tenant Billing Monitor -->
      <AppCard :padded="false">
        <ReportTable
          :columns="revenueColumns"
          :rows="tenants.data"
          row-key="row_id"
          size="default"
          empty-title="Tidak ada data tenant yang cocok dengan filter."
        >
          <template #cell-tenant="{ row }">
            <div class="min-w-0">
              <Link
                :href="`/admin/tenants/${row.row_id}`"
                class="block truncate font-bold text-primary hover:underline"
                >{{ row.name }}</Link
              >
              <div
                class="mt-0.5 flex items-center gap-2 text-xs text-on-surface-variant"
              >
                <span>{{ row.code }}</span>
                <span v-if="row.district_code"
                  >· kec. {{ row.district_code }}</span
                >
                <AppBadge
                  :tone="row.tenant_status === 'active' ? 'success' : 'error'"
                  >{{
                    row.tenant_status === "active" ? "Aktif" : "Suspended"
                  }}</AppBadge
                >
              </div>
            </div>
          </template>
          <template #cell-plan="{ row }">
            <div v-if="row.plan">
              <p class="font-semibold text-primary">{{ row.plan.name }}</p>
              <p class="text-xs text-on-surface-variant">
                {{ money(row.plan.price_amount, row.plan.currency) }} /
                {{ row.plan.billing_period }}
              </p>
            </div>
            <span v-else class="text-xs italic text-on-surface-variant"
              >Belum Ada Paket</span
            >
          </template>
          <template #cell-latest_invoice="{ row }">
            <div v-if="row.latest_invoice">
              <p class="font-bold tabular-nums text-primary">
                {{
                  money(row.latest_invoice.amount, row.latest_invoice.currency)
                }}
              </p>
              <Link
                :href="`/admin/invoices/${row.latest_invoice.row_id}`"
                class="ml-auto block max-w-[140px] truncate font-mono text-xs text-on-surface-variant hover:text-primary hover:underline"
                >{{ row.latest_invoice.number }}</Link
              >
            </div>
            <span v-else class="text-xs italic text-on-surface-variant">—</span>
          </template>
          <template #cell-due="{ row }">
            <div v-if="row.latest_invoice">
              <p class="whitespace-nowrap text-xs font-medium text-on-surface">
                {{ row.latest_invoice.due_at || "—" }}
              </p>
              <p
                class="mt-0.5 text-[11px] font-semibold"
                :class="
                  row.payment_status === 'overdue'
                    ? 'text-error'
                    : row.payment_status === 'paid'
                      ? 'text-secondary'
                      : 'text-on-surface-variant'
                "
              >
                {{ row.due_info }}
              </p>
            </div>
            <span
              v-else-if="row.due_info"
              class="text-xs font-medium text-info"
              >{{ row.due_info }}</span
            >
            <span v-else class="text-xs italic text-on-surface-variant">—</span>
          </template>
          <template #cell-payment_status="{ row }"
            ><AppBadge :tone="row.payment_status_tone">{{
              row.payment_status_label
            }}</AppBadge></template
          >
          <template #cell-lifetime_paid="{ row }"
            ><span
              class="whitespace-nowrap font-semibold tabular-nums text-secondary"
              >{{ money(row.lifetime_paid) }}</span
            ></template
          >
          <template #cell-actions="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <Link
                v-if="row.latest_invoice"
                :href="`/admin/invoices/${row.latest_invoice.row_id}`"
                ><AppButton variant="ghost" size="compact" icon="receipt"
                  >Invoice</AppButton
                ></Link
              >
              <Link :href="`/admin/invoices/create?tenant_id=${row.row_id}`"
                ><AppButton variant="secondary" size="compact" icon="add"
                  >Tagih</AppButton
                ></Link
              >
              <Link :href="`/admin/tenants/${row.row_id}`"
                ><AppButton variant="ghost" size="compact" icon="visibility"
                  >Detail</AppButton
                ></Link
              >
            </div>
          </template>
        </ReportTable>
        <!-- Pagination -->
        <div
          v-if="tenants.last_page > 1"
          class="flex items-center justify-between border-t border-outline-variant px-6 py-4 text-sm"
        >
          <p class="text-on-surface-variant">
            Menampilkan {{ tenants.from || 0 }} - {{ tenants.to || 0 }} dari
            {{ tenants.total }} tenant
          </p>
          <div class="flex gap-2">
            <AppButton
              variant="ghost"
              size="compact"
              :disabled="tenants.current_page <= 1"
              @click="applyFilters(tenants.current_page - 1)"
            >
              Sebelumnya
            </AppButton>
            <AppButton
              variant="ghost"
              size="compact"
              :disabled="tenants.current_page >= tenants.last_page"
              @click="applyFilters(tenants.current_page + 1)"
            >
              Selanjutnya
            </AppButton>
          </div>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

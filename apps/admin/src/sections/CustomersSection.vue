<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


  <section class="card">
    <div v-if="selectedCustomer" class="customer-detail">
      <div class="card-head"><span class="card-title">Customer details</span><button class="button button--secondary button--small" type="button" @click="selectedCustomer = null">Close</button></div>
      <div class="card-body">
        <div class="detail-list">
          <div class="detail-item"><div class="detail-label">Name</div><div class="detail-value">{{ [selectedCustomer.first_name, selectedCustomer.last_name].filter(Boolean).join(' ') || '—' }}</div></div>
          <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">{{ selectedCustomer.email }}</div></div>
          <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">{{ selectedCustomer.phone || '—' }}</div></div>
          <div class="detail-item"><div class="detail-label">Account status</div><div class="detail-value">{{ selectedCustomer.status }}</div></div>
          <div class="detail-item"><div class="detail-label">Email verified</div><div class="detail-value">{{ selectedCustomer.email_verified_at ? new Date(selectedCustomer.email_verified_at).toLocaleString() : 'Not verified' }}</div></div>
          <div class="detail-item"><div class="detail-label">Phone verified</div><div class="detail-value">{{ selectedCustomer.phone_verified_at ? new Date(selectedCustomer.phone_verified_at).toLocaleString() : 'Not verified' }}</div></div>
          <div class="detail-item"><div class="detail-label">Customer since</div><div class="detail-value">{{ new Date(selectedCustomer.created_at).toLocaleString() }}</div></div>
        </div>
      </div>
    </div>
    <div class="card-head"><span class="card-title">Customer accounts</span><div class="filter-row">
      <input v-model="filters.q" class="search-input" placeholder="Search name, email or phone" @keyup.enter="searchCustomers" />
      <select v-model="filters.status" class="search-input status-filter" @change="searchCustomers"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="deleted">Deleted</option></select>
      <button class="button button--secondary" @click="searchCustomers">Search</button>
    </div></div>
    <div v-if="loading" class="loading">Loading customers…</div>
    <div v-else-if="rows.length === 0" class="empty-state">No matching customers.</div>
    <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Customer</th><th>Phone</th><th>Joined</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="customer in rows" :key="customer.id"><td><button class="text-button primary-cell" type="button" @click="openCustomer(customer)">{{ [customer.first_name, customer.last_name].filter(Boolean).join(' ') || customer.email }}</button><div class="secondary-cell">{{ customer.email }}</div></td><td>{{ customer.phone || '—' }}</td><td>{{ new Date(customer.created_at).toLocaleDateString() }}</td><td><span class="status-pill" :class="`status-pill--${customer.status}`">{{ customer.status }}</span></td><td><button v-if="customer.status !== 'deleted'" class="text-button" type="button" :disabled="busy" @click="toggleCustomer(customer)">{{ customer.status === 'active' ? 'Suspend' : 'Reactivate' }}</button></td></tr>
    </tbody></table></div>
    <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage" @click="loadSection(page + 1)">Next</button></div></div>
  </section>
</template>

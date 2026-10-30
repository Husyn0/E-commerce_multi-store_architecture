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
    <div class="card-head"><span class="card-title">Audit history</span><button class="text-button" type="button" @click="loadSection()">Refresh</button></div>
    <div class="audit-filters">
      <div class="field"><label for="audit-action">Search action</label><input id="audit-action" v-model.trim="filters.action" class="search-input" placeholder="product, order…" @keyup.enter="filterAudit" /></div>
      <div class="field"><label for="audit-entity">Search entity type</label><input id="audit-entity" v-model.trim="filters.entity_type" class="search-input" placeholder="product, order…" @keyup.enter="filterAudit" /></div>
      <div class="field"><label for="audit-actor">Actor UUID</label><input id="audit-actor" v-model.trim="filters.actor_id" class="search-input" placeholder="Optional" /></div>
      <div class="field"><label for="audit-source">Source</label><select id="audit-source" v-model="filters.source" class="search-input"><option value="">All sources</option><option value="web">Web</option><option value="cli">CLI</option><option value="webhook">Webhook</option></select></div>
      <div class="field"><label for="audit-from">From (UTC)</label><input id="audit-from" v-model="filters.from" class="search-input" type="date" /></div>
      <div class="field"><label for="audit-to">To (UTC)</label><input id="audit-to" v-model="filters.to" class="search-input" type="date" /></div>
      <div class="filter-actions"><button class="button" type="button" @click="filterAudit">Apply filters</button><button class="button button--secondary" type="button" @click="resetAuditFilters">Clear</button></div>
    </div>
    <div v-if="loading" class="loading">Loading audit events…</div>
    <div v-else-if="rows.length === 0" class="empty-state">No audit events match these filters.</div>
    <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Action</th><th>Entity</th><th>Actor</th><th>Source / host</th><th>Time (UTC)</th></tr></thead><tbody>
      <tr v-for="event in rows" :key="event.id"><td class="primary-cell">{{ event.action }}</td><td>{{ event.entity_type }}<div class="secondary-cell">{{ event.entity_id }}</div></td><td>{{ event.actor?.email || 'System / CLI' }}</td><td><span class="status-pill">{{ event.source }}</span><div class="secondary-cell">{{ event.host_type || '—' }}</div></td><td>{{ new Date(event.created_at).toLocaleString(undefined, { timeZone: 'UTC' }) }}</td></tr>
    </tbody></table></div>
    <div class="pagination"><span>Page {{ auditPage }} · Newest first · Date range in UTC</span><div class="inline-actions"><label class="page-size-control">Rows<select class="search-input" :value="auditLimit" @change="changeAuditLimit($event.target.value)"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option><option :value="100">100</option></select></label><button class="button button--secondary button--small" :disabled="auditPage <= 1 || loading" @click="previousAuditPage">Previous</button><button class="button button--secondary button--small" :disabled="!nextAuditCursor || loading" @click="nextAuditPage">Next</button></div></div>
  </section>
</template>

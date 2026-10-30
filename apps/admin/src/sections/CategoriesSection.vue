<script>
import { defineComponent, inject } from 'vue'

export default defineComponent({
  setup() {
    return inject('adminWorkspace')
  },
})
</script>

<template>


    <section v-if="showCategoryForm" class="card form-card">
      <div class="card-head"><span class="card-title">{{ categoryForm.id ? 'Edit category' : 'New category' }}</span></div>
      <form class="card-body" @submit.prevent="saveCategory">
        <div class="form-grid">
          <div class="field"><label for="category-parent">Parent category</label><select id="category-parent" v-model="categoryForm.parent_id"><option value="">No parent</option><option v-for="category in rows" :key="category.id" :value="category.id" :disabled="category.id === categoryForm.id">{{ category.name }}</option></select></div>
          <div class="field"><label for="category-name">Name</label><input id="category-name" v-model.trim="categoryForm.name" maxlength="160" required /></div>
          <div class="field"><label for="category-slug">Slug</label><input id="category-slug" v-model.trim="categoryForm.slug" maxlength="180" required /></div>
          <div class="field"><label for="category-position">Position</label><input id="category-position" v-model.number="categoryForm.position" type="number" min="0" /></div>
          <div class="field"><label for="category-icon">Icon (emoji or symbol)</label><input id="category-icon" v-model.trim="categoryForm.icon" maxlength="32" placeholder="🌿" /></div>
          <div class="field field--wide"><label for="category-image">Category image</label><input id="category-image" type="file" accept="image/jpeg,image/png,image/webp,image/avif" @change="categoryForm.image_file = $event.target.files?.[0] || null; categoryForm.remove_image = false" /><small>Optional image shown on the shop collection tile. Up to 10 MB.</small><div v-if="categoryForm.image_url" class="category-image-manager"><img :src="categoryForm.image_url" alt="Current category image" class="admin-media-preview" /><label class="checkbox-field"><input v-model="categoryForm.remove_image" type="checkbox" /> Remove current image when saving</label></div></div>
          <label class="checkbox-field"><input v-model="categoryForm.is_active" type="checkbox" /> Active</label>
        </div>
        <div class="form-actions"><button class="button button--secondary" type="button" @click="showCategoryForm = false">Cancel</button><button class="button" :disabled="busy">{{ categoryForm.id ? 'Save category' : 'Create category' }}</button></div>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><span class="card-title">Categories</span></div>
      <div v-if="loading" class="loading">Loading categories…</div>
      <div v-else-if="rows.length === 0" class="empty-state">No categories created.</div>
      <div v-else class="table-scroll"><table class="data-table"><thead><tr><th>Category</th><th>Slug</th><th>Position</th><th>Status</th><th></th></tr></thead><tbody>
        <tr v-for="category in rows" :key="category.id"><td class="primary-cell"><img v-if="category.image_url" :src="category.image_url" :alt="''" class="category-admin-thumb" /><span v-else-if="category.icon">{{ category.icon }}</span>{{ category.name }}</td><td>{{ category.slug }}</td><td>{{ category.position }}</td><td><span class="status-pill" :class="`status-pill--${category.is_active ? 'active' : 'suspended'}`">{{ category.is_active ? 'active' : 'inactive' }}</span></td><td><div class="inline-actions"><button class="text-button" type="button" @click="editCategory(category)">Edit</button><button class="text-button text-button--danger" type="button" :disabled="busy" @click="removeCategory(category)">Delete</button></div></td></tr>
      </tbody></table></div>
      <div class="pagination"><span>Page {{ page }} of {{ lastPage }}</span><div class="inline-actions"><button class="button button--secondary button--small" :disabled="page <= 1" @click="loadSection(page - 1)">Previous</button><button class="button button--secondary button--small" :disabled="page >= lastPage" @click="loadSection(page + 1)">Next</button></div></div>
    </section>
</template>

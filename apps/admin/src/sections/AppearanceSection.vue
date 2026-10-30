<script>
import { defineComponent, inject, ref } from 'vue'

const palettes = [
  { name: 'Warm & natural', colors: { primary: '#6b5b4b', secondary: '#f0ece7', accent: '#aa7d52', background: '#ffffff', text: '#242323' } },
  { name: 'Fresh & calm', colors: { primary: '#315c55', secondary: '#e7f0ec', accent: '#b77a52', background: '#ffffff', text: '#202a27' } },
  { name: 'Bold & modern', colors: { primary: '#293b68', secondary: '#edf0f8', accent: '#cf5b46', background: '#ffffff', text: '#202431' } },
  { name: 'Soft & minimal', colors: { primary: '#514b50', secondary: '#f2eff0', accent: '#a87d87', background: '#ffffff', text: '#282528' } },
  { name: 'Coastal blue', colors: { primary: '#234e70', secondary: '#e8f2f6', accent: '#4e91a6', background: '#ffffff', text: '#1d3039' } },
  { name: 'Olive market', colors: { primary: '#46543a', secondary: '#eff0e5', accent: '#a77d42', background: '#fffefa', text: '#292c24' } },
  { name: 'Terracotta', colors: { primary: '#744336', secondary: '#f5e9e1', accent: '#c16b4d', background: '#fffdfb', text: '#352924' } },
  { name: 'Midnight', colors: { primary: '#30364f', secondary: '#ececf2', accent: '#9c83bd', background: '#ffffff', text: '#222432' } },
  { name: 'Citrus shop', colors: { primary: '#42513c', secondary: '#f2f0dc', accent: '#d19a35', background: '#fffefa', text: '#28291f' } },
  { name: 'Blush boutique', colors: { primary: '#65434b', secondary: '#f5e9eb', accent: '#bd7885', background: '#fffdfd', text: '#34272b' } },
]

export default defineComponent({
  setup() {
    const workspace = inject('adminWorkspace')
    const selectedPalette = ref('')
    const paletteMenuOpen = ref(false)
    return {
      ...workspace,
      palettes,
      selectedPalette,
      paletteMenuOpen,
      applyPalette(palette) {
        Object.assign(workspace.themeForm.colors, palette.colors)
      },
      choosePalette(index) {
        const palette = palettes[index]
        if (!palette) return
        Object.assign(workspace.themeForm.colors, palette.colors)
        selectedPalette.value = String(index)
        paletteMenuOpen.value = false
      },
    }
  },
})
</script>

<template>


  <section class="card">
    <div class="card-head"><div><span class="card-title">Storefront appearance</span><p class="appearance-intro">Choose your store’s colors and style. Your changes appear in the preview before you save.</p></div><span class="status-pill">Tenant scoped</span></div>
    <div v-if="loading" class="loading">Loading theme…</div>
    <form v-else class="card-body" @submit.prevent="saveTheme">
      <div class="theme-editor-grid">
        <div class="appearance-controls">
          <fieldset class="appearance-group">
            <legend>Start with a color style</legend>
            <div class="field palette-select">
              <span>Color style</span>
              <button class="palette-select-trigger" type="button" :aria-expanded="paletteMenuOpen" aria-haspopup="listbox" @click="paletteMenuOpen = !paletteMenuOpen">
                <span>{{ selectedPalette === '' ? 'Choose a style…' : palettes[Number(selectedPalette)].name }}</span>
                <span class="palette-swatches" aria-hidden="true"><i v-for="(color, key) in previewColors" :key="key" :style="{ backgroundColor: color }" /></span>
                <span class="palette-chevron" aria-hidden="true">{{ paletteMenuOpen ? '⌃' : '⌄' }}</span>
              </button>
              <div v-if="paletteMenuOpen" class="palette-menu" role="listbox" aria-label="Color styles">
                <button v-for="(palette, index) in palettes" :key="palette.name" class="palette-option" :class="{ 'palette-option--selected': selectedPalette === String(index) }" type="button" role="option" :aria-selected="selectedPalette === String(index)" @click="choosePalette(index)">
                  <span class="palette-option-copy"><strong>{{ palette.name }}</strong><small>{{ palette.colors.accent }} accent · {{ palette.colors.primary }} main color</small></span>
                  <span class="palette-swatches" aria-hidden="true"><i v-for="(color, key) in palette.colors" :key="key" :style="{ backgroundColor: color }" /></span>
                </button>
              </div>
            </div>
          </fieldset>

          <fieldset class="appearance-group">
            <legend>Fine-tune your colors</legend>
            <div class="color-control-grid">
              <label v-for="(value, key) in themeForm.colors" :key="key" class="color-control">
                <span>{{ key.charAt(0).toUpperCase() + key.slice(1) }}</span>
                <input v-model="themeForm.colors[key]" type="color" :aria-label="`${key} color`" />
              </label>
            </div>
          </fieldset>

          <fieldset class="appearance-group appearance-options">
            <legend>Store style</legend>
            <label class="field"><span>Layout</span><select v-model="themeForm.layout_variant"><option value="classic">Classic</option><option value="editorial">Editorial</option><option value="minimal">Minimal</option></select></label>
            <label class="field"><span>Heading font</span><select v-model="themeForm.fonts.heading"><option>Georgia</option><option>Arial</option><option>Trebuchet MS</option><option>Times New Roman</option></select></label>
            <label class="field"><span>Body font</span><select v-model="themeForm.fonts.body"><option>Arial</option><option>Georgia</option><option>Trebuchet MS</option><option>Verdana</option></select></label>
            <label class="field"><span>Logo image link <small>Optional</small></span><input v-model.trim="themeForm.logo_url" type="url" placeholder="https://…" /><small>Use a public link to your logo image.</small></label>
          </fieldset>
        </div>
        <div class="theme-preview" :class="`theme-preview--${themeForm.layout_variant}`" :style="{
          '--theme-primary': previewColors.primary,
          '--theme-secondary': previewColors.secondary,
          '--theme-accent': previewColors.accent,
          '--theme-background': previewColors.background,
          '--theme-text': previewColors.text,
          '--theme-heading-font': themeForm.fonts.heading,
          '--theme-body-font': themeForm.fonts.body,
        }">
          <div class="theme-preview-head"><img v-if="themeForm.logo_url" class="theme-preview-image" :src="themeForm.logo_url" alt="Store logo preview" /><span v-else class="theme-preview-logo">V</span><span>Storefront preview</span><span class="preview-dot" /></div>
          <div class="theme-preview-content">
            <p class="preview-eyebrow">A LITTLE SOMETHING SPECIAL</p>
            <h2>Find your<br />everyday favorite.</h2>
            <p>A considered collection, made for the way you live.</p>
            <button type="button" class="preview-button">Explore the collection <span>→</span></button>
          </div>
          <div class="theme-preview-swatch-row"><span v-for="(value, key) in previewColors" :key="key" class="theme-swatch" :title="`${key}: ${value}`" :style="{ backgroundColor: value }" /></div>
        </div>
      </div>
      <div class="form-actions"><button class="button" :disabled="busy">{{ busy ? 'Saving…' : 'Save appearance' }}</button></div>
    </form>
  </section>
</template>

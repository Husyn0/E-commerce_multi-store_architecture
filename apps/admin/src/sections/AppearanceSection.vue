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
  { name: 'Ocean sunset', source: 'https://coolors.co/264653-2a9d8f-e9c46a-f4a261-e76f51', colors: { primary: '#264653', secondary: '#e9c46a', accent: '#e76f51', background: '#fffaf1', text: '#20343a' } },
  { name: 'Cool aqua', source: 'https://coolors.co/03045e-0077b6-00b4d8-90e0ef-caf0f8', colors: { primary: '#03045e', secondary: '#caf0f8', accent: '#00b4d8', background: '#f4fcff', text: '#03045e' } },
  { name: 'Sunny Beach Day', source: 'https://coolors.co/606c38-283618-fefae0-dda15e-bc6c25', swatches: ['#606C38', '#283618', '#FEFAE0', '#DDA15E', '#BC6C25'], colors: { primary: '#283618', secondary: '#DDA15E', accent: '#BC6C25', background: '#FEFAE0', text: '#283618' } },
  { name: 'Olive Garden Feast', source: 'https://coolors.co/e63946-f1faee-a8dadc-457b9d-1d3557', swatches: ['#E63946', '#F1FAEE', '#A8DADC', '#457B9D', '#1D3557'], colors: { primary: '#1D3557', secondary: '#A8DADC', accent: '#E63946', background: '#F1FAEE', text: '#1D3557' } },
  { name: 'Summer Ocean Breeze', source: 'https://coolors.co/8ecae6-219ebc-023047-ffb703-fb8500', swatches: ['#8ECAE6', '#219EBC', '#023047', '#FFB703', '#FB8500'], colors: { primary: '#023047', secondary: '#8ECAE6', accent: '#FB8500', background: '#F4FBFF', text: '#023047' } },
  { name: 'Refreshing Summer Fun', source: 'https://coolors.co/cdb4db-ffc8dd-ffafcc-bde0fe-a2d2ff', swatches: ['#CDB4DB', '#FFC8DD', '#FFAFCC', '#BDE0FE', '#A2D2FF'], colors: { primary: '#74627F', secondary: '#BDE0FE', accent: '#FFAFCC', background: '#FFF8FC', text: '#423748' } },
  { name: 'Pastel Dreamland Adventure', source: 'https://coolors.co/ccd5ae-e9edc9-fefae0-faedcd-d4a373', swatches: ['#CCD5AE', '#E9EDC9', '#FEFAE0', '#FAEDCD', '#D4A373'], colors: { primary: '#606C38', secondary: '#E9EDC9', accent: '#D4A373', background: '#FEFAE0', text: '#384326' } },
  { name: 'Golden Summer Fields', source: 'https://coolors.co/03045e-023e8a-0077b6-0096c7-00b4d8-48cae4-90e0ef-ade8f4-caf0f8', swatches: ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4', '#90E0EF', '#ADE8F4', '#CAF0F8'], colors: { primary: '#03045E', secondary: '#90E0EF', accent: '#00B4D8', background: '#F4FCFF', text: '#03045E' } },
  { name: 'Ocean Blue Serenity', source: 'https://coolors.co/03045e-023e8a-0077b6-0096c7-00b4d8-48cae4-90e0ef-ade8f4-caf0f8', swatches: ['#03045E', '#023E8A', '#0077B6', '#0096C7', '#00B4D8', '#48CAE4', '#90E0EF', '#ADE8F4', '#CAF0F8'], colors: { primary: '#023E8A', secondary: '#90E0EF', accent: '#00B4D8', background: '#F4FCFF', text: '#03045E' } },
  { name: 'Black & Gold Elegance', source: 'https://coolors.co/000000-14213d-fca311-e5e5e5-ffffff', swatches: ['#000000', '#14213D', '#FCA311', '#E5E5E5', '#FFFFFF'], colors: { primary: '#14213D', secondary: '#E5E5E5', accent: '#FCA311', background: '#FFFFFF', text: '#000000' } },
  { name: 'Muted Earthy Tones', source: 'https://coolors.co/ffcdb2-ffb4a2-e5989b-b5838d-6d6875', swatches: ['#FFCDB2', '#FFB4A2', '#E5989B', '#B5838D', '#6D6875'], colors: { primary: '#6D6875', secondary: '#FFCDB2', accent: '#E5989B', background: '#FFF8F4', text: '#494650' } },
  { name: 'Cozy Neutral Retreat', source: 'https://coolors.co/003049-d62828-f77f00-fcbf49-eae2b7', swatches: ['#003049', '#D62828', '#F77F00', '#FCBF49', '#EAE2B7'], colors: { primary: '#003049', secondary: '#EAE2B7', accent: '#D62828', background: '#FFFCF2', text: '#003049' } },
  { name: 'Warm Autumn Glow', source: 'https://coolors.co/003049-d62828-f77f00-fcbf49-eae2b7', swatches: ['#003049', '#D62828', '#F77F00', '#FCBF49', '#EAE2B7'], colors: { primary: '#6E321F', secondary: '#FCBF49', accent: '#F77F00', background: '#FFF8E8', text: '#38251D' } },
  { name: 'Vibrant Color Fiesta', source: 'https://coolors.co/ffbe0b-fb5607-ff006e-8338ec-3a86ff', swatches: ['#FFBE0B', '#FB5607', '#FF006E', '#8338EC', '#3A86FF'], colors: { primary: '#4B2387', secondary: '#FFBE0B', accent: '#FF006E', background: '#FFF8F5', text: '#261337' } },
  { name: 'Soft Peachy Delight', source: 'https://coolors.co/fec5bb-fcd5ce-fae1dd-f8edeb-e8e8e4-d8e2dc-ece4db-ffe5d9-ffd7ba-fec89a', swatches: ['#FEC5BB', '#FCD5CE', '#FAE1DD', '#F8EDEB', '#E8E8E4', '#D8E2DC', '#ECE4DB', '#FFE5D9', '#FFD7BA', '#FEC89A'], colors: { primary: '#77756D', secondary: '#FCD5CE', accent: '#E5988B', background: '#FFFBF9', text: '#46443F' } },
  { name: 'Fiery Red Sunset', source: 'https://coolors.co/03071e-370617-6a040f-9d0208-d00000-dc2f02-e85d04-f48c06-faa307-ffba08', swatches: ['#03071E', '#370617', '#6A040F', '#9D0208', '#D00000', '#DC2F02', '#E85D04', '#F48C06', '#FAA307', '#FFBA08'], colors: { primary: '#370617', secondary: '#FAA307', accent: '#D00000', background: '#FFF7ED', text: '#03071E' } },
  { name: 'Ocean Pearl Delight', source: 'https://coolors.co/006d77-83c5be-edf6f9-ffddd2-e29578', swatches: ['#006D77', '#83C5BE', '#EDF6F9', '#FFDDD2', '#E29578'], colors: { primary: '#006D77', secondary: '#83C5BE', accent: '#E29578', background: '#EDF6F9', text: '#004B50' } },
  { name: 'Cool Coastal Vibes', source: 'https://coolors.co/2b2d42-8d99ae-edf2f4-ef233c-d90429', swatches: ['#2B2D42', '#8D99AE', '#EDF2F4', '#EF233C', '#D90429'], colors: { primary: '#2B2D42', secondary: '#8D99AE', accent: '#EF233C', background: '#EDF2F4', text: '#2B2D42' } },
  { name: 'Neutral Harmony Bliss', source: 'https://coolors.co/f4f1de-e07a5f-3d405b-81b29a-f2cc8f', swatches: ['#F4F1DE', '#E07A5F', '#3D405B', '#81B29A', '#F2CC8F'], colors: { primary: '#3D405B', secondary: '#81B29A', accent: '#E07A5F', background: '#F4F1DE', text: '#303247' } },
  { name: 'Earthy Forest Hues', source: 'https://coolors.co/dad7cd-a3b18a-588157-3a5a40-344e41', swatches: ['#DAD7CD', '#A3B18A', '#588157', '#3A5A40', '#344E41'], colors: { primary: '#344E41', secondary: '#A3B18A', accent: '#588157', background: '#F2F1EA', text: '#26372F' } },
  { name: 'Pastel Rainbow Fantasy', source: 'https://coolors.co/ffadad-ffd6a5-fdffb6-caffbf-9bf6ff-a0c4ff-bdb2ff-ffc6ff-fffffc', swatches: ['#FFADAD', '#FFD6A5', '#FDFFB6', '#CAFFBF', '#9BF6FF', '#A0C4FF', '#BDB2FF', '#FFC6FF', '#FFFFFC'], colors: { primary: '#6D647F', secondary: '#FFD6A5', accent: '#BDB2FF', background: '#FFFFFC', text: '#484256' } },
  { name: 'Fiery Ocean', source: 'https://coolors.co/780000-c1121f-fdf0d5-003049-669bbc', swatches: ['#780000', '#C1121F', '#FDF0D5', '#003049', '#669BBC'], colors: { primary: '#003049', secondary: '#669BBC', accent: '#C1121F', background: '#FDF0D5', text: '#003049' } },
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
            <p class="palette-source-note">New palette inspirations from <a href="https://coolors.co/palettes/popular" target="_blank" rel="noreferrer">Coolors popular palettes</a>. Select one, then adjust any color below.</p>
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
                  <span class="palette-swatches" aria-hidden="true"><i v-for="(color, key) in (palette.swatches || Object.values(palette.colors))" :key="key" :style="{ backgroundColor: color }" /></span>
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

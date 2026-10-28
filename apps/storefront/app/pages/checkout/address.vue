<script setup lang="ts">
useSeoMeta({ title: 'Checkout' })
</script>

<template>
  <section class="page-section checkout-notice">
    <div class="page-intro">
      <NuxtLink class="back-link" to="/cart">← Back to your bag</NuxtLink>
      <span class="eyebrow">Almost yours</span>
      <h1>Checkout is getting ready.</h1>
      <p>Your cart is safe. We’re finishing the secure payment experience before orders can be placed.</p>
    </div>
    <div class="checkout-notice__card">
      <span class="checkout-notice__icon">◇</span>
      <div>
        <h2>Secure checkout is not available yet.</h2>
        <p>We won't create an order or reserve inventory until the payment form is fully connected. Please come back soon.</p>
        <NuxtLink class="button button--dark" to="/cart">Return to your bag</NuxtLink>
      </div>
    </div>
  </section>
</template>

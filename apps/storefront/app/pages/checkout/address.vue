<script setup lang="ts">
interface PaymentConfiguration {
  provider: string
  publishable_key: string | null
  connected_account_id: string | null
}

interface CheckoutResponse {
  order: { id: string; order_number: string; total_cents: number; currency: string; payment_status: string }
  payment: { provider: string; payment_intent_id?: string; client_secret?: string; status: string }
}

interface StripeElements {
  create(type: 'payment'): { mount(selector: string): void; unmount(): void }
}

interface StripeClient {
  elements(options: { clientSecret: string }): StripeElements
  confirmPayment(options: {
    elements: StripeElements
    confirmParams: { return_url: string }
    redirect: 'if_required'
  }): Promise<{ error?: { message?: string }; paymentIntent?: { status: string } }>
}

declare global {
  interface Window {
    Stripe?: (publishableKey: string, options?: { stripeAccount?: string }) => StripeClient
  }
}

const api = useStorefrontApi()
const { cart, refresh } = useCart()
const form = reactive({
  recipient_name: '',
  email: '',
  phone: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  postal_code: '',
  country_code: 'LB',
})
const paymentConfig = ref<PaymentConfiguration | null>(null)
const paymentMethod = ref<'cash_on_delivery' | 'online'>('cash_on_delivery')
const stage = ref<'details' | 'payment' | 'complete'>('details')
const checkout = ref<CheckoutResponse | null>(null)
const pending = ref(false)
const error = ref('')
const stripeElement = ref<{ mount(selector: string): void; unmount(): void } | null>(null)
const stripe = ref<StripeClient | null>(null)
const elements = ref<StripeElements | null>(null)
const checkoutKey = ref('')
const onlineAvailable = computed(() => paymentConfig.value?.provider === 'stripe'
  && Boolean(paymentConfig.value.publishable_key && paymentConfig.value.connected_account_id))

onMounted(async () => {
  await Promise.all([
    refresh().catch(() => undefined),
    api.get<PaymentConfiguration>('payment-config')
      .then((config) => { paymentConfig.value = config })
      .catch(() => { paymentConfig.value = null }),
  ])
})

watch([form, paymentMethod], () => {
  if (stage.value === 'details') checkoutKey.value = ''
}, { deep: true })

async function startCheckout() {
  if (!cart.value?.items.length) {
    error.value = 'Your bag is empty.'
    return
  }
  if (paymentMethod.value === 'online' && !onlineAvailable.value) {
    error.value = 'Online card payment is not configured for this store yet. Choose cash on delivery.'
    return
  }

  pending.value = true
  error.value = ''
  try {
    if (!checkoutKey.value) checkoutKey.value = crypto.randomUUID()
    const result = await api.send<CheckoutResponse>('checkout', 'POST', {
      shipping_address: {
        recipient_name: form.recipient_name,
        phone: form.phone || null,
        line1: form.line1,
        line2: form.line2 || null,
        city: form.city,
        state: form.state || null,
        postal_code: form.postal_code,
        country_code: form.country_code,
      },
      currency: cart.value.currency,
      customer_email: form.email,
      customer_phone: form.phone || null,
      payment_method: paymentMethod.value,
    }, { 'Idempotency-Key': checkoutKey.value })

    checkout.value = result

    if (paymentMethod.value === 'cash_on_delivery') {
      stage.value = 'complete'
      await refresh().catch(() => undefined)
      return
    }

    await beginStripePayment(result)
    await refresh().catch(() => undefined)
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    pending.value = false
  }
}

async function beginStripePayment(result: CheckoutResponse) {
  if (!paymentConfig.value?.publishable_key || !result.payment.client_secret) {
    throw new Error('The online payment form could not be started. Please choose cash on delivery or try again later.')
  }

  await loadStripeScript()
  const stripeFactory = window.Stripe
  if (!stripeFactory) throw new Error('The online payment form could not be loaded. Please try again.')

  stripe.value = stripeFactory(paymentConfig.value.publishable_key, {
    stripeAccount: paymentConfig.value.connected_account_id || undefined,
  })
  elements.value = stripe.value.elements({ clientSecret: result.payment.client_secret })
  stage.value = 'payment'
  await nextTick()
  stripeElement.value = elements.value.create('payment')
  stripeElement.value.mount('#checkout-payment-element')
}

async function payOnline() {
  if (!stripe.value || !elements.value) return
  pending.value = true
  error.value = ''
  try {
    const result = await stripe.value.confirmPayment({
      elements: elements.value,
      confirmParams: { return_url: window.location.href },
      redirect: 'if_required',
    })
    if (result.error) {
      error.value = result.error.message || 'Your payment could not be completed. Check the card details and try again.'
      return
    }
    stage.value = 'complete'
  } catch (failure) {
    error.value = apiErrorMessage(failure)
  } finally {
    pending.value = false
  }
}

function loadStripeScript(): Promise<void> {
  if (window.Stripe) return Promise.resolve()
  return new Promise((resolve, reject) => {
    let script = document.querySelector<HTMLScriptElement>('script[data-stripe-js]')
    if (!script) {
      script = document.createElement('script')
      script.src = 'https://js.stripe.com/v3/'
      script.async = true
      script.dataset.stripeJs = 'true'
    }
    script.addEventListener('load', () => resolve(), { once: true })
    script.addEventListener('error', () => reject(new Error('Stripe could not be loaded. Please try again.')), { once: true })
    if (!script.isConnected) document.head.append(script)
  })
}

useSeoMeta({ title: 'Checkout', description: 'Enter delivery details and choose how you want to pay.' })
</script>

<template>
  <section class="page-section checkout-page">
    <div class="page-intro page-intro--compact">
      <NuxtLink class="back-link" to="/cart">← Back to your bag</NuxtLink>
      <span class="eyebrow">Almost yours</span>
      <h1>{{ stage === 'complete' ? 'Order placed.' : stage === 'payment' ? 'Pay securely.' : 'Delivery details.' }}</h1>
      <p v-if="stage === 'details'">Choose delivery details and a payment option to place your order.</p>
    </div>

    <div v-if="stage === 'complete' && checkout" class="checkout-complete">
      <span class="checkout-notice__icon">◇</span>
      <h2>Thank you. Your order is {{ checkout.order.order_number }}.</h2>
      <p v-if="checkout.payment.provider === 'cash_on_delivery'">Pay {{ formatMoney(checkout.order.total_cents, checkout.order.currency) }} in cash when your order arrives.</p>
      <p v-else>Your payment was submitted securely. We’ll update your order as soon as the payment provider confirms it.</p>
      <NuxtLink class="button button--dark" to="/search">Continue shopping</NuxtLink>
    </div>

    <form v-else class="checkout-form" @submit.prevent="stage === 'payment' ? payOnline() : startCheckout()">
      <div v-if="stage === 'details'" class="checkout-form__fields">
        <section class="checkout-panel">
          <h2>Contact</h2>
          <label class="form-field"><span>Email address</span><input v-model.trim="form.email" type="email" autocomplete="email" required></label>
          <label class="form-field"><span>Phone number</span><input v-model.trim="form.phone" type="tel" autocomplete="tel" required></label>
        </section>
        <section class="checkout-panel">
          <h2>Delivery address</h2>
          <label class="form-field"><span>Recipient name</span><input v-model.trim="form.recipient_name" autocomplete="name" required maxlength="160"></label>
          <label class="form-field"><span>Address</span><input v-model.trim="form.line1" autocomplete="address-line1" required maxlength="255"></label>
          <label class="form-field"><span>Apartment, suite or unit <small>Optional</small></span><input v-model.trim="form.line2" autocomplete="address-line2" maxlength="255"></label>
          <div class="checkout-form__row">
            <label class="form-field"><span>City</span><input v-model.trim="form.city" autocomplete="address-level2" required maxlength="120"></label>
            <label class="form-field"><span>State / region <small>Optional</small></span><input v-model.trim="form.state" autocomplete="address-level1" maxlength="120"></label>
          </div>
          <div class="checkout-form__row">
            <label class="form-field"><span>Postal code</span><input v-model.trim="form.postal_code" autocomplete="postal-code" required maxlength="32"></label>
            <label class="form-field"><span>Country code</span><input v-model.trim="form.country_code" autocomplete="country" required minlength="2" maxlength="2" placeholder="LB"></label>
          </div>
        </section>
        <section class="checkout-panel">
          <h2>Payment method</h2>
          <label class="payment-choice">
            <input v-model="paymentMethod" type="radio" value="cash_on_delivery">
            <span><strong>Cash on delivery</strong><small>Pay the courier when your order arrives.</small></span>
          </label>
          <label class="payment-choice" :class="{ 'payment-choice--disabled': !onlineAvailable }">
            <input v-model="paymentMethod" type="radio" value="online" :disabled="!onlineAvailable">
            <span><strong>Pay online by card</strong><small>{{ onlineAvailable ? 'Secure payment powered by Stripe.' : 'Online payments will be available when Stripe is activated for this store.' }}</small></span>
          </label>
        </section>
      </div>
      <section v-else class="checkout-panel checkout-panel--payment">
        <h2>Card details</h2>
        <div id="checkout-payment-element" />
        <p class="checkout-small-print">Your card details are handled securely by Stripe.</p>
      </section>

      <p v-if="error" class="error-box" role="alert">{{ error }}</p>
      <aside v-if="checkout || cart?.items.length" class="checkout-order-summary">
        <span>{{ checkout ? 'Order total' : `${cart?.items.length} item${cart?.items.length === 1 ? '' : 's'}` }}</span>
        <strong>{{ checkout ? formatMoney(checkout.order.total_cents, checkout.order.currency) : formatMoney(cart?.subtotal_cents ?? 0, cart?.currency ?? 'USD') }}</strong>
      </aside>
      <button class="button button--dark button--wide" type="submit" :disabled="pending || (stage === 'details' && !cart?.items.length)">
        {{ pending ? (stage === 'payment' ? 'Processing payment…' : 'Placing order…') : stage === 'payment' ? 'Pay securely' : paymentMethod === 'cash_on_delivery' ? 'Place order' : 'Continue to payment' }}
      </button>
    </form>
  </section>
</template>

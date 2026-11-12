<script setup lang="ts">
interface PaymentConfiguration {
  provider: string
  publishable_key: string | null
  connected_account_id: string | null
}

interface CheckoutCustomer {
  email: string
  phone: string | null
  email_verified: boolean
  phone_verified: boolean
}

interface ChallengeWidget {
  render(target: string, options: Record<string, unknown>): string
  reset?(widgetId?: string): void
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
    turnstile?: ChallengeWidget
    hcaptcha?: ChallengeWidget
  }
}

const api = useStorefrontApi()
const { cart, refresh } = useCart()
const runtimeConfig = useRuntimeConfig()
const { data: tenant } = await useTenant()
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
const countryQuery = ref('Lebanon')
const countryOpen = ref(false)
const countryCodes = 'AW AF AO AI AX AL AD AE AR AM AS AQ TF AG AU AT AZ BI BE BJ BQ BF BD BG BH BS BA BL BY BZ BM BO BR BB BN BT BV BW CF CA CC CH CL CN CI CM CD CG CK CO KM CV CR CU CW CX KY CY CZ DE DJ DM DK DO DZ EC EG ER EH ES EE ET FI FJ FK FR FO FM GA GB GE GG GH GI GN GP GM GW GQ GR GD GL GT GF GU GY HK HM HN HR HT HU ID IM IN IO IE IR IQ IS IL IT JM JE JO JP KZ KE KG KH KI KN KR KW LA LB LR LY LC LI LK LS LT LU LV MO MF MA MC MD MG MV MX MH MK ML MT MM ME MN MP MZ MR MS MQ MU MW MY YT NA NC NE NF NG NI NU NL NO NP NR NZ OM PK PA PN PE PH PW PG PL PR KP PT PY PS PF QA RE RO RU RW SA SD SN SG GS SH SJ SB SL SV SM SO PM RS SS ST SR SK SI SE SZ SX SC SY TC TD TG TH TJ TK TM TL TO TT TN TR TV TW TZ UG UA UM UY US UZ VA VC VE VG VI VN VU WF WS YE ZA ZM ZW'.split(' ')
const countryNames = new Intl.DisplayNames(['en'], { type: 'region' })
const countryOptions = countryCodes.map((code) => ({ code, name: countryNames.of(code) || code }))
  .sort((first, second) => first.name.localeCompare(second.name))
const filteredCountries = computed(() => {
  const query = countryQuery.value.trim().toLocaleLowerCase()
  return countryOptions.filter(({ code, name }) => !query || name.toLocaleLowerCase().includes(query) || code.toLocaleLowerCase().includes(query)).slice(0, 30)
})
const paymentConfig = ref<PaymentConfiguration | null>(null)
const customer = ref<CheckoutCustomer | null>(null)
const paymentMethod = ref<'cash_on_delivery' | 'online'>('cash_on_delivery')
const stage = ref<'details' | 'payment' | 'complete'>('details')
const checkout = ref<CheckoutResponse | null>(null)
const pending = ref(false)
const error = ref('')
const stripeElement = ref<{ mount(selector: string): void; unmount(): void } | null>(null)
const stripe = ref<StripeClient | null>(null)
const elements = ref<StripeElements | null>(null)
const checkoutKey = ref('')
const humanToken = ref('')
const captchaAnswer = ref('')
const captchaToken = ref('')
const captchaImage = ref('')
const humanVerified = ref(false)
const checkingHuman = ref(false)
const humanError = ref('')
const accountLoading = ref(true)
let humanWidgetId = ''
const humanDriver = computed(() => tenant.value?.registration?.human_check_driver || 'demo')
const accountVerified = computed(() => Boolean(customer.value?.email_verified))
const onlineAvailable = computed(() => paymentConfig.value?.provider === 'stripe'
  && Boolean(paymentConfig.value.publishable_key && paymentConfig.value.connected_account_id))

onMounted(async () => {
  await Promise.all([
    refresh().catch(() => undefined),
    api.get<PaymentConfiguration>('payment-config')
      .then((config) => { paymentConfig.value = config })
      .catch(() => { paymentConfig.value = null }),
    api.get<{ user: CheckoutCustomer }>('account')
      .then((account) => {
        customer.value = account.user
        form.email = account.user.email
        form.phone = account.user.phone || ''
      })
      .catch(() => { customer.value = null })
      .finally(() => { accountLoading.value = false }),
  ])
  await nextTick()
  loadHumanChallenge()
})

watch([form, paymentMethod], () => {
  if (stage.value === 'details') checkoutKey.value = ''
}, { deep: true })

async function startCheckout() {
  if (!customer.value) {
    error.value = 'Sign in to your account before placing an order.'
    return
  }
  if (!accountVerified.value) {
    error.value = 'Verify your email address in account settings before placing an order.'
    return
  }
  if (!cart.value?.items.length) {
    error.value = 'Your bag is empty.'
    return
  }
  if (!form.country_code) {
    error.value = 'Choose a country from the country list.'
    return
  }
  if (paymentMethod.value === 'online' && !onlineAvailable.value) {
    error.value = 'Online card payment is not configured for this store yet. Choose cash on delivery.'
    return
  }

  pending.value = true
  error.value = ''
  try {
    if (!humanVerified.value) throw new Error('Verify the CAPTCHA before placing your order.')

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
      customer_phone: customer.value.phone,
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
    humanToken.value = ''
    humanVerified.value = false
    resetHumanChallenge()
  } finally {
    pending.value = false
  }
}

function selectCountry(country: { code: string; name: string }) {
  form.country_code = country.code
  countryQuery.value = country.name
  countryOpen.value = false
}

function chooseFirstCountry() {
  const country = filteredCountries.value[0]
  if (country) selectCountry(country)
}

function closeCountryList() {
  window.setTimeout(() => { countryOpen.value = false }, 150)
}

async function verifyHuman() {
  const responseToken = humanDriver.value === 'demo' ? captchaToken.value : humanToken.value
  if (!responseToken || (humanDriver.value === 'demo' && !captchaAnswer.value.trim())) return
  checkingHuman.value = true
  humanError.value = ''
  try {
    await api.send('verification/human', 'POST', {
      action: 'checkout',
      response_token: responseToken,
      ...(humanDriver.value === 'demo' ? { answer: captchaAnswer.value } : {}),
    })
    humanVerified.value = true
  } catch (failure) {
    humanVerified.value = false
    humanError.value = apiErrorMessage(failure)
  } finally {
    checkingHuman.value = false
  }
}

function loadHumanChallenge() {
  if (import.meta.server) return
  if (humanDriver.value === 'demo') {
    api.send<{ challenge_token: string; image: string }>('verification/human/challenge', 'POST', { action: 'checkout' })
      .then((challenge) => { captchaToken.value = challenge.challenge_token; captchaImage.value = challenge.image })
      .catch((failure) => { humanError.value = apiErrorMessage(failure) })
    return
  }

  const isTurnstile = humanDriver.value === 'turnstile'
  const siteKey = isTurnstile ? runtimeConfig.public.turnstileSiteKey : runtimeConfig.public.hcaptchaSiteKey
  if (!siteKey) {
    humanError.value = `This store needs ${isTurnstile ? 'Turnstile' : 'hCaptcha'} site-key configuration before checkout can continue.`
    return
  }

  const render = () => {
    const widget = isTurnstile ? window.turnstile : window.hcaptcha
    if (!widget) return
    humanWidgetId = widget.render('#checkout-human-check', {
      sitekey: siteKey,
      ...(isTurnstile ? { action: 'checkout' } : {}),
      callback: (token: string) => { humanToken.value = token; humanVerified.value = false; humanError.value = '' },
      'expired-callback': () => { humanToken.value = ''; humanVerified.value = false },
      'error-callback': () => { humanToken.value = ''; humanVerified.value = false },
    })
  }

  if ((isTurnstile && window.turnstile) || (!isTurnstile && window.hcaptcha)) {
    render()
    return
  }

  const scriptId = isTurnstile ? 'turnstile-api' : 'hcaptcha-api'
  const existingScript = document.getElementById(scriptId) as HTMLScriptElement | null
  if (existingScript) {
    existingScript.addEventListener('load', render, { once: true })
    return
  }
  const script = document.createElement('script')
  script.id = scriptId
  script.src = isTurnstile
    ? 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    : 'https://js.hcaptcha.com/1/api.js?render=explicit'
  script.async = true
  script.defer = true
  script.onload = render
  script.onerror = () => { humanError.value = 'The human verification challenge could not be loaded.' }
  document.head.appendChild(script)
}

function resetHumanChallenge() {
  if (!humanWidgetId) return
  if (humanDriver.value === 'turnstile') window.turnstile?.reset?.(humanWidgetId)
  else window.hcaptcha?.reset?.(humanWidgetId)
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

    <div v-if="stage === 'details' && !accountLoading && !customer" class="checkout-verification-note" role="status">
      <strong>Sign in to place your order</strong>
      <p>Orders require a customer account with a verified email address.</p>
      <div><NuxtLink class="button button--dark" to="/auth/login?redirect=%2Fcheckout%2Faddress">Sign in</NuxtLink><NuxtLink class="underlined-link" to="/auth/register?redirect=%2Fcheckout%2Faddress">Create account</NuxtLink></div>
    </div>
    <div v-else-if="stage === 'details' && customer && !accountVerified" class="checkout-verification-note" role="status">
      <strong>Verify your account before ordering</strong>
      <p>Email: {{ customer.email_verified ? 'verified' : 'needs verification' }}</p>
      <NuxtLink class="button button--dark" to="/account/settings">Verify email</NuxtLink>
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
          <label class="form-field"><span>Email address</span><input v-model.trim="form.email" type="email" autocomplete="email" readonly required></label>
          <label class="form-field"><span>Verified phone number</span><input v-model.trim="form.phone" type="tel" autocomplete="tel" readonly required></label>
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
            <div class="country-picker">
              <label class="form-field"><span>Country</span><input v-model="countryQuery" role="combobox" aria-autocomplete="list" :aria-expanded="countryOpen" aria-controls="checkout-country-options" autocomplete="off" required placeholder="Search countries" @focus="countryOpen = true" @input="form.country_code = ''; countryOpen = true" @keydown.enter.prevent="chooseFirstCountry" @keydown.esc="countryOpen = false" @blur="closeCountryList"></label>
              <div v-if="countryOpen" id="checkout-country-options" class="country-picker__options" role="listbox">
                <button v-for="country in filteredCountries" :key="country.code" class="country-picker__option" type="button" role="option" :aria-selected="form.country_code === country.code" @mousedown.prevent="selectCountry(country)">{{ country.name }} <span>{{ country.code }}</span></button>
                <p v-if="!filteredCountries.length" class="country-picker__empty">No matching countries</p>
              </div>
            </div>
          </div>
        </section>
        <section class="checkout-panel">
          <h2>Human verification</h2>
          <div v-if="humanDriver === 'demo'" class="register-human-demo"><img v-if="captchaImage" :src="captchaImage" alt="CAPTCHA word challenge"><label class="form-field"><span>Enter the letters shown</span><input v-model="captchaAnswer" autocomplete="off" autocapitalize="characters" maxlength="6" required :disabled="humanVerified"></label></div>
          <div v-else id="checkout-human-check" class="register-human-check" />
          <button class="button button--outline code-button" type="button" :disabled="checkingHuman || humanVerified || (humanDriver === 'demo' && (!captchaToken || !captchaAnswer.trim())) || (humanDriver !== 'demo' && !humanToken)" @click="verifyHuman">{{ checkingHuman ? 'Checking…' : humanVerified ? 'Verified ✓' : 'Verify code' }}</button>
          <p v-if="humanVerified" class="success-box" role="status">Human verification complete.</p>
          <p v-if="humanError" class="error-box" role="alert">{{ humanError }}</p>
          <p class="checkout-small-print">Complete this check to protect the store from automated orders.</p>
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
      <button class="button button--dark button--wide" type="submit" :disabled="pending || (stage === 'details' && (!cart?.items.length || !accountVerified || accountLoading || !humanVerified))">
        {{ pending ? (stage === 'payment' ? 'Processing payment…' : 'Placing order…') : stage === 'payment' ? 'Pay securely' : paymentMethod === 'cash_on_delivery' ? 'Place order' : 'Continue to payment' }}
      </button>
    </form>
  </section>
</template>

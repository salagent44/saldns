<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">SSL/TLS Checker</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Check SSL certificate details, expiry, chain, and supported TLS versions.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Hostname</label>
          <input
            v-model="host"
            type="text"
            placeholder="e.g. example.com"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="check"
            @focus="showHistory = true"
            @blur="hideHistory"
          />
          <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-48 overflow-y-auto shadow-lg dark:shadow-none">
            <button
              v-for="item in history"
              :key="item"
              @mousedown.prevent="host = item; showHistory = false; check()"
              class="block w-full text-left px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
              {{ item }}
            </button>
          </div>
        </div>
        <div class="w-full sm:w-28">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Port</label>
          <input
            v-model.number="port"
            type="number"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
          />
        </div>
        <div class="flex items-end">
          <button
            @click="check"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 text-gray-900 dark:text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Checking...' : 'Check' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-500 dark:text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <template v-if="result">
      <!-- Status Banner -->
      <div
        :class="[
          'rounded-xl border p-4 flex items-center gap-3',
          statusBanner.classes
        ]"
      >
        <span :class="['w-3 h-3 rounded-full shrink-0', statusBanner.dot]"></span>
        <div>
          <div class="font-medium text-sm">{{ statusBanner.title }}</div>
          <div class="text-xs mt-0.5 opacity-75">{{ statusBanner.subtitle }}</div>
        </div>
      </div>

      <!-- Certificate Details -->
      <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Certificate</h3>
        <div class="space-y-2">
          <div v-for="(value, key) in certFields" :key="key" class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-4">
            <span class="text-sm text-gray-400 dark:text-gray-500 sm:w-44 shrink-0">{{ key }}</span>
            <span class="text-sm text-gray-900 dark:text-white font-mono break-all">{{ value }}</span>
          </div>
        </div>
      </div>

      <!-- SANs -->
      <div v-if="result.sans && result.sans.length" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">
          Subject Alternative Names
          <span class="ml-2 text-gray-300 dark:text-gray-600 normal-case font-normal">{{ result.sans.length }}</span>
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
          <div
            v-for="(san, i) in result.sans"
            :key="i"
            class="text-sm text-gray-900 dark:text-white font-mono"
          >
            {{ san }}
          </div>
        </div>
      </div>

      <!-- TLS Protocols -->
      <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">TLS Protocol Support</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div
            v-for="p in result.protocols"
            :key="p.version"
            :class="[
              'rounded-lg px-4 py-3 text-center',
              p.supported
                ? isOldTls(p.version)
                  ? 'bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/30'
                  : 'bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800/30'
                : 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700'
            ]"
          >
            <div class="text-sm font-mono font-medium" :class="[
              p.supported
                ? isOldTls(p.version) ? 'text-amber-700 dark:text-amber-400' : 'text-green-700 dark:text-green-400'
                : 'text-gray-400 dark:text-gray-500'
            ]">
              {{ p.version }}
            </div>
            <div class="text-xs mt-1" :class="[
              p.supported
                ? isOldTls(p.version) ? 'text-amber-600 dark:text-amber-500' : 'text-green-600 dark:text-green-500'
                : 'text-gray-400 dark:text-gray-600'
            ]">
              {{ p.supported ? (isOldTls(p.version) ? 'Deprecated' : 'Supported') : 'Not Supported' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Certificate Chain -->
      <div v-if="result.chain && result.chain.length" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Certificate Chain</h3>
        <div class="space-y-3">
          <div
            v-for="(c, i) in result.chain"
            :key="i"
            class="flex items-start gap-3"
          >
            <div class="flex flex-col items-center shrink-0 pt-1">
              <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600"></span>
              <span v-if="i < result.chain.length - 1" class="w-px h-8 bg-gray-200 dark:bg-gray-700"></span>
            </div>
            <div class="text-sm">
              <div class="text-gray-900 dark:text-white font-mono break-all">{{ c.subject }}</div>
              <div class="text-gray-400 dark:text-gray-500 text-xs mt-0.5">Issued by: {{ c.issuer }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const host = ref('')
const port = ref(443)
const result = ref(null)
const loading = ref(false)
const error = ref('')
const showHistory = ref(false)

const HISTORY_KEY = 'dns-tools-ssl-history'
const history = ref(JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'))

function addToHistory(q) {
  const list = history.value.filter(h => h !== q)
  list.unshift(q)
  if (list.length > 20) list.pop()
  history.value = list
  localStorage.setItem(HISTORY_KEY, JSON.stringify(list))
}

function hideHistory() {
  setTimeout(() => showHistory.value = false, 150)
}

function isOldTls(version) {
  return version === 'TLS 1.0' || version === 'TLS 1.1'
}

const statusBanner = computed(() => {
  if (!result.value?.certificate) return {}
  const days = result.value.certificate.days_remaining
  if (days === undefined) return { title: 'Certificate Found', subtitle: 'Could not determine expiry', classes: 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300', dot: 'bg-gray-400' }
  if (days < 0) return { title: 'Certificate Expired', subtitle: `Expired ${Math.abs(days)} days ago`, classes: 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800/30 text-red-700 dark:text-red-400', dot: 'bg-red-500' }
  if (days <= 30) return { title: 'Certificate Expiring Soon', subtitle: `${days} days remaining`, classes: 'bg-amber-50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/30 text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' }
  return { title: 'Certificate Valid', subtitle: `${days} days remaining`, classes: 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800/30 text-green-700 dark:text-green-400', dot: 'bg-green-500' }
})

const certFields = computed(() => {
  if (!result.value?.certificate) return {}
  const c = result.value.certificate
  const fields = {}
  if (c.common_name) fields['Common Name'] = c.common_name
  if (c.issuer_org) fields['Issuer'] = c.issuer_cn ? `${c.issuer_org} (${c.issuer_cn})` : c.issuer_org
  else if (c.issuer_cn) fields['Issuer'] = c.issuer_cn
  if (c.not_before) fields['Valid From'] = c.not_before
  if (c.not_after) fields['Valid Until'] = c.not_after
  if (c.days_remaining !== undefined) fields['Days Remaining'] = c.days_remaining.toString()
  if (c.signature_algorithm) fields['Signature'] = c.signature_algorithm
  if (c.key_algorithm) fields['Key Type'] = c.key_size ? `${c.key_algorithm} (${c.key_size})` : c.key_algorithm
  if (c.serial) fields['Serial'] = c.serial
  if (c.fingerprint_sha256) fields['SHA-256 Fingerprint'] = c.fingerprint_sha256
  return fields
})

async function check() {
  const h = host.value.trim().replace(/^https?:\/\//, '').replace(/\/.*$/, '').replace(/:.*$/, '')
  if (!h) {
    error.value = 'Please enter a hostname'
    return
  }
  loading.value = true
  error.value = ''
  result.value = null
  showHistory.value = false
  try {
    const res = await fetch('/api/ssl.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ host: h, port: port.value })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { result.value = data; addToHistory(h) }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}
</script>

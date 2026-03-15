<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">HTTP Headers</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Inspect HTTP response headers for any URL. Follows redirects and highlights security headers.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">URL</label>
          <input
            v-model="url"
            type="text"
            placeholder="e.g. example.com or https://example.com"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="fetchHeaders"
            @focus="showHistory = true"
            @blur="hideHistory"
          />
          <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-48 overflow-y-auto shadow-lg dark:shadow-none">
            <button
              v-for="item in history"
              :key="item"
              @mousedown.prevent="url = item; showHistory = false; fetchHeaders()"
              class="block w-full text-left px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
              {{ item }}
            </button>
          </div>
        </div>
        <div class="flex items-end">
          <button
            @click="fetchHeaders"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 text-gray-900 dark:text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Fetching...' : 'Fetch' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-500 dark:text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="result" class="space-y-4">
      <div
        v-for="(hop, idx) in result.hops"
        :key="idx"
        class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6"
      >
        <div class="flex items-center gap-3 mb-4">
          <span v-if="result.hops.length > 1" class="px-2.5 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-md">
            {{ idx < result.hops.length - 1 ? 'Redirect ' + (idx + 1) : 'Final Response' }}
          </span>
          <span
            :class="[
              'px-2.5 py-1 text-xs font-bold rounded-md',
              statusColor(hop.status_line)
            ]"
          >
            {{ hop.status_line }}
          </span>
        </div>

        <div class="overflow-auto rounded-lg">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-4 py-2 font-medium w-56">Header</th>
                <th class="px-4 py-2 font-medium">Value</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(h, i) in hop.headers"
                :key="i"
                :class="[
                  'border-t border-gray-100 dark:border-gray-800 transition-colors',
                  isSecurityHeader(h.name)
                    ? 'bg-blue-50 dark:bg-blue-900/10'
                    : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
                ]"
              >
                <td class="px-4 py-2 font-mono text-gray-600 dark:text-gray-300 align-top">
                  <span class="flex items-center gap-2">
                    {{ h.name }}
                    <span v-if="isSecurityHeader(h.name)" class="inline-block w-1.5 h-1.5 rounded-full bg-blue-400 dark:bg-blue-500 shrink-0" title="Security header"></span>
                  </span>
                </td>
                <td class="px-4 py-2 text-gray-900 dark:text-white font-mono break-all">{{ h.value }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Security Summary -->
      <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Security Headers Summary</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div
            v-for="sh in securityChecks"
            :key="sh.name"
            class="flex items-center gap-2 text-sm"
          >
            <span
              :class="[
                'w-2 h-2 rounded-full shrink-0',
                sh.present ? 'bg-green-500' : 'bg-red-400 dark:bg-red-500'
              ]"
            ></span>
            <span :class="sh.present ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-500'">
              {{ sh.name }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const url = ref('')
const result = ref(null)
const loading = ref(false)
const error = ref('')
const showHistory = ref(false)

const HISTORY_KEY = 'dns-tools-headers-history'
const history = ref(JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'))

const securityHeaderNames = [
  'Strict-Transport-Security',
  'Content-Security-Policy',
  'X-Frame-Options',
  'X-Content-Type-Options',
  'X-XSS-Protection',
  'Referrer-Policy',
  'Permissions-Policy',
]

function isSecurityHeader(name) {
  if (!result.value) return false
  return result.value.security_headers.includes(name.toLowerCase())
}

function statusColor(statusLine) {
  if (/\s2\d{2}/.test(statusLine)) return 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
  if (/\s3\d{2}/.test(statusLine)) return 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
  if (/\s4\d{2}/.test(statusLine)) return 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
  if (/\s5\d{2}/.test(statusLine)) return 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
  return 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200'
}

const securityChecks = computed(() => {
  if (!result.value) return []
  // Check final response hop
  const finalHop = result.value.hops[result.value.hops.length - 1]
  const presentHeaders = finalHop.headers.map(h => h.name.toLowerCase())
  return securityHeaderNames.map(name => ({
    name,
    present: presentHeaders.includes(name.toLowerCase())
  }))
})

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

async function fetchHeaders() {
  if (!url.value.trim()) {
    error.value = 'Please enter a URL'
    return
  }
  loading.value = true
  error.value = ''
  result.value = null
  showHistory.value = false
  try {
    const res = await fetch('/api/headers.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url: url.value.trim() })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { result.value = data; addToHistory(url.value.trim()) }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}
</script>

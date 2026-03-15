<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">WHOIS Lookup</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Look up registration and ownership information for domains or IP addresses.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Domain or IP</label>
          <input
            v-model="query"
            type="text"
            placeholder="e.g. example.com or 8.8.8.8"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="lookup"
            @focus="showHistory = true"
            @blur="hideHistory"
          />
          <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-48 overflow-y-auto shadow-lg dark:shadow-none">
            <button
              v-for="item in history"
              :key="item"
              @mousedown.prevent="query = item; showHistory = false; lookup()"
              class="block w-full text-left px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
              {{ item }}
            </button>
          </div>
        </div>
        <div class="flex items-end">
          <button
            @click="lookup"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 text-gray-900 dark:text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Looking up...' : 'Lookup' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-500 dark:text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="result" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">WHOIS Result</h3>
        <button
          @click="copyResult"
          class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
        >
          {{ copied ? 'Copied!' : 'Copy Raw' }}
        </button>
      </div>

      <!-- Parsed fields -->
      <div v-if="result.parsed && Object.keys(result.parsed).length" class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div
            v-for="(value, key) in result.parsed"
            :key="key"
            class="bg-gray-50 dark:bg-gray-800 rounded-lg px-4 py-3"
          >
            <div class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">{{ key }}</div>
            <div class="text-gray-900 dark:text-white text-sm font-mono break-all">{{ value }}</div>
          </div>
        </div>
      </div>

      <!-- Raw output -->
      <details class="group">
        <summary class="text-sm text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
          Raw WHOIS output
        </summary>
        <pre class="mt-3 bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-sm text-gray-600 dark:text-gray-300 font-mono overflow-auto max-h-[50vh] whitespace-pre-wrap">{{ result.raw }}</pre>
      </details>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const query = ref('')
const result = ref(null)
const loading = ref(false)
const error = ref('')
const copied = ref(false)
const showHistory = ref(false)

const HISTORY_KEY = 'dns-tools-whois-history'
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

async function lookup() {
  if (!query.value.trim()) {
    error.value = 'Please enter a domain or IP address'
    return
  }
  loading.value = true
  error.value = ''
  result.value = null
  showHistory.value = false
  try {
    const res = await fetch('/api/whois.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query: query.value.trim() })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { result.value = data; addToHistory(query.value.trim()) }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}

function copyResult() {
  if (result.value?.raw) {
    navigator.clipboard.writeText(result.value.raw)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">WHOIS Lookup</h2>
      <p class="text-gray-400 text-sm mb-4">
        Look up registration and ownership information for domains or IP addresses.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm text-gray-400 mb-1">Domain or IP</label>
          <input
            v-model="query"
            type="text"
            placeholder="e.g. example.com or 8.8.8.8"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
            @keyup.enter="lookup"
          />
        </div>
        <div class="flex items-end">
          <button
            @click="lookup"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-800 disabled:text-blue-400 text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Looking up...' : 'Lookup' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="result" class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-400">WHOIS Result</h3>
        <button
          @click="copyResult"
          class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
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
            class="bg-gray-800 rounded-lg px-4 py-3"
          >
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">{{ key }}</div>
            <div class="text-white text-sm font-mono break-all">{{ value }}</div>
          </div>
        </div>
      </div>

      <!-- Raw output -->
      <details class="group">
        <summary class="text-sm text-gray-400 cursor-pointer hover:text-gray-300 transition-colors">
          Raw WHOIS output
        </summary>
        <pre class="mt-3 bg-gray-800 rounded-lg p-4 text-sm text-gray-300 font-mono overflow-auto max-h-[50vh] whitespace-pre-wrap">{{ result.raw }}</pre>
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

async function lookup() {
  if (!query.value.trim()) {
    error.value = 'Please enter a domain or IP address'
    return
  }

  loading.value = true
  error.value = ''
  result.value = null

  try {
    const res = await fetch('/api/whois.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query: query.value.trim() })
    })
    const data = await res.json()
    if (data.error) {
      error.value = data.error
    } else {
      result.value = data
    }
  } catch (e) {
    error.value = 'Failed to connect to API'
  } finally {
    loading.value = false
  }
}

function copyResult() {
  if (result.value?.raw) {
    navigator.clipboard.writeText(result.value.raw)
    copied.value = true
    setTimeout(() => copied.value = false, 2000)
  }
}
</script>

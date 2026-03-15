<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reverse DNS</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Look up the hostname associated with an IP address using PTR records.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">IP Address</label>
          <input
            v-model="ip"
            type="text"
            placeholder="e.g. 8.8.8.8"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="lookup"
            @focus="showHistory = true"
            @blur="hideHistory"
          />
          <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-48 overflow-y-auto shadow-lg dark:shadow-none">
            <button
              v-for="item in history"
              :key="item"
              @mousedown.prevent="ip = item; showHistory = false; lookup()"
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

    <!-- PTR Results -->
    <div v-if="result" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <div class="flex items-center gap-3 mb-4">
        <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">PTR Records</h3>
        <span v-if="result.query_time" class="text-xs text-gray-400 dark:text-gray-600">{{ result.query_time }}</span>
      </div>

      <div v-if="result.ptr_records.length" class="overflow-auto rounded-lg">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr class="text-left text-gray-500 dark:text-gray-400">
              <th class="px-4 py-2 font-medium">Name</th>
              <th class="px-4 py-2 font-medium">TTL</th>
              <th class="px-4 py-2 font-medium">Hostname</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(rec, i) in result.ptr_records"
              :key="i"
              class="border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
            >
              <td class="px-4 py-2 text-gray-600 dark:text-gray-300 font-mono">{{ rec.name }}</td>
              <td class="px-4 py-2 text-gray-400 dark:text-gray-500 font-mono">{{ rec.ttl }}</td>
              <td class="px-4 py-2 text-gray-900 dark:text-white font-mono break-all">{{ rec.value }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-gray-400 dark:text-gray-500 text-sm">No PTR records found for this IP.</p>
    </div>

    <!-- Host Output -->
    <div v-if="result" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Host Command Output</h3>
      <pre class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-sm text-gray-600 dark:text-gray-300 font-mono overflow-auto max-h-[30vh] whitespace-pre-wrap">{{ result.host_output }}</pre>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const ip = ref('')
const result = ref(null)
const loading = ref(false)
const error = ref('')
const showHistory = ref(false)

const HISTORY_KEY = 'dns-tools-reverse-history'
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
  if (!ip.value.trim()) {
    error.value = 'Please enter an IP address'
    return
  }
  loading.value = true
  error.value = ''
  result.value = null
  showHistory.value = false
  try {
    const res = await fetch('/api/reverse.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ip: ip.value.trim() })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { result.value = data; addToHistory(ip.value.trim()) }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}
</script>

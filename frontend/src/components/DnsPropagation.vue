<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">DNS Propagation</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Check how a DNS record resolves across multiple public DNS servers worldwide.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Domain</label>
          <input
            v-model="domain"
            type="text"
            placeholder="e.g. example.com"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="check"
          />
        </div>
        <div class="w-full sm:w-36">
          <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Record Type</label>
          <select
            v-model="recordType"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
          >
            <option v-for="rt in recordTypes" :key="rt" :value="rt">{{ rt }}</option>
          </select>
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
    <div v-if="results" class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
          Results for <span class="font-mono">{{ results.domain }}</span>
          <span class="ml-2 px-2.5 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-md">{{ results.type }}</span>
        </h3>
        <span
          :class="[
            'px-2.5 py-1 text-xs font-medium rounded-md',
            results.consistent
              ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
              : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
          ]"
        >
          {{ results.consistent ? 'Consistent' : 'Differences detected' }}
        </span>
      </div>

      <div class="overflow-auto rounded-lg">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr class="text-left text-gray-500 dark:text-gray-400">
              <th class="px-4 py-2 font-medium">Server</th>
              <th class="px-4 py-2 font-medium">IP</th>
              <th class="px-4 py-2 font-medium">Result</th>
              <th class="px-4 py-2 font-medium">Query Time</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, i) in results.results"
              :key="i"
              :class="[
                'border-t border-gray-100 dark:border-gray-800 transition-colors',
                !results.consistent && row.result !== mostCommonResult
                  ? 'bg-amber-50 dark:bg-amber-900/10'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
              ]"
            >
              <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ row.server }}</td>
              <td class="px-4 py-2 text-gray-400 dark:text-gray-500 font-mono">{{ row.ip }}</td>
              <td class="px-4 py-2 text-gray-900 dark:text-white font-mono break-all">{{ row.result }}</td>
              <td class="px-4 py-2 text-gray-400 dark:text-gray-500 font-mono">{{ row.query_time || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const domain = ref('')
const recordType = ref('A')
const results = ref(null)
const loading = ref(false)
const error = ref('')

const recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA']

const mostCommonResult = computed(() => {
  if (!results.value) return ''
  const counts = {}
  for (const r of results.value.results) {
    counts[r.result] = (counts[r.result] || 0) + 1
  }
  let max = 0, val = ''
  for (const [k, v] of Object.entries(counts)) {
    if (v > max) { max = v; val = k }
  }
  return val
})

async function check() {
  if (!domain.value.trim()) {
    error.value = 'Please enter a domain'
    return
  }
  loading.value = true
  error.value = ''
  results.value = null
  try {
    const res = await fetch('/api/propagation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        domain: domain.value.trim(),
        type: recordType.value
      })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { results.value = data }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}
</script>

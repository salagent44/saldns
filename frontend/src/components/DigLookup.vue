<template>
  <div class="space-y-6">
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Dig Lookup</h2>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
        Query DNS records for any domain. Select one or more record types.
      </p>

      <div class="flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row gap-4">
          <div class="flex-1 relative">
            <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">Domain</label>
            <input
              v-model="domain"
              type="text"
              placeholder="e.g. example.com"
              class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
              @keyup.enter="dig"
              @focus="showHistory = true"
              @blur="hideHistory"
            />
            <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden max-h-48 overflow-y-auto shadow-lg dark:shadow-none">
              <button
                v-for="item in history"
                :key="item"
                @mousedown.prevent="domain = item; showHistory = false; dig()"
                class="block w-full text-left px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              >
                {{ item }}
              </button>
            </div>
          </div>
          <div class="w-full sm:w-48">
            <label class="block text-sm text-gray-500 dark:text-gray-400 mb-1">DNS Server (optional)</label>
            <input
              v-model="server"
              type="text"
              placeholder="e.g. 8.8.8.8"
              class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-400 dark:focus:ring-gray-600 focus:border-transparent font-mono"
            />
          </div>
          <div class="flex items-end">
            <button
              @click="dig"
              :disabled="loading"
              class="w-full sm:w-auto px-6 py-2.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:text-gray-400 dark:disabled:text-gray-600 text-gray-900 dark:text-white font-medium rounded-lg transition-colors"
            >
              {{ loading ? 'Querying...' : 'Dig' }}
            </button>
          </div>
        </div>

        <!-- Record Type Selector -->
        <div>
          <div class="flex items-center gap-3 mb-2">
            <label class="text-sm text-gray-500 dark:text-gray-400">Record Types</label>
            <button
              @click="selectAll"
              class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            >
              {{ allSelected ? 'Deselect All' : 'Select All' }}
            </button>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="rt in recordTypes"
              :key="rt"
              @click="toggleType(rt)"
              :class="[
                'px-3 py-1.5 rounded-md text-xs font-medium transition-all',
                selectedTypes.includes(rt)
                  ? 'bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white'
                  : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-200/50 dark:hover:bg-gray-700/50'
              ]"
            >
              {{ rt }}
            </button>
          </div>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-500 dark:text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="results.length" class="space-y-4">
      <div
        v-for="(group, idx) in results"
        :key="idx"
        class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 p-6"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <span class="px-2.5 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-md">
              {{ group.type }}
            </span>
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ group.records.length }} record{{ group.records.length !== 1 ? 's' : '' }}
              <span v-if="group.query_time" class="ml-2 text-gray-400 dark:text-gray-600">{{ group.query_time }}</span>
            </span>
          </div>
        </div>

        <div v-if="group.records.length" class="overflow-auto rounded-lg">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-4 py-2 font-medium">Name</th>
                <th class="px-4 py-2 font-medium">TTL</th>
                <th class="px-4 py-2 font-medium">Value</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(rec, i) in group.records"
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
        <p v-else class="text-gray-400 dark:text-gray-500 text-sm">No records found</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const domain = ref('')
const server = ref('')
const selectedTypes = ref(['A', 'AAAA', 'MX', 'NS'])
const results = ref([])
const loading = ref(false)
const error = ref('')
const showHistory = ref(false)

const recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA']

const HISTORY_KEY = 'dns-tools-dig-history'
const history = ref(JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]'))

const allSelected = computed(() => selectedTypes.value.length === recordTypes.length)

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

function toggleType(rt) {
  const idx = selectedTypes.value.indexOf(rt)
  if (idx >= 0) {
    selectedTypes.value.splice(idx, 1)
  } else {
    selectedTypes.value.push(rt)
  }
}

function selectAll() {
  if (allSelected.value) {
    selectedTypes.value = []
  } else {
    selectedTypes.value = [...recordTypes]
  }
}

async function dig() {
  if (!domain.value.trim()) {
    error.value = 'Please enter a domain'
    return
  }
  if (!selectedTypes.value.length) {
    error.value = 'Please select at least one record type'
    return
  }
  loading.value = true
  error.value = ''
  results.value = []
  showHistory.value = false
  try {
    const res = await fetch('/api/dig.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        domain: domain.value.trim(),
        types: selectedTypes.value,
        server: server.value.trim() || null
      })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { results.value = data.results; addToHistory(domain.value.trim()) }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}
</script>

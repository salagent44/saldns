<template>
  <div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Dig Lookup</h2>
      <p class="text-gray-400 text-sm mb-4">
        Query DNS records for any domain. Select one or more record types.
      </p>

      <div class="flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row gap-4">
          <div class="flex-1">
            <label class="block text-sm text-gray-400 mb-1">Domain</label>
            <input
              v-model="domain"
              type="text"
              placeholder="e.g. example.com"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
              @keyup.enter="dig"
            />
          </div>
          <div class="w-full sm:w-48">
            <label class="block text-sm text-gray-400 mb-1">DNS Server (optional)</label>
            <input
              v-model="server"
              type="text"
              placeholder="e.g. 8.8.8.8"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
            />
          </div>
          <div class="flex items-end">
            <button
              @click="dig"
              :disabled="loading"
              class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-800 disabled:text-blue-400 text-white font-medium rounded-lg transition-colors"
            >
              {{ loading ? 'Querying...' : 'Dig' }}
            </button>
          </div>
        </div>

        <!-- Record Type Selector -->
        <div>
          <label class="block text-sm text-gray-400 mb-2">Record Types</label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="rt in recordTypes"
              :key="rt"
              @click="toggleType(rt)"
              :class="[
                'px-3 py-1.5 rounded-md text-xs font-medium transition-all',
                selectedTypes.includes(rt)
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-800 text-gray-400 hover:text-gray-200 hover:bg-gray-700'
              ]"
            >
              {{ rt }}
            </button>
          </div>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="results.length" class="space-y-4">
      <div
        v-for="(group, idx) in results"
        :key="idx"
        class="bg-gray-900 rounded-xl border border-gray-800 p-6"
      >
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-3">
            <span class="px-2.5 py-1 bg-blue-600/20 text-blue-400 text-xs font-bold rounded-md">
              {{ group.type }}
            </span>
            <span class="text-sm text-gray-400">
              {{ group.records.length }} record{{ group.records.length !== 1 ? 's' : '' }}
              <span v-if="group.query_time" class="ml-2 text-gray-600">{{ group.query_time }}</span>
            </span>
          </div>
        </div>

        <div v-if="group.records.length" class="overflow-auto rounded-lg">
          <table class="w-full text-sm">
            <thead class="bg-gray-800">
              <tr class="text-left text-gray-400">
                <th class="px-4 py-2 font-medium">Name</th>
                <th class="px-4 py-2 font-medium">TTL</th>
                <th class="px-4 py-2 font-medium">Value</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(rec, i) in group.records"
                :key="i"
                class="border-t border-gray-800 hover:bg-gray-800/50 transition-colors"
              >
                <td class="px-4 py-2 text-gray-300 font-mono">{{ rec.name }}</td>
                <td class="px-4 py-2 text-gray-500 font-mono">{{ rec.ttl }}</td>
                <td class="px-4 py-2 text-white font-mono break-all">{{ rec.value }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-gray-500 text-sm">No records found</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const domain = ref('')
const server = ref('')
const selectedTypes = ref(['A', 'AAAA', 'MX', 'NS'])
const results = ref([])
const loading = ref(false)
const error = ref('')

const recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SOA', 'PTR', 'SRV', 'CAA']

function toggleType(rt) {
  const idx = selectedTypes.value.indexOf(rt)
  if (idx >= 0) {
    selectedTypes.value.splice(idx, 1)
  } else {
    selectedTypes.value.push(rt)
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
    if (data.error) {
      error.value = data.error
    } else {
      results.value = data.results
    }
  } catch (e) {
    error.value = 'Failed to connect to API'
  } finally {
    loading.value = false
  }
}
</script>

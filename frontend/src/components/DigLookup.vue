<template>
  <div>
    <div class="flex flex-col gap-4">
      <div class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1 relative">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">domain</label>
          <input
            v-model="domain"
            type="text"
            placeholder="example.com"
            class="w-full"
            @keyup.enter="dig"
            @focus="showHistory = true"
            @blur="hideHistory"
          />
          <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-neutral-900 border border-neutral-800 max-h-40 overflow-auto">
            <button
              v-for="item in history"
              :key="item"
              @mousedown.prevent="domain = item; showHistory = false; dig()"
              class="block w-full text-left px-3 py-1.5 text-sm font-mono text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-colors"
            >
              {{ item }}
            </button>
          </div>
        </div>
        <div class="w-full sm:w-40">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">server</label>
          <input
            v-model="server"
            type="text"
            placeholder="8.8.8.8"
            class="w-full"
          />
        </div>
        <button
          @click="dig"
          :disabled="loading"
          class="text-sm font-mono text-neutral-400 hover:text-neutral-100 disabled:text-neutral-700 transition-colors pb-2"
        >
          {{ loading ? '...' : 'go' }}
        </button>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="rt in recordTypes"
            :key="rt"
            @click="toggleType(rt)"
            :class="[
              'px-2 py-0.5 text-xs font-mono transition-colors',
              selectedTypes.includes(rt)
                ? 'text-neutral-100'
                : 'text-neutral-700 hover:text-neutral-500'
            ]"
          >
            {{ rt }}
          </button>
        </div>
        <button
          @click="selectAll"
          class="text-xs font-mono text-neutral-700 hover:text-neutral-400 transition-colors ml-1"
        >
          {{ allSelected ? 'none' : 'all' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mt-4 text-red-400/80 text-sm font-mono">{{ error }}</p>

    <div v-if="results.length" class="mt-8 space-y-6">
      <div v-for="(group, idx) in results" :key="idx">
        <div class="flex items-center gap-3 mb-2">
          <span class="text-xs font-mono text-neutral-100">{{ group.type }}</span>
          <span class="text-xs font-mono text-neutral-700">
            {{ group.records.length }} record{{ group.records.length !== 1 ? 's' : '' }}
            <span v-if="group.query_time" class="ml-1">{{ group.query_time }}</span>
          </span>
        </div>

        <div v-if="group.records.length" class="overflow-auto">
          <table class="w-full text-sm font-mono">
            <thead>
              <tr class="text-left text-neutral-700 text-xs">
                <th class="pr-4 py-1 font-normal">name</th>
                <th class="pr-4 py-1 font-normal">ttl</th>
                <th class="py-1 font-normal">value</th>
              </tr>
            </thead>
            <tbody class="text-neutral-400">
              <tr
                v-for="(rec, i) in group.records"
                :key="i"
                class="border-t border-neutral-900"
              >
                <td class="pr-4 py-1.5">{{ rec.name }}</td>
                <td class="pr-4 py-1.5 text-neutral-700">{{ rec.ttl }}</td>
                <td class="py-1.5 text-neutral-200 break-all">{{ rec.value }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="text-neutral-700 text-xs font-mono">no records</p>
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
    error.value = 'enter a domain'
    return
  }
  if (!selectedTypes.value.length) {
    error.value = 'select at least one record type'
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
    if (data.error) {
      error.value = data.error
    } else {
      results.value = data.results
      addToHistory(domain.value.trim())
    }
  } catch (e) {
    error.value = 'failed to connect'
  } finally {
    loading.value = false
  }
}
</script>

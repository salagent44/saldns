<template>
  <div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Subnet Calculator</h2>

      <!-- Mode toggle -->
      <div class="flex gap-2 mb-4">
        <button
          @click="mode = 'split'"
          :class="[
            'px-3 py-1.5 rounded-md text-xs font-medium transition-colors',
            mode === 'split' ? 'bg-gray-700 text-white' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-800'
          ]"
        >Split CIDR</button>
        <button
          @click="mode = 'range'"
          :class="[
            'px-3 py-1.5 rounded-md text-xs font-medium transition-colors',
            mode === 'range' ? 'bg-gray-700 text-white' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-800'
          ]"
        >Range to CIDR</button>
      </div>

      <!-- Split CIDR -->
      <div v-if="mode === 'split'" class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm text-gray-400 mb-1">CIDR Block</label>
          <input
            v-model="cidr"
            type="text"
            placeholder="e.g. 10.0.0.0/16"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="calculate"
          />
        </div>
        <div class="w-full sm:w-40">
          <label class="block text-sm text-gray-400 mb-1">Target Prefix</label>
          <select
            v-model="targetPrefix"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-gray-600 focus:border-transparent"
          >
            <option v-for="n in prefixOptions" :key="n" :value="n">/{{ n }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            @click="calculate"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:text-gray-600 text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Working...' : 'Calculate' }}
          </button>
        </div>
      </div>

      <!-- Range to CIDR -->
      <div v-if="mode === 'range'" class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm text-gray-400 mb-1">Start IP</label>
          <input
            v-model="rangeStart"
            type="text"
            placeholder="e.g. 192.168.0.0"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="convertRange"
          />
        </div>
        <div class="flex-1">
          <label class="block text-sm text-gray-400 mb-1">End IP</label>
          <input
            v-model="rangeEnd"
            type="text"
            placeholder="e.g. 192.168.3.255"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:border-transparent font-mono"
            @keyup.enter="convertRange"
          />
        </div>
        <div class="flex items-end">
          <button
            @click="convertRange"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 disabled:text-gray-600 text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Working...' : 'Convert' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="results.length" class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-400">
          {{ results.length }} {{ results.length === 1 ? 'block' : 'subnets' }} found
        </h3>
        <button
          @click="copyAll"
          class="text-sm text-gray-400 hover:text-gray-200 transition-colors"
        >
          {{ copied ? 'Copied!' : 'Copy All' }}
        </button>
      </div>

      <div class="overflow-auto max-h-[60vh] rounded-lg">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-gray-800">
            <tr class="text-left text-gray-400">
              <th class="px-4 py-2 font-medium">#</th>
              <th class="px-4 py-2 font-medium">CIDR</th>
              <th class="px-4 py-2 font-medium">First IP</th>
              <th class="px-4 py-2 font-medium">Last IP</th>
              <th class="px-4 py-2 font-medium text-right">Hosts</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(subnet, i) in results"
              :key="i"
              class="border-t border-gray-800 hover:bg-gray-800/50 transition-colors"
            >
              <td class="px-4 py-2 text-gray-500 font-mono">{{ i + 1 }}</td>
              <td class="px-4 py-2 text-white font-mono">{{ subnet.cidr }}</td>
              <td class="px-4 py-2 text-gray-300 font-mono">{{ subnet.first_ip }}</td>
              <td class="px-4 py-2 text-gray-300 font-mono">{{ subnet.last_ip }}</td>
              <td class="px-4 py-2 text-gray-300 font-mono text-right">{{ subnet.hosts.toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const mode = ref('split')
const cidr = ref('')
const targetPrefix = ref(24)
const rangeStart = ref('')
const rangeEnd = ref('')
const results = ref([])
const loading = ref(false)
const error = ref('')
const copied = ref(false)

const prefixOptions = computed(() => {
  const match = cidr.value.match(/\/(\d+)/)
  const current = match ? parseInt(match[1]) : 8
  const opts = []
  for (let i = current + 1; i <= 32; i++) opts.push(i)
  return opts.length ? opts : [24]
})

async function calculate() {
  if (!cidr.value.trim()) {
    error.value = 'Please enter a CIDR block'
    return
  }
  loading.value = true
  error.value = ''
  results.value = []
  try {
    const res = await fetch('/api/subnet.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cidr: cidr.value.trim(), prefix: targetPrefix.value })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { results.value = data.subnets }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}

async function convertRange() {
  if (!rangeStart.value.trim() || !rangeEnd.value.trim()) {
    error.value = 'Please enter start and end IPs'
    return
  }
  loading.value = true
  error.value = ''
  results.value = []
  try {
    const res = await fetch('/api/range2cidr.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ start: rangeStart.value.trim(), end: rangeEnd.value.trim() })
    })
    const data = await res.json()
    if (data.error) { error.value = data.error } else { results.value = data.cidrs }
  } catch (e) { error.value = 'Failed to connect to API' }
  finally { loading.value = false }
}

function copyAll() {
  const text = results.value.map(s => s.cidr).join('\n')
  navigator.clipboard.writeText(text)
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}
</script>

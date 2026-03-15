<template>
  <div class="space-y-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <h2 class="text-lg font-semibold text-white mb-4">Subnet Calculator</h2>
      <p class="text-gray-400 text-sm mb-4">
        Enter a CIDR block and select a target prefix length to split it into child ranges.
      </p>

      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm text-gray-400 mb-1">CIDR Block</label>
          <input
            v-model="cidr"
            type="text"
            placeholder="e.g. 10.0.0.0/16"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono"
            @keyup.enter="calculate"
          />
        </div>
        <div class="w-full sm:w-40">
          <label class="block text-sm text-gray-400 mb-1">Target Prefix</label>
          <select
            v-model="targetPrefix"
            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option v-for="n in prefixOptions" :key="n" :value="n">/{{ n }}</option>
          </select>
        </div>
        <div class="flex items-end">
          <button
            @click="calculate"
            :disabled="loading"
            class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:bg-blue-800 disabled:text-blue-400 text-white font-medium rounded-lg transition-colors"
          >
            {{ loading ? 'Calculating...' : 'Calculate' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="mt-3 text-red-400 text-sm">{{ error }}</p>
    </div>

    <!-- Results -->
    <div v-if="results.length" class="bg-gray-900 rounded-xl border border-gray-800 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-gray-400">
          {{ results.length }} subnets found
        </h3>
        <button
          @click="copyAll"
          class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
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

const cidr = ref('')
const targetPrefix = ref(24)
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
    if (data.error) {
      error.value = data.error
    } else {
      results.value = data.subnets
    }
  } catch (e) {
    error.value = 'Failed to connect to API'
  } finally {
    loading.value = false
  }
}

function copyAll() {
  const text = results.value.map(s => s.cidr).join('\n')
  navigator.clipboard.writeText(text)
  copied.value = true
  setTimeout(() => copied.value = false, 2000)
}
</script>

<template>
  <div>
    <!-- Mode toggle -->
    <div class="flex gap-4 mb-6 text-xs font-mono">
      <button
        @click="mode = 'split'"
        :class="mode === 'split' ? 'text-neutral-100' : 'text-neutral-700 hover:text-neutral-400'"
        class="transition-colors"
      >split cidr</button>
      <button
        @click="mode = 'range'"
        :class="mode === 'range' ? 'text-neutral-100' : 'text-neutral-700 hover:text-neutral-400'"
        class="transition-colors"
      >range to cidr</button>
    </div>

    <!-- Split CIDR -->
    <div v-if="mode === 'split'">
      <div class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">cidr</label>
          <input
            v-model="cidr"
            type="text"
            placeholder="10.0.0.0/16"
            class="w-full"
            @keyup.enter="calculate"
          />
        </div>
        <div class="w-full sm:w-32">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">target</label>
          <select v-model="targetPrefix" class="w-full">
            <option v-for="n in prefixOptions" :key="n" :value="n">/{{ n }}</option>
          </select>
        </div>
        <button
          @click="calculate"
          :disabled="loading"
          class="text-sm font-mono text-neutral-400 hover:text-neutral-100 disabled:text-neutral-700 transition-colors pb-2"
        >
          {{ loading ? '...' : 'go' }}
        </button>
      </div>
    </div>

    <!-- Range to CIDR -->
    <div v-if="mode === 'range'">
      <div class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">start ip</label>
          <input
            v-model="rangeStart"
            type="text"
            placeholder="192.168.0.0"
            class="w-full"
            @keyup.enter="convertRange"
          />
        </div>
        <div class="flex-1">
          <label class="block text-xs text-neutral-600 mb-1 font-mono">end ip</label>
          <input
            v-model="rangeEnd"
            type="text"
            placeholder="192.168.3.255"
            class="w-full"
            @keyup.enter="convertRange"
          />
        </div>
        <button
          @click="convertRange"
          :disabled="loading"
          class="text-sm font-mono text-neutral-400 hover:text-neutral-100 disabled:text-neutral-700 transition-colors pb-2"
        >
          {{ loading ? '...' : 'go' }}
        </button>
      </div>
    </div>

    <p v-if="error" class="mt-4 text-red-400/80 text-sm font-mono">{{ error }}</p>

    <div v-if="results.length" class="mt-8">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs text-neutral-600 font-mono">{{ results.length }} {{ results.length === 1 ? 'block' : 'blocks' }}</span>
        <button
          @click="copyAll"
          class="text-xs text-neutral-600 hover:text-neutral-300 font-mono transition-colors"
        >
          {{ copied ? 'copied' : 'copy' }}
        </button>
      </div>

      <div class="overflow-auto max-h-[65vh]">
        <table class="w-full text-sm font-mono">
          <thead>
            <tr class="text-left text-neutral-600 text-xs">
              <th class="pr-4 py-1.5 font-normal">#</th>
              <th class="pr-4 py-1.5 font-normal">cidr</th>
              <th class="pr-4 py-1.5 font-normal">first</th>
              <th class="pr-4 py-1.5 font-normal">last</th>
              <th class="py-1.5 font-normal text-right">hosts</th>
            </tr>
          </thead>
          <tbody class="text-neutral-400">
            <tr
              v-for="(subnet, i) in results"
              :key="i"
              class="border-t border-neutral-900 hover:text-neutral-200 transition-colors"
            >
              <td class="pr-4 py-1.5 text-neutral-700">{{ i + 1 }}</td>
              <td class="pr-4 py-1.5 text-neutral-200">{{ subnet.cidr }}</td>
              <td class="pr-4 py-1.5">{{ subnet.first_ip }}</td>
              <td class="pr-4 py-1.5">{{ subnet.last_ip }}</td>
              <td class="py-1.5 text-right text-neutral-600">{{ subnet.hosts.toLocaleString() }}</td>
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
    error.value = 'enter a cidr block'
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
    error.value = 'failed to connect'
  } finally {
    loading.value = false
  }
}

async function convertRange() {
  if (!rangeStart.value.trim() || !rangeEnd.value.trim()) {
    error.value = 'enter start and end IPs'
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
    if (data.error) {
      error.value = data.error
    } else {
      results.value = data.cidrs
    }
  } catch (e) {
    error.value = 'failed to connect'
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

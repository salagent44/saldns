<template>
  <div>
    <div class="flex flex-col sm:flex-row gap-4 items-end">
      <div class="flex-1 relative">
        <label class="block text-xs text-neutral-600 mb-1 font-mono">domain or ip</label>
        <input
          v-model="query"
          type="text"
          placeholder="example.com"
          class="w-full"
          @keyup.enter="lookup"
          @focus="showHistory = true"
          @blur="hideHistory"
        />
        <div v-if="showHistory && history.length" class="absolute z-10 top-full left-0 right-0 mt-1 bg-neutral-900 border border-neutral-800 max-h-40 overflow-auto">
          <button
            v-for="item in history"
            :key="item"
            @mousedown.prevent="query = item; showHistory = false; lookup()"
            class="block w-full text-left px-3 py-1.5 text-sm font-mono text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-colors"
          >
            {{ item }}
          </button>
        </div>
      </div>
      <button
        @click="lookup"
        :disabled="loading"
        class="text-sm font-mono text-neutral-400 hover:text-neutral-100 disabled:text-neutral-700 transition-colors pb-2"
      >
        {{ loading ? '...' : 'go' }}
      </button>
    </div>

    <p v-if="error" class="mt-4 text-red-400/80 text-sm font-mono">{{ error }}</p>

    <div v-if="result" class="mt-8">
      <div class="flex items-center justify-between mb-3">
        <span class="text-xs text-neutral-600 font-mono">whois</span>
        <button
          @click="copyResult"
          class="text-xs text-neutral-600 hover:text-neutral-300 font-mono transition-colors"
        >
          {{ copied ? 'copied' : 'copy raw' }}
        </button>
      </div>

      <div v-if="result.parsed && Object.keys(result.parsed).length" class="mb-6 space-y-1">
        <div
          v-for="(value, key) in result.parsed"
          :key="key"
          class="flex gap-4 text-sm font-mono py-1 border-b border-neutral-900"
        >
          <span class="text-neutral-600 w-40 shrink-0">{{ key }}</span>
          <span class="text-neutral-300 break-all">{{ value }}</span>
        </div>
      </div>

      <details class="group">
        <summary class="text-xs text-neutral-600 cursor-pointer hover:text-neutral-400 font-mono transition-colors">
          raw output
        </summary>
        <pre class="mt-3 text-xs text-neutral-500 font-mono overflow-auto max-h-[50vh] whitespace-pre-wrap leading-relaxed">{{ result.raw }}</pre>
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
    error.value = 'enter a domain or ip'
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
    if (data.error) {
      error.value = data.error
    } else {
      result.value = data
      addToHistory(query.value.trim())
    }
  } catch (e) {
    error.value = 'failed to connect'
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

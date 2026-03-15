<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-950">
    <header class="border-b border-gray-200 dark:border-gray-800/50 sticky top-0 z-10 bg-gray-100/80 dark:bg-gray-950/80 backdrop-blur-sm">
      <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200 tracking-tight">DNS Tools</h1>
        <button
          @click="toggleTheme"
          class="p-2 rounded-lg text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors"
          :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        >
          <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
          </svg>
        </button>
      </div>
    </header>

    <div class="max-w-5xl mx-auto px-4 py-6">
      <!-- Tabs -->
      <div class="flex gap-1 mb-6 bg-gray-200/50 dark:bg-gray-900/50 rounded-lg p-1 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-all duration-150',
            activeTab === tab.id
              ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
              : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-200/50 dark:hover:bg-gray-800/50'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <SubnetCalculator v-if="activeTab === 'subnet'" />
      <WhoisLookup v-if="activeTab === 'whois'" />
      <DigLookup v-if="activeTab === 'dig'" />
      <DnsPropagation v-if="activeTab === 'propagation'" />
      <ReverseDns v-if="activeTab === 'reverse'" />
      <HttpHeaders v-if="activeTab === 'headers'" />
      <SslChecker v-if="activeTab === 'ssl'" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import SubnetCalculator from './components/SubnetCalculator.vue'
import WhoisLookup from './components/WhoisLookup.vue'
import DigLookup from './components/DigLookup.vue'
import DnsPropagation from './components/DnsPropagation.vue'
import ReverseDns from './components/ReverseDns.vue'
import HttpHeaders from './components/HttpHeaders.vue'
import SslChecker from './components/SslChecker.vue'

const activeTab = ref('subnet')
const tabs = [
  { id: 'subnet', label: 'Subnet Calculator' },
  { id: 'whois', label: 'WHOIS' },
  { id: 'dig', label: 'Dig' },
  { id: 'propagation', label: 'Propagation' },
  { id: 'reverse', label: 'Reverse DNS' },
  { id: 'headers', label: 'HTTP Headers' },
  { id: 'ssl', label: 'SSL/TLS' }
]

const isDark = ref(false)

function toggleTheme() {
  isDark.value = !isDark.value
  document.documentElement.classList.toggle('dark', isDark.value)
  localStorage.setItem('dns-tools-theme', isDark.value ? 'dark' : 'light')
}

onMounted(() => {
  const saved = localStorage.getItem('dns-tools-theme')
  if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    isDark.value = true
    document.documentElement.classList.add('dark')
  }
})
</script>

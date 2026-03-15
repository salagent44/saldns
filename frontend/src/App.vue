<template>
  <div class="min-h-screen bg-gray-950">
    <header class="border-b border-gray-800/50 sticky top-0 z-10 bg-gray-950/80 backdrop-blur-sm">
      <div class="max-w-5xl mx-auto px-4 py-4">
        <h1 class="text-xl font-semibold text-gray-200 tracking-tight">DNS Tools</h1>
      </div>
    </header>

    <div class="max-w-5xl mx-auto px-4 py-6">
      <!-- Tabs -->
      <div class="flex gap-1 mb-6 bg-gray-900/50 rounded-lg p-1 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-all duration-150',
            activeTab === tab.id
              ? 'bg-gray-700 text-white'
              : 'text-gray-500 hover:text-gray-300 hover:bg-gray-800/50'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <SubnetCalculator v-if="activeTab === 'subnet'" />
      <WhoisLookup v-if="activeTab === 'whois'" />
      <DigLookup v-if="activeTab === 'dig'" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import SubnetCalculator from './components/SubnetCalculator.vue'
import WhoisLookup from './components/WhoisLookup.vue'
import DigLookup from './components/DigLookup.vue'

const activeTab = ref('subnet')
const tabs = [
  { id: 'subnet', label: 'Subnet Calculator' },
  { id: 'whois', label: 'WHOIS' },
  { id: 'dig', label: 'Dig' }
]
</script>

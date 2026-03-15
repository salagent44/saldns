<template>
  <div class="min-h-screen bg-gray-950">
    <header class="border-b border-gray-800 bg-gray-900/50 backdrop-blur-sm sticky top-0 z-10">
      <div class="max-w-5xl mx-auto px-4 py-4">
        <h1 class="text-2xl font-bold text-white tracking-tight">DNS Tools</h1>
      </div>
    </header>

    <div class="max-w-5xl mx-auto px-4 py-6">
      <!-- Tabs -->
      <div class="flex gap-1 mb-6 bg-gray-900 rounded-lg p-1 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-all duration-150',
            activeTab === tab.id
              ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20'
              : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800'
          ]"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab Content -->
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

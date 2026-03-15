<template>
  <div class="min-h-screen">
    <div class="max-w-4xl mx-auto px-6 py-10">
      <h1 class="text-lg font-mono text-neutral-500 mb-8">dns-tools</h1>

      <!-- Tabs -->
      <div class="flex gap-6 mb-8 border-b border-neutral-800">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'pb-2 text-sm font-mono transition-colors -mb-px',
            activeTab === tab.id
              ? 'text-neutral-100 border-b border-neutral-100'
              : 'text-neutral-600 hover:text-neutral-400'
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
  { id: 'subnet', label: 'subnet' },
  { id: 'whois', label: 'whois' },
  { id: 'dig', label: 'dig' }
]
</script>

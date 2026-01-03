<template>
  <div class="min-h-screen bg-gray-100 py-12">
    <div class="max-w-4xl mx-auto px-4">
      <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold mb-6">Test Yahoo Finance Service</h1>
        
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Simbolo Azione (es. AAPL, GOOG, TSLA, MSFT)
          </label>
          <div class="flex gap-2">
            <input
              v-model="symbol"
              type="text"
              class="flex-1 border border-gray-300 rounded-lg px-4 py-2"
              placeholder="AAPL"
              @keyup.enter="fetchData"
            />
            <button
              @click="fetchData"
              :disabled="loading"
              class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loading ? 'Caricamento...' : 'Cerca' }}
            </button>
          </div>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
          {{ error }}
        </div>

        <div v-if="data" class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <h2 class="text-xl font-bold mb-4">Dati Azione: {{ data.symbol }}</h2>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div>
                <p class="text-sm text-gray-600">Nome</p>
                <p class="font-semibold">{{ data.name || 'N/A' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Prezzo Corrente</p>
                <p class="font-semibold text-lg">${{ data.current_price?.toFixed(2) || 'N/A' }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Variazione</p>
                <p 
                  class="font-semibold"
                  :class="data.change >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ data.change >= 0 ? '+' : '' }}${{ data.change?.toFixed(2) || 'N/A' }}
                </p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Variazione %</p>
                <p 
                  class="font-semibold"
                  :class="data.change_percent >= 0 ? 'text-green-600' : 'text-red-600'"
                >
                  {{ data.change_percent >= 0 ? '+' : '' }}{{ data.change_percent?.toFixed(2) || 'N/A' }}%
                </p>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
            <h3 class="font-bold mb-2">JSON Completo:</h3>
            <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-auto text-xs">{{ JSON.stringify(data, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const symbol = ref('');
const data = ref(null);
const error = ref(null);
const loading = ref(false);

const fetchData = async () => {
  if (!symbol.value.trim()) {
    error.value = 'Inserisci un simbolo';
    return;
  }

  loading.value = true;
  error.value = null;
  data.value = null;

  try {
    const response = await axios.post('/test-yahoo/fetch', {
      symbol: symbol.value.trim()
    });

    if (response.data.success) {
      data.value = response.data.data;
      error.value = null;
    } else {
      error.value = response.data.error || 'Impossibile recuperare i dati per questo simbolo';
      if (response.data.debug) {
        error.value += ' (Debug: ' + response.data.debug + ')';
      }
      data.value = null;
    }
  } catch (err) {
    error.value = err.response?.data?.error || err.response?.data?.message || 'Errore durante il recupero dei dati';
    if (err.response?.data?.debug) {
      error.value += ' (Debug: ' + err.response.data.debug + ')';
    }
    data.value = null;
  } finally {
    loading.value = false;
  }
};
</script>


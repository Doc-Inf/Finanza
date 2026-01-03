<!-- resources/js/Pages/Dashboard.vue -->
<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-bold">Stock Tracker</h1>
          </div>
          <div class="flex items-center gap-4">
            <Link :href="route('stocks.create')" class="btn-primary">
              Aggiungi Azione
            </Link>
            <button @click="logout" class="btn-secondary">Logout</button>
          </div>
        </div>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
      <div v-if="stocks.length === 0" class="text-center py-12">
        <p class="text-gray-500">Nessuna azione nella tua watchlist</p>
        <Link :href="route('stocks.create')" class="btn-primary mt-4 inline-block">
          Aggiungi la prima azione
        </Link>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="stock in stocks"
          :key="stock.id"
          class="bg-white rounded-lg shadow p-6"
        >
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="text-lg font-bold">{{ stock.symbol }}</h3>
              <p class="text-sm text-gray-500">{{ stock.name }}</p>
            </div>
            <button
              @click="removeStock(stock.id)"
              class="text-red-600 hover:text-red-800"
            >
              ✕
            </button>
          </div>

          <div class="space-y-2">
            <div class="text-2xl font-bold">
              {{ formatPrice(stock.current_price) }}
            </div>
            <div
              :class="[
                'text-sm font-medium',
                stock.change >= 0 ? 'text-green-600' : 'text-red-600'
              ]"
            >
              {{ stock.change >= 0 ? '+' : '' }}{{ formatPrice(stock.change) }}
              ({{ stock.change_percent?.toFixed(2) }}%)
            </div>
            
            <div class="pt-2 border-t border-gray-200">
              <div v-if="stock.pivot?.purchase_price" class="mb-2">
                <div class="text-xs text-gray-600 mb-1">
                  Prezzo di acquisto: <span class="font-semibold">${{ parseFloat(stock.pivot.purchase_price).toFixed(2) }}</span>
                </div>
                <div 
                  :class="[
                    'text-sm font-bold',
                    calculateGainLoss(stock) >= 0 ? 'text-green-600' : 'text-red-600'
                  ]"
                >
                  {{ calculateGainLoss(stock) >= 0 ? '+' : '' }}{{ formatPrice(calculateGainLoss(stock)) }}
                  ({{ calculateGainLossPercent(stock) >= 0 ? '+' : '' }}{{ calculateGainLossPercent(stock).toFixed(2) }}%)
                </div>
              </div>
              <div v-else class="text-xs text-gray-500 italic">
                Nessun prezzo di acquisto impostato
              </div>
            </div>
            
            <div class="text-xs text-gray-500 space-y-1">
              <div v-if="stock.data?.market_close_time">
                Chiusura: {{ stock.data.market_close_time }}
              </div>
              <div v-if="stock.data?.after_hours">
                <div class="text-xs font-semibold text-blue-600 mt-1">After Hours:</div>
                <div class="text-xs">
                  Prezzo: ${{ stock.data.after_hours.price?.toFixed(2) || 'N/A' }}
                </div>
                <div 
                  :class="[
                    'text-xs',
                    (stock.data.after_hours.change || 0) >= 0 ? 'text-green-600' : 'text-red-600'
                  ]"
                >
                  {{ (stock.data.after_hours.change || 0) >= 0 ? '+' : '' }}${{ stock.data.after_hours.change?.toFixed(2) || '0.00' }}
                  ({{ (stock.data.after_hours.change_percent || 0) >= 0 ? '+' : '' }}{{ stock.data.after_hours.change_percent?.toFixed(2) || '0.00' }}%)
                </div>
                <div v-if="stock.data.after_hours.time" class="text-xs text-gray-400 mt-1">
                  {{ stock.data.after_hours.time }}
                </div>
              </div>
              <div class="mt-1">
                Aggiornato: {{ formatDate(stock.last_updated) }}
              </div>
            </div>
          </div>

          <button
            @click="refreshStock(stock.id)"
            class="mt-4 w-full btn-secondary text-sm"
          >
            Aggiorna
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';

defineProps({
  stocks: Array
});

const logout = () => {
  router.post(route('logout'));
};

const removeStock = (id) => {
  if (confirm('Sei sicuro di voler rimuovere questa azione?')) {
    router.delete(route('stocks.destroy', id));
  }
};

const refreshStock = (id) => {
  router.post(route('stocks.refresh', id));
};

const formatPrice = (price) => {
  return price ? `$${parseFloat(price).toFixed(2)}` : 'N/A';
};

const formatDate = (date) => {
  return date ? new Date(date).toLocaleString('it-IT') : 'Mai';
};

const calculateGainLoss = (stock) => {
  if (!stock.pivot?.purchase_price || !stock.current_price) {
    return null;
  }
  return stock.current_price - parseFloat(stock.pivot.purchase_price);
};

const calculateGainLossPercent = (stock) => {
  if (!stock.pivot?.purchase_price || !stock.current_price) {
    return null;
  }
  const purchasePrice = parseFloat(stock.pivot.purchase_price);
  return ((stock.current_price - purchasePrice) / purchasePrice) * 100;
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition;
}

.btn-secondary {
  @apply bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 transition;
}
</style>
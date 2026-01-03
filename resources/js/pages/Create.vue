<!-- resources/js/Pages/Stocks/Create.vue -->
<template>
  <div class="min-h-screen bg-gray-100 py-12">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow p-8">
      <h2 class="text-2xl font-bold mb-6">Aggiungi Azione</h2>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Simbolo Azione (es. GOOG, AAPL, TSLA)
          </label>
          <input
            v-model="form.symbol"
            type="text"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
            placeholder="GOOG"
            required
          />
          <p v-if="form.errors.symbol" class="text-red-600 text-sm mt-1">
            {{ form.errors.symbol }}
          </p>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Prezzo di Acquisto (opzionale)
          </label>
          <input
            v-model="form.purchase_price"
            type="number"
            step="0.01"
            min="0"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
            placeholder="0.00"
          />
          <p class="text-xs text-gray-500 mt-1">
            Inserisci il prezzo a cui hai acquistato questa azione per calcolare il guadagno/perdita
          </p>
          <p v-if="form.errors.purchase_price" class="text-red-600 text-sm mt-1">
            {{ form.errors.purchase_price }}
          </p>
        </div>

        <div class="flex gap-4">
          <button type="submit" class="btn-primary flex-1">
            Aggiungi
          </button>
          <Link :href="route('dashboard')" class="btn-secondary flex-1 text-center">
            Annulla
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
  symbol: '',
  purchase_price: null
});

const submit = () => {
  form.post(route('stocks.store'));
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
<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-600 to-blue-500 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl p-10 max-w-2xl w-full">
      <h1 class="text-5xl font-bold text-gray-800 mb-4">
        🎉 Tailwind CSS v4
      </h1>
      <p class="text-xl text-gray-600 mb-8">
        Setup completato con successo!
      </p>
      
      <div class="grid grid-cols-2 gap-4 mb-8">
        <div class="bg-blue-100 rounded-2xl p-6 text-center">
          <div class="text-4xl mb-2">✓</div>
          <p class="font-bold text-gray-700">Laravel</p>
        </div>
        <div class="bg-green-100 rounded-2xl p-6 text-center">
          <div class="text-4xl mb-2">✓</div>
          <p class="font-bold text-gray-700">Inertia.js</p>
        </div>
        <div class="bg-purple-100 rounded-2xl p-6 text-center">
          <div class="text-4xl mb-2">✓</div>
          <p class="font-bold text-gray-700">Vue 3</p>
        </div>
        <div class="bg-pink-100 rounded-2xl p-6 text-center">
          <div class="text-4xl mb-2">✓</div>
          <p class="font-bold text-gray-700">Tailwind v4</p>
        </div>
      </div>

      <div class="flex gap-3">
        <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl transition-all hover:scale-105">
          Test Button
        </button>
        <button class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl transition-all hover:scale-105">
          Another Button
        </button>
      </div>

      <div class="mt-6 text-center">
        <a href="/" class="text-blue-600 hover:text-blue-700 font-medium">
          Vai alla Home →
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
// Test page - no logic needed
</script>
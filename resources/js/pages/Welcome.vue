<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl p-10 max-w-2xl w-full text-center">
      <h1 class="text-5xl font-bold text-gray-800 mb-4">
        Benvenuto in Finanza
      </h1>
      <p class="text-xl text-gray-600 mb-8">
        Gestisci e monitora le tue azioni preferite
      </p>
      
      <div class="flex gap-4 justify-center">
        <a
          href="/login"
          class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all hover:scale-105"
        >
          Login
        </a>
        <a
          href="/register"
          class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-xl transition-all hover:scale-105"
        >
          Registrati
        </a>
      </div>

      <div class="mt-8">
        <a href="/test" class="text-blue-600 hover:text-blue-700 font-medium">
          Test Tailwind →
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
// Welcome page - no logic needed
</script>


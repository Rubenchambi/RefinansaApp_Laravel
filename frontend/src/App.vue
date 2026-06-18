<script setup>
import { ref, computed } from 'vue'
// 1. 📦 IMPORTA TU COMPONENTE AQUÍ (Ajusta la ruta según tus carpetas)
import ListaNegra from './components/ListaNegra.vue'
import ActualizarRequerimientos from './components/ActualizarRequerimientos.vue'

// Estados de control impecables
const esHorizontal = ref(false)
const actual = ref('lista_negra')
const busqueda = ref('')

// Módulos del sistema
const modulos = [
  { id: 'lista_negra', nombre: '📱 Lista Negra Teléfonos' },
  { id: 'metropolitana', nombre: '🏢 Metropolitana DXD' },
  { id: 'actualizar_requerimientos', nombre: '⚙️ Requerimientos' },
  { id: 'predictivos', nombre: '📊 Predictivos Alfin' },
  { id: 'Metas-carteras', nombre: '🎯 Metas de Carteras' }
]

// Buscador en tiempo real
const modulosFiltrados = computed(() => {
  return modulos.filter(m => 
    m.nombre.toLowerCase().includes(busqueda.value.toLowerCase())
  )
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col font-sans">
    
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
      <div class="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between gap-4">
        
        <h1 class="font-extrabold text-xl text-indigo-600 tracking-tight whitespace-nowrap">
          ⚡ AutoPanel <span class="text-xs font-normal text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md ml-1">v2.0</span>
        </h1>

        <div class="flex-1 max-w-lg mx-4">
          <input 
            v-model="busqueda"
            type="text" 
            placeholder="Buscar módulos..." 
            class="w-full bg-slate-100 border border-slate-200 rounded-xl py-2 px-4 text-sm outline-none focus:bg-white focus:border-indigo-500 transition-all"
          />
        </div>

        <div class="flex items-center gap-4">
          <div class="text-right hidden md:block">
            <p class="text-xs font-bold text-slate-900">Ruben 👋</p>
            <p class="text-[10px] text-slate-500 font-medium">Analista Developer</p>
          </div>
          
          <button class="text-xs font-bold text-red-600 hover:text-red-800 px-3 py-1.5 bg-red-50 rounded-lg transition-colors border border-red-100">
            Salir
          </button>
          
          <button @click="esHorizontal = !esHorizontal" class="p-2 hover:bg-slate-100 rounded-xl border border-slate-200/60 transition-all">
            <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path v-if="!esHorizontal" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
              <path v-else d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>

      </div>
    </header>

    <div class="max-w-7xl w-full mx-auto p-6 flex gap-6 flex-1" :class="esHorizontal ? 'flex-col' : 'flex-row'">
      
      <aside 
        :class="[
          esHorizontal 
            ? 'w-full flex flex-row flex-wrap gap-2 border-b border-slate-200 pb-4 mb-2' 
            : 'w-64 flex flex-col gap-2 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm sticky top-2 self-start'
        ]" 
        class="transition-all duration-300"
      >
        
        <button 
          v-for="mod in modulosFiltrados" 
          :key="mod.id" 
          @click="actual = mod.id"
          :class="[
            actual === mod.id 
              ? 'bg-indigo-600 text-white shadow-md shadow-indigo-100 font-bold' 
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-semibold'
          ]"
          class="px-4 py-2.5 rounded-xl text-sm transition-all text-left whitespace-nowrap"
        >
          {{ mod.nombre }}
        </button>
        
        <div :class="esHorizontal ? 'hidden' : 'mt-12 pt-4 border-t border-slate-100'">
          <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider px-1">Refinansa Perú 2026</p>
        </div>
      </aside>

      <main class="flex-1 min-w-0">
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm min-h-[450px]">
          
          <div class="border-b border-slate-100 pb-4 mb-6">
            <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest">Módulo Activo</span>
            <h2 class="text-xl font-extrabold text-slate-900 mt-1">
              {{ modulos.find(m => m.id === actual)?.nombre }}
            </h2>
          </div>

          <div v-if="actual === 'lista_negra'">
            <ListaNegra />
          </div>
          <div v-else-if="actual === 'actualizar_requerimientos'">
            <ActualizarRequerimientos />
          </div>

          <div v-else class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-xl p-12 text-center text-slate-500">
            <p class="font-semibold text-sm">Módulo en construcción.</p>
            <p class="text-xs text-slate-400 mt-2">Listo para recibir peticiones por Axios desde backend/app/Http/Controllers/Api/</p>
          </div>

        </div>
      </main>

    </div>
  </div>
</template>
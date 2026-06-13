<script setup>
import { ref, onMounted, watch } from 'vue'

// Estados del Proceso de Carga Masiva
const archivo = ref(null)
const cargando = ref(false)
const preview = ref(null)
const resumenFinal = ref(null)
const errorMsg = ref(null)
const rutaManual = ref('') // 📁 Para describir o pegar rutas directas de red/servidor

// Estados del Monitor del Sistema (Datos Existentes)
const kpis = ref({ total: 0, activos: 0, otros: 0 })
const registrosSql = ref([])
const buscarTermino = ref('')
const paginaActual = ref(1)
const ultimaPagina = ref(1)

// ==========================================
// 🛠️ MONITOREO EN VIVO (SQL SERVER COORD)
// ==========================================
const cargarDatosMonitor = async () => {
  try {
    const res = await fetch(`http://127.0.0.1:8000/api/lista-negra?page=${paginaActual.value}&search=${buscarTermino.value}`)
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error al conectar con el monitor.')
    
    kpis.value = data.kpis
    registrosSql.value = data.registros.data
    ultimaPagina.value = data.registros.last_page
  } catch (err) {
    console.error("Error cargando monitor en vivo:", err.message)
  }
}

watch(buscarTermino, () => {
  paginaActual.value = 1
  cargarDatosMonitor()
})

onMounted(() => {
  cargarDatosMonitor()
})

// ==========================================
// 🖱️ MANEJO DE ARCHIVOS
// ==========================================
const handleFileChange = (e) => {
  if (e.target.files?.length) {
    archivo.value = e.target.files[0]
    resetAlertas()
  }
}

const handleDrop = (e) => {
  if (e.dataTransfer?.files?.length) {
    archivo.value = e.dataTransfer.files[0]
    resetAlertas()
  }
}

const resetAlertas = () => {
  preview.value = null
  resumenFinal.value = null
  errorMsg.value = null
}

const removerArchivo = () => {
  archivo.value = null
  resetAlertas()
}

// ==========================================
// 📡 CONSUMO DE ENDPOINTS DE TU CONTROLADOR
// ==========================================
const subirParaPrevisualizar = async () => {
  if (!archivo.value && !rutaManual.value) return
  cargando.value = true
  errorMsg.value = null
  
  const formData = new FormData()
  if (archivo.value) {
    formData.append('archivo', archivo.value)
  }
  if (rutaManual.value) {
    formData.append('ruta_servidor', rutaManual.value)
  }

  try {
    const res = await fetch('http://127.0.0.1:8000/api/lista-negra/previsualizar', {
      method: 'POST',
      body: formData
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error al previsualizar.')
    preview.value = data
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    cargando.value = false
  }
}

const confirmarCarga = async () => {
  if (!preview.value?.temporal_path) return
  cargando.value = true
  errorMsg.value = null

  try {
    const res = await fetch('http://127.0.0.1:8000/api/lista-negra/cargar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ temporal_path: preview.value.temporal_path })
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error en la inserción final.')
    
    resumenFinal.value = data
    preview.value = null
    archivo.value = null
    rutaManual.value = ''
    
    cargarDatosMonitor()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-2 text-slate-800">
    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Lista Negra</p>
          <p class="text-2xl font-black text-slate-900">{{ kpis.total.toLocaleString() }}</p>
        </div>
        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">📱</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Bloqueos Activos</p>
          <p class="text-2xl font-black text-emerald-600">{{ kpis.activos.toLocaleString() }}</p>
        </div>
        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-lg">🛡️</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Otros Estados</p>
          <p class="text-2xl font-black text-amber-600">{{ kpis.otros.toLocaleString() }}</p>
        </div>
        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-lg">⏳</div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
      <div>
        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">📥 Importación Masiva de Teléfonos</h2>
        <p class="text-xs text-slate-400 font-medium mt-0.5">Selecciona un archivo local o especifica una ruta directa del servidor</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div 
          @dragover.prevent 
          @drop.prevent="handleDrop"
          class="border-2 border-dashed border-slate-200 hover:border-indigo-400 rounded-xl p-6 bg-slate-50/50 text-center transition-all relative"
        >
          <input type="file" @change="handleFileChange" accept=".xlsx, .xls" id="file-upload" class="hidden" />
          
          <div class="flex flex-col items-center justify-center space-y-3">
            <span class="text-3xl">📄</span>
            <div>
              <span class="text-xs font-bold text-slate-700 block">Arrastra tu plantilla Excel aquí</span>
              <span class="text-[11px] text-slate-400 mt-1 block">Actualizar_ListaNegra_telefonos_subir.xlsx</span>
            </div>
            
            <label for="file-upload" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs shadow-sm cursor-pointer transition-all inline-block">
              🔍 Examinar Archivo Local
            </label>
          </div>

          <div v-if="archivo" class="mt-4 inline-flex items-center gap-2 bg-indigo-50 border border-indigo-100 text-indigo-600 text-xs font-bold px-3 py-1.5 rounded-xl">
            <span>Selected: {{ archivo.name }}</span>
            <button @click.prevent="removerArchivo" class="text-indigo-400 hover:text-indigo-600 font-bold">✕</button>
          </div>
        </div>

        <div class="bg-slate-50/50 border border-slate-200 border-dashed rounded-xl p-6 flex flex-col justify-between space-y-4">
          <div class="space-y-2">
            <label class="text-xs font-bold text-slate-700 block">📁 Descripción o Ruta Absoluta en Servidor (Opcional):</label>
            <input 
              v-model="rutaManual"
              type="text" 
              placeholder="Ej: C:\CargasExcel\Actualizar_ListaNegra.xlsx o \\192.168.1.247\compartido\lista.xlsx"
              class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-mono shadow-sm outline-none focus:border-indigo-500 transition-all placeholder-slate-400"
            />
            <p class="text-[10px] text-slate-400 font-medium">Útil si el archivo Excel ya se encuentra almacenado dentro de los directorios del backend.</p>
          </div>

          <div class="flex justify-end pt-2">
            <button 
              @click="subirParaPrevisualizar" 
              :disabled="cargando || (!archivo && !rutaManual)" 
              class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 text-white disabled:text-slate-400 px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wide transition-all shadow-md shadow-indigo-100/50"
            >
              {{ cargando ? 'Analizando documento...' : '👁️ Previsualizar Datos del Excel' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="errorMsg" class="bg-rose-50 border border-rose-100 text-rose-700 text-xs p-4 rounded-xl font-bold flex items-start gap-2.5 shadow-sm">
      <span class="text-sm">⚠️</span>
      <div>
        <p class="font-extrabold uppercase">Error en Operación</p>
        <p class="font-medium text-rose-600 mt-0.5">{{ errorMsg }}</p>
      </div>
    </div>

    <div v-if="preview" class="border border-slate-200 rounded-2xl p-5 bg-white space-y-4 shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">👁️ Previsualización de Datos (Muestra de 5 filas)</h3>
          <p class="text-[10px] text-slate-400 font-medium mt-0.5">Valores recuperados del almacenamiento temporal</p>
        </div>
        <span class="text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-100 px-2 py-0.5 rounded-md">Confirmación Requerida</span>
      </div>

      <div class="grid grid-cols-3 gap-3 text-center text-[11px] font-bold">
        <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Filas en Excel</span>
          <span class="text-sm text-slate-800 font-black block mt-0.5">{{ preview.total_filas_excel }}</span>
        </div>
        <div class="bg-orange-50 border border-orange-100 p-2.5 rounded-xl">
          <span class="text-orange-600 block">Duplicados Internos</span>
          <span class="text-sm text-orange-700 font-black block mt-0.5">{{ preview.duplicados_excel }}</span>
        </div>
        <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Antes en SQL Server</span>
          <span class="text-sm text-slate-800 font-black block mt-0.5">{{ preview.registros_antes_sql }}</span>
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-100 rounded-xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100 font-bold text-slate-500">
              <th class="p-2.5">Doc</th>
              <th class="p-2.5">Teléfono</th>
              <th class="p-2.5">Obs</th>
              <th class="p-2.5">Estado original</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in preview.primeras_filas" :key="idx" class="border-b border-slate-50 font-medium text-slate-600">
              <td class="p-2.5 font-mono font-bold text-slate-900">{{ row.nro_documento || 'NULL' }}</td>
              <td class="p-2.5 font-mono text-indigo-600 font-bold">{{ row.telefono || 'NULL' }}</td>
              <td class="p-2.5 max-w-xs truncate text-slate-400">{{ row.observaciones || 'NULL' }}</td>
              <td class="p-2.5">
                <span class="bg-slate-100 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ row.estado || 'NULL' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="preview.duplicados_excel > 0" class="bg-amber-50 border border-amber-100 text-amber-800 text-[11px] p-3 rounded-xl font-semibold">
        ⚠️ ADVERTENCIA: El archivo contiene {{ preview.duplicados_excel }} filas duplicadas internamente.
      </div>

      <div class="flex justify-between items-center pt-2">
        <button @click="resetAlertas" class="text-slate-400 text-xs font-bold hover:text-slate-600">Cancelar Operación</button>
        <button 
          @click="confirmarCarga" 
          :disabled="cargando" 
          class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl text-xs font-extrabold tracking-wide shadow-md shadow-emerald-100"
        >
          {{ cargando ? 'Insertando en SQL Server...' : '🚀 Confirmar e Insertar en SQL Server' }}
        </button>
      </div>
    </div>

    <div v-if="resumenFinal" class="bg-emerald-50/50 border border-emerald-100 rounded-2xl p-5 space-y-4 shadow-sm">
      <div class="flex items-center gap-3 border-b border-emerald-100/80 pb-3">
        <div class="w-7 h-7 bg-emerald-500 text-white rounded-full flex items-center justify-center font-bold text-xs">✓</div>
        <div>
          <h3 class="text-xs font-black text-emerald-900 uppercase tracking-wider">RESUMEN DE CARGA - LISTA NEGRA TELEFONOS</h3>
          <p class="text-[10px] text-emerald-600 font-bold">Estado: COMPLETADO exitosamente</p>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-[11px] font-bold text-slate-700">
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Filas en Excel</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.total_filas_excel }}</span>
        </div>
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Antes en SQL</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.registros_antes_sql }}</span>
        </div>
        <div class="bg-emerald-600 p-2.5 rounded-xl text-white shadow-sm">
          <span class="text-emerald-100 block">Insertadas Hoy</span>
          <span class="text-base font-black mt-0.5 block">{{ resumenFinal.filas_insertadas }}</span>
        </div>
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Total en SQL</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.registros_despues_sql }}</span>
        </div>
      </div>
      <div class="flex justify-between items-center text-[10px] text-emerald-600 font-bold pt-1">
        <span>📅 Último creado: <span class="font-mono">{{ resumenFinal.ultimo_created || 'N/A' }}</span></span>
        <span>⏱️ Proceso completado en {{ resumenFinal.tiempo_total_segundos }} segundos</span>
      </div>
    </div>

    <div class="border border-slate-200 rounded-2xl bg-white p-5 shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100/80 pb-4">
        <div>
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">🖥️ Monitor de Datos de la Tabla</h3>
          <p class="text-[11px] text-slate-400 font-medium mt-0.5">Consulta directa sobre SQL Server con paginado e indexación rápida</p>
        </div>
        
        <div class="w-full sm:w-72">
          <input 
            v-model="buscarTermino"
            type="text" 
            placeholder="Buscar por Documento o Teléfono..." 
            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-semibold outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-50/50 transition-all placeholder-slate-400"
          />
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-100 rounded-xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 font-bold text-slate-500 uppercase tracking-wider text-[10px]">
              <th class="p-3">Doc</th>
              <th class="p-3">Teléfono</th>
              <th class="p-3">Observaciones</th>
              <th class="p-3">Fecha Registro</th>
              <th class="p-3 text-center">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="registrosSql.length === 0" class="text-center text-slate-400">
              <td colspan="5" class="p-10 font-bold text-xs">No hay datos indexados o el término no coincide.</td>
            </tr>
            <tr v-for="(row, idx) in registrosSql" :key="idx" class="border-b border-slate-50 hover:bg-slate-50/40 font-medium text-slate-600 transition-colors">
              <td class="p-3 font-mono font-bold text-slate-900">{{ row.nro_documento || '-' }}</td>
              <td class="p-3 font-mono text-indigo-600 font-bold">{{ row.telefono || '-' }}</td>
              <td class="p-3 max-w-xs truncate text-slate-400">{{ row.observaciones || '-' }}</td>
              <td class="p-3 text-slate-400 font-mono">{{ row.created_at || row.fecha_registro || '-' }}</td>
              <td class="p-3 text-center">
                <span 
                  :class="row.estado?.startsWith('ACT') ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-200'"
                  class="px-2 py-0.5 rounded-md text-[9px] font-black border uppercase tracking-wide inline-block"
                >
                  {{ row.estado || 'INACTIVO' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between pt-2 text-xs font-bold" v-if="ultimaPagina > 1">
        <span class="text-slate-400 font-medium">Página {{ paginaActual }} de {{ ultimaPagina }}</span>
        <div class="flex gap-2">
          <button 
            :disabled="paginaActual === 1" 
            @click="paginaActual--; cargarDatosMonitor()" 
            class="px-3 py-1.5 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 disabled:opacity-40 font-bold transition-all"
          >
            Anterior
          </button>
          <button 
            :disabled="paginaActual === ultimaPagina" 
            @click="paginaActual++; cargarDatosMonitor()" 
            class="px-3 py-1.5 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 disabled:opacity-40 font-bold transition-all"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
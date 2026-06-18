<script setup>
import { ref, onMounted, watch } from 'vue'

// ==========================================
// ESTADOS DEL PROCESO DE CARGA
// ==========================================
const archivo = ref(null)
const cargando = ref(false)
const preview = ref(null)
const resumenFinal = ref(null)
const errorMsg = ref(null)
const rutaManual = ref('')
const mostrarModalPreview = ref(false)
const mostrarModalResumen = ref(false)

// Selects dinámicos — EXACTAMENTE lo que pide tu backend
const mesSeleccionado = ref(6)
const anioSeleccionado = ref(2026)
const opcionCartera = ref('4')

const opcionesCartera = [
  { value: '1', label: 'ADMINISTRADA' },
  { value: '2', label: 'HIPOTECARIO' },
  { value: '3', label: 'CONVENIO' },
  { value: '4', label: 'NINGUNA (solo cargar datos)' }
]

const meses = Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: `${i + 1} - ${['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][i]}` }))
const anios = [2024, 2025, 2026, 2027, 2028]

// ==========================================
// MONITOR EN VIVO (igual que tu componente modelo)
// ==========================================
const kpis = ref({ total: 0, activos: 0, otros: 0 })
const registrosSql = ref([])
const buscarTermino = ref('')
const paginaActual = ref(1)
const ultimaPagina = ref(1)

const cargarDatosMonitor = async () => {
  try {
    const res = await fetch(`http://127.0.0.1:8000/api/actualizar-requerimientos/monitor?page=${paginaActual.value}&search=${buscarTermino.value}`)
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error al conectar con el monitor.')

    kpis.value = data.kpis || { total: 0, activos: 0, otros: 0 }
    registrosSql.value = data.registros?.data || []
    ultimaPagina.value = data.registros?.last_page || 1
  } catch (err) {
    console.error("Error cargando monitor:", err.message)
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
// MANEJO DE ARCHIVOS (igual que tu modelo)
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
  mostrarModalPreview.value = false
  mostrarModalResumen.value = false
}

const removerArchivo = () => {
  archivo.value = null
  resetAlertas()
}

// ==========================================
// PREVISUALIZAR (preview sin ejecutar)
// ==========================================
const previsualizar = async () => {
  if (!archivo.value && !rutaManual.value) {
    errorMsg.value = 'Debes seleccionar un archivo o ingresar una ruta.'
    return
  }
  cargando.value = true
  errorMsg.value = null

  try {
    let res, data

    if (archivo.value) {
      // Archivo subido → POST multipart
      const formData = new FormData()
      formData.append('archivo', archivo.value)
      formData.append('mes_asignacion', mesSeleccionado.value)
      formData.append('anio_asignacion', anioSeleccionado.value)
      formData.append('confirmar', 'N')
      formData.append('opcion', opcionCartera.value)

      res = await fetch('http://127.0.0.1:8000/api/actualizar-requerimientos', {
        method: 'POST',
        body: formData
      })
    } else {
      // Ruta manual → GET
      const params = new URLSearchParams({
        ruta_excel: rutaManual.value,
        mes_asignacion: mesSeleccionado.value,
        anio_asignacion: anioSeleccionado.value,
        confirmar: 'N',
        opcion: opcionCartera.value
      })
      res = await fetch(`http://127.0.0.1:8000/api/actualizar-requerimientos/preview?${params}`)
    }

    data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error al previsualizar.')

    preview.value = data
    mostrarModalPreview.value = true
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    cargando.value = false
  }
}

// ==========================================
// CONFIRMAR CARGA COMPLETA
// ==========================================
const confirmarCarga = async () => {
  if (!archivo.value && !rutaManual.value) return
  cargando.value = true
  errorMsg.value = null

  try {
    let res, data

    if (archivo.value) {
      const formData = new FormData()
      formData.append('archivo', archivo.value)
      formData.append('mes_asignacion', mesSeleccionado.value)
      formData.append('anio_asignacion', anioSeleccionado.value)
      formData.append('confirmar', 'S')
      formData.append('opcion', opcionCartera.value)

      res = await fetch('http://127.0.0.1:8000/api/actualizar-requerimientos', {
        method: 'POST',
        body: formData
      })
    } else {
      res = await fetch('http://127.0.0.1:8000/api/actualizar-requerimientos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ruta_excel: rutaManual.value,
          mes_asignacion: mesSeleccionado.value,
          anio_asignacion: anioSeleccionado.value,
          confirmar: 'S',
          opcion: opcionCartera.value
        })
      })
    }

    data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error en la carga.')

    resumenFinal.value = data
    preview.value = null
    archivo.value = null
    rutaManual.value = ''
    mostrarModalPreview.value = false
    mostrarModalResumen.value = true

    cargarDatosMonitor()
  } catch (err) {
    errorMsg.value = err.message
  } finally {
    cargando.value = false
  }
}

// ==========================================
// CARGA RÁPIDA POR CARTERA (endpoints directos)
// ==========================================
const cargarDirecto = async (endpoint) => {
  if (!archivo.value && !rutaManual.value) {
    errorMsg.value = 'Debes seleccionar un archivo o ingresar una ruta.'
    return
  }
  cargando.value = true
  errorMsg.value = null

  try {
    let res, data

    if (archivo.value) {
      const formData = new FormData()
      formData.append('archivo', archivo.value)
      formData.append('mes_asignacion', mesSeleccionado.value)
      formData.append('anio_asignacion', anioSeleccionado.value)
      // ✨ CORRECCIÓN CRÍTICA: Indicamos al backend que ejecute de verdad la carga
      formData.append('confirmar', 'S') 

      res = await fetch(`http://127.0.0.1:8000/api/actualizar-requerimientos/${endpoint}`, {
        method: 'POST',
        body: formData
      })
    } else {
      res = await fetch(`http://127.0.0.1:8000/api/actualizar-requerimientos/${endpoint}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ruta_excel: rutaManual.value,
          mes_asignacion: mesSeleccionado.value,
          anio_asignacion: anioSeleccionado.value,
          // ✨ CORRECCIÓN CRÍTICA: Indicamos al backend que ejecute de verdad la carga
          confirmar: 'S' 
        })
      })
    }

    data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error en la carga directa.')

    resumenFinal.value = data
    archivo.value = null
    rutaManual.value = ''
    mostrarModalResumen.value = true

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

    <!-- ========================================== -->
    <!-- KPIs CARDS -->
    <!-- ========================================== -->
    <div class="grid grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Registros</p>
          <p class="text-2xl font-black text-slate-900">{{ kpis.total.toLocaleString() }}</p>
        </div>
        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">📊</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total PAgos</p>
          <p class="text-2xl font-black text-emerald-600">S/. {{ kpis.activos.toLocaleString() }}</p>
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

    <!-- ========================================== -->
    <!-- SECCIÓN DE CARGA -->
    <!-- ========================================== -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
      <div>
        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">📥 Actualización de Requerimientos — Excel → SQL Server</h2>
        <p class="text-xs text-slate-400 font-medium mt-0.5">Selecciona archivo, mes/año de asignación y tipo de cartera a actualizar</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Drag & Drop -->
        <div 
          @dragover.prevent 
          @drop.prevent="handleDrop"
          class="border-2 border-dashed border-slate-200 hover:border-indigo-400 rounded-xl p-6 bg-slate-50/50 text-center transition-all relative"
        >
          <input type="file" @change="handleFileChange" accept=".xlsx, .xls" id="file-upload" class="hidden" />

          <div class="flex flex-col items-center justify-center space-y-3">
            <span class="text-3xl">📄</span>
            <div>
              <span class="text-xs font-bold text-slate-700 block">Arrastra tu Excel aquí</span>
              <span class="text-[11px] text-slate-400 mt-1 block">Actualizacion-datos-requeridos_subir.xlsx</span>
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

        <!-- Ruta Manual + Selects -->
        <div class="lg:col-span-2 bg-slate-50/50 border border-slate-200 border-dashed rounded-xl p-6 flex flex-col justify-between space-y-4">
          <!-- Ruta -->
          <div class="space-y-2">
            <label class="text-xs font-bold text-slate-700 block">📁 Ruta Absoluta en Servidor (Opcional):</label>
            <input 
              v-model="rutaManual"
              type="text" 
              placeholder="Ej: \\192.168.1.249\Compartido\...\Actualizacion-datos-requeridos_subir.xlsx"
              class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-xs font-mono shadow-sm outline-none focus:border-indigo-500 transition-all placeholder-slate-400"
            />
            <p class="text-[10px] text-slate-400 font-medium">Si el archivo ya está en el servidor, ingresa la ruta UNC completa.</p>
          </div>

          <!-- Selects -->
          <div class="grid grid-cols-3 gap-3">
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Mes Asignación</label>
              <select v-model="mesSeleccionado" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="m in meses" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Año Asignación</label>
              <select v-model="anioSeleccionado" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Cartera a Actualizar</label>
              <select v-model="opcionCartera" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="opt in opcionesCartera" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <!-- Botones -->
          <div class="flex flex-wrap gap-2 justify-end pt-2">
            <button 
              @click="previsualizar" 
              :disabled="cargando || (!archivo && !rutaManual)" 
              class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 disabled:text-slate-400 px-4 py-2 rounded-xl text-xs font-extrabold transition-all shadow-sm"
            >
              {{ cargando ? 'Analizando...' : '👁️ Previsualizar' }}
            </button>
            <button 
              @click="confirmarCarga" 
              :disabled="cargando || (!archivo && !rutaManual)" 
              class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 text-white disabled:text-slate-400 px-5 py-2 rounded-xl text-xs font-extrabold tracking-wide transition-all shadow-md shadow-indigo-100/50"
            >
              {{ cargando ? 'Procesando...' : '🚀 Ejecutar Carga' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Botones rápidos -->
      <div class="border-t border-slate-100 pt-4">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">⚡ Acciones Rápidas (Cargar + Actualizar)</p>
        <div class="flex flex-wrap gap-2">
          <button @click="cargarDirecto('administrada')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🏢 ADMINISTRADA
          </button>
          <button @click="cargarDirecto('hipotecario')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-blue-50 border border-blue-200 hover:bg-blue-100 text-blue-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🏠 HIPOTECARIO
          </button>
          <button @click="cargarDirecto('convenio')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-purple-50 border border-purple-200 hover:bg-purple-100 text-purple-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🤝 CONVENIO
          </button>
          <button @click="cargarDirecto('cargar')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            📤 Solo Cargar
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- ALERTA DE ERROR -->
    <!-- ========================================== -->
    <div v-if="errorMsg" class="bg-rose-50 border border-rose-100 text-rose-700 text-xs p-4 rounded-xl font-bold flex items-start gap-2.5 shadow-sm">
      <span class="text-sm">⚠️</span>
      <div>
        <p class="font-extrabold uppercase">Error en Operación</p>
        <p class="font-medium text-rose-600 mt-0.5">{{ errorMsg }}</p>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL PREVISUALIZACIÓN -->
    <!-- ========================================== -->
    <div v-if="mostrarModalPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="mostrarModalPreview = false">
      <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-200 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">👁️ Previsualización de Datos</h3>
            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Vista previa de los registros detectados en el Excel</p>
          </div>
          <button @click="mostrarModalPreview = false" class="text-slate-400 hover:text-slate-600 transition-colors text-xl font-bold p-1 hover:bg-slate-100 rounded-lg w-8 h-8 flex items-center justify-center">
            ✕
          </button>
        </div>

        <div class="grid grid-cols-3 gap-3 text-center text-[11px] font-bold">
          <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
            <span class="text-slate-400 block">Registros Encontrados</span>
            <span class="text-sm text-slate-800 font-black block mt-0.5">{{ preview?.registros_encontrados || 0 }}</span>
          </div>
          <div class="bg-indigo-50 border border-indigo-100 p-2.5 rounded-xl">
            <span class="text-indigo-600 block">Mes / Año</span>
            <span class="text-sm text-indigo-700 font-black block mt-0.5">{{ mesSeleccionado }} / {{ anioSeleccionado }}</span>
          </div>
          <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
            <span class="text-slate-400 block">Cartera Seleccionada</span>
            <span class="text-sm text-slate-800 font-black block mt-0.5">{{ opcionesCartera.find(o => o.value === opcionCartera)?.label }}</span>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-100 rounded-xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 font-bold text-slate-500">
                <th class="p-2.5">DNI</th>
                <th class="p-2.5">ENTIDADES</th>
                <th class="p-2.5">CALIF. SBS</th>
                <th class="p-2.5">RANGO SUELDO</th>
                <th class="p-2.5">RANGO EDAD</th>
                <th class="p-2.5">UGEL</th>
                <th class="p-2.5">TRAMO</th>
                <th class="p-2.5 text-right">PAGOS</th>
                <th class="p-2.5">FECHA PAGOS</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in preview?.preview" :key="idx" class="border-b border-slate-50 font-medium text-slate-600">
                <td class="p-2.5 font-mono font-bold text-slate-900">{{ row.DNI || 'NULL' }}</td>
                <td class="p-2.5 font-mono text-indigo-600 font-bold">{{ row.ENTIDADES || 'NULL' }}</td>
                <td class="p-2.5">{{ row.CALIFICACION_SBS || 'NULL' }}</td>
                <td class="p-2.5">{{ row.RANGO_SUELDO || 'NULL' }}</td>
                <td class="p-2.5">{{ row.RANGO_EDAD || 'NULL' }}</td>
                <td class="p-2.5">{{ row.NOMBRE_UGEL || 'NULL' }}</td>
                <td class="p-2.5">{{ row.TRAMO_FACT || 'NULL' }}</td>
                <td class="p-2.5 text-right font-mono text-emerald-600 font-bold">{{ row.PAGOS !== null ? Number(row.PAGOS).toFixed(2) : '-' }}</td>
                <td class="p-2.5 font-mono">{{ row.FECHA_PAGOS || 'NULL' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-between items-center pt-4 border-t border-slate-100">
          <button @click="mostrarModalPreview = false" class="text-slate-400 text-xs font-bold hover:text-slate-600">Cancelar Operación</button>
          <button 
            @click="confirmarCarga" 
            :disabled="cargando" 
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl text-xs font-extrabold tracking-wide shadow-md shadow-emerald-100"
          >
            {{ cargando ? 'Insertando en SQL Server...' : '🚀 Confirmar e Insertar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL RESUMEN FINAL -->
    <!-- ========================================== -->
    <div v-if="mostrarModalResumen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="mostrarModalResumen = false">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-emerald-200 p-6 space-y-4">
        <div class="flex items-center gap-3 border-b border-emerald-100 pb-4">
          <div class="w-8 h-8 bg-emerald-500 text-white rounded-full flex items-center justify-center font-bold text-xs">✓</div>
          <div>
            <h3 class="text-sm font-black text-emerald-900 uppercase tracking-wider">Carga Completada Exitosamente</h3>
            <p class="text-[10px] text-emerald-600 font-bold">Proceso finalizado correctamente</p>
          </div>
          <button @click="mostrarModalResumen = false" class="ml-auto text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-[11px] font-bold">
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Registros Excel</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.registros || 0 }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Insertados</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.registros_insertados || 0 }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Mes / Año</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.mes_asignacion }}/{{ resumenFinal?.resumen?.anio_asignacion }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Tiempo</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.tiempo_segundos || 0 }}s</span>
          </div>
        </div>

        <div v-if="resumenFinal?.actualizacion?.tipo" class="bg-slate-50 border border-slate-100 p-3 rounded-xl">
          <p class="text-xs font-bold text-slate-700">🔄 Actualización de Cartera:</p>
          <p class="text-[11px] text-slate-600 mt-1">
            Tipo: <span class="font-bold text-indigo-600">{{ resumenFinal.actualizacion.tipo }}</span> — 
            Filas afectadas: <span class="font-bold text-emerald-600">{{ resumenFinal.actualizacion.filas_afectadas }}</span>
          </p>
        </div>

        <div class="flex justify-end pt-2">
          <button @click="mostrarModalResumen = false; resumenFinal = null" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-xs font-extrabold transition-all">
            Aceptar
          </button>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- RESUMEN INLINE (fallback si no usas modal) -->
    <!-- ========================================== -->
    <div v-if="resumenFinal && !mostrarModalResumen" class="bg-emerald-50/50 border border-emerald-100 rounded-2xl p-5 space-y-4 shadow-sm">
      <div class="flex items-center gap-3 border-b border-emerald-100/80 pb-3">
        <div class="w-7 h-7 bg-emerald-500 text-white rounded-full flex items-center justify-center font-bold text-xs">✓</div>
        <div>
          <h3 class="text-xs font-black text-emerald-900 uppercase tracking-wider">RESUMEN DE CARGA</h3>
          <p class="text-[10px] text-emerald-600 font-bold">Estado: COMPLETADO exitosamente</p>
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-[11px] font-bold text-slate-700">
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Registros Excel</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.resumen?.registros || 0 }}</span>
        </div>
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Insertados</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.resumen?.registros_insertados || 0 }}</span>
        </div>
        <div class="bg-white border border-emerald-100/60 p-2.5 rounded-xl">
          <span class="text-slate-400 block">Mes / Año</span>
          <span class="text-sm mt-0.5 text-slate-800 block">{{ resumenFinal.resumen?.mes_asignacion }}/{{ resumenFinal.resumen?.anio_asignacion }}</span>
        </div>
        <div class="bg-emerald-600 p-2.5 rounded-xl text-white shadow-sm">
          <span class="text-emerald-100 block">Tiempo Total</span>
          <span class="text-base font-black mt-0.5 block">{{ resumenFinal.resumen?.tiempo_segundos || 0 }}s</span>
        </div>
      </div>
      <div v-if="resumenFinal.actualizacion?.tipo" class="flex justify-between items-center text-[10px] text-emerald-600 font-bold pt-1">
        <span>🔄 Cartera actualizada: <span class="font-bold text-indigo-700">{{ resumenFinal.actualizacion.tipo }}</span></span>
        <span>Filas afectadas: <span class="font-bold">{{ resumenFinal.actualizacion.filas_afectadas }}</span></span>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MONITOR DE DATOS SQL -->
    <!-- ========================================== -->
    <div class="border border-slate-200 rounded-2xl bg-white p-5 shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100/80 pb-4">
        <div>
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">🖥️ Monitor de Datos — Tabla Actualizar_datos</h3>
          <p class="text-[11px] text-slate-400 font-medium mt-0.5">Consulta directa sobre SQL Server con paginado</p>
        </div>
        <div class="w-full sm:w-72">
          <input 
            v-model="buscarTermino"
            type="text" 
            placeholder="Buscar por DNI o Entidad..." 
            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-semibold outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-50/50 transition-all placeholder-slate-400"
          />
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-100 rounded-xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 font-bold text-slate-500 uppercase tracking-wider text-[10px]">
              <th class="p-3">DNI</th>
              <th class="p-3">ENTIDADES</th>
              <th class="p-3">CALIF. SBS</th>
              <th class="p-3 whitespace-nowrap">RANGO SUELDO</th>
              <th class="p-3 whitespace-nowrap">RANGO EDAD</th>
              <th class="p-3">UGEL</th>
              <th class="p-3">TRAMO</th>
              <th class="p-3 text-right">PAGOS</th>
              <th class="p-3 whitespace-nowrap">FECHA PAGOS</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="registrosSql.length === 0" class="text-center text-slate-400">
              <td colspan="9" class="p-10 font-bold text-xs">No hay datos indexados o el término no coincide.</td>
            </tr>
            <tr v-for="(row, idx) in registrosSql" :key="idx" class="border-b border-slate-50 hover:bg-slate-50/40 font-medium text-slate-600 transition-colors">
              <td class="p-3 font-mono font-bold text-slate-900">{{ row.DNI || '-' }}</td>
              <td class="p-3 font-mono text-indigo-600 font-bold whitespace-nowrap">{{ row.ENTIDADES || '-' }}</td>
              <td class="p-3">{{ row.CALIFICACION_SBS || '-' }}</td>
              <td class="p-3">{{ row.RANGO_SUELDO || '-' }}</td>
              <td class="p-3">{{ row.RANGO_EDAD || '-' }}</td>
              <td class="p-3">{{ row.NOMBRE_UGEL || '-' }}</td>
              <td class="p-3">{{ row.TRAMO_FACT || '-' }}</td>
              <td class="p-3 text-right font-mono text-emerald-600 font-bold whitespace-nowrap">s/. {{ row.PAGOS !== null ? Number(row.PAGOS).toFixed(2) : '-' }}</td>
              <td class="p-3 font-mono text-slate-400">{{ row.FECHA_PAGOS || '-' }}</td>
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
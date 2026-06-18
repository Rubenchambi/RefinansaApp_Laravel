<script setup>
import { ref, onMounted, watch, computed } from 'vue'

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

// Configuración de nombre manual o adicional (Ej: _adi, _update)
const nombreTablaPersonalizado = ref('')

// Campos String dinámicos para proteger los ceros a la izquierda
const columnasString = ref('NRO_DOCUMENTO-OPERACION, CUENTA, OPERACION, NRO_DOCUMENTO, año, mes, TELEFONO_1, TELEFONO_2, TELEFONO_3, COD_ASISTENTE_CAMPO, ASIGNACION_MES25, ENTIDADES, UBIGEO')

// Selects dinámicos mapeados con el Backend
const mesSeleccionado = ref('Jun')
const anioSeleccionado = ref('26')
const opcionCartera = ref('Convenio')

const opcionesCartera = [
  { value: 'Aqp', label: 'AQP' },
  { value: 'Carsa', label: 'CARSA' },
  { value: 'Cmetro', label: 'CMETRO' },
  { value: 'CompJudicial', label: 'COMP JUDICIAL' },
  { value: 'Confianza', label: 'CONFIANZA' },
  { value: 'ConfJudicial', label: 'CONF JUDICIAL' },
  { value: 'Convenio', label: 'CONVENIO' },
  { value: 'Derrama', label: 'DERRAMA' },
  { value: 'Efectiva', label: 'EFECTIVA' },
  { value: 'Hipotecario', label: 'HIPOTECARIO' },
  { value: 'Judicial', label: 'JUDICIAL' },
  { value: 'Pichincha', label: 'PICHINCHA' },
  { value: 'Prejudicial', label: 'PREJUDICIAL' },
  { value: 'Sisgroup', label: 'SISGROUP' },
  { value: 'Tramo5', label: 'TRAMO 5' }
]

const meses = [
  { value: 'Ene', label: '01 - Enero' },
  { value: 'Feb', label: '02 - Febrero' },
  { value: 'Mar', label: '03 - Marzo' },
  { value: 'Abr', label: '04 - Abril' },
  { value: 'May', label: '05 - Mayo' },
  { value: 'Jun', label: '06 - Junio' },
  { value: 'Jul', label: '07 - Julio' },
  { value: 'Ago', label: '08 - Agosto' },
  { value: 'Sep', label: '09 - Septiembre' },
  { value: 'Oct', label: '10 - Octubre' },
  { value: 'Nov', label: '11 - Noviembre' },
  { value: 'Dic', label: '12 - Diciembre' }
]

const anios = [
  { value: '24', label: '2024' },
  { value: '25', label: '2025' },
  { value: '26', label: '2026' },
  { value: '27', label: '2027' },
  { value: '28', label: '2028' }
]

// Nombre de la tabla calculado reactivamente para mostrar como sugerencia/placeholder
const nombreTablaCalculado = computed(() => {
  if (nombreTablaPersonalizado.value.trim()) {
    return nombreTablaPersonalizado.value.trim()
  }
  return `Asignacion${opcionCartera.value}_${mesSeleccionado.value}${anioSeleccionado.value}`
})

// ==========================================
// MONITOR EN VIVO (Conectado a la tabla dinámica activa)
// ==========================================
const kpis = ref({ total: 0, columnas: 0 })
const registrosSql = ref([])
const columnasTabla = ref([]) // Guarda el orden dinámico de columnas devuelto por SQL
const buscarTermino = ref('')
const paginaActual = ref(1)
const ultimaPagina = ref(1)
const tablaExiste = ref(false)

const cargarDatosMonitor = async () => {
  try {
    const params = new URLSearchParams({
      cartera: opcionCartera.value,
      mes_abreviado: mesSeleccionado.value,
      anio_dos_digitos: anioSeleccionado.value,
      nombre_tabla_personalizado: nombreTablaPersonalizado.value,
      page: paginaActual.value,
      search: buscarTermino.value
    })

    const res = await fetch(`http://127.0.0.1:8000/api/subir-asignacion/monitor?${params}`)
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Error al conectar con el monitor.')

    tablaExiste.value = data.tabla_existe
    kpis.value = data.kpis || { total: 0, columnas: 0 }
    registrosSql.value = data.registros?.data || []
    ultimaPagina.value = data.registros?.last_page || 1
    
    // Extraer cabeceras dinámicamente si hay registros indexados
    if (registrosSql.value.length > 0) {
      columnasTabla.value = Object.keys(registrosSql.value[0])
    }
  } catch (err) {
    console.error("Error cargando monitor:", err.message)
  }
}

// Escuchas del monitor para reaccionar al cambio de carteras en tiempo real
watch([opcionCartera, mesSeleccionado, anioSeleccionado, nombreTablaPersonalizado], () => {
  paginaActual.value = 1
  cargarDatosMonitor()
})

watch(buscarTermino, () => {
  paginaActual.value = 1
  cargarDatosMonitor()
})

onMounted(() => {
  cargarDatosMonitor()
})

// ==========================================
// MANEJO DE ARCHIVOS
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
    const formData = new FormData()
    if (archivo.value) formData.append('archivo', archivo.value)
    if (rutaManual.value) formData.append('ruta_excel_manual', rutaManual.value)
    
    formData.append('cartera', opcionCartera.value)
    formData.append('mes_abreviado', mesSeleccionado.value)
    formData.append('anio_dos_digitos', anioSeleccionado.value)
    formData.append('nombre_tabla_personalizado', nombreTablaPersonalizado.value)
    formData.append('columnas_string', columnasString.value)
    formData.append('confirmar', 'N')

    const res = await fetch('http://127.0.0.1:8000/api/subir-asignacion', {
      method: 'POST',
      body: formData
    })

    const data = await res.json()
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
    const formData = new FormData()
    if (archivo.value) formData.append('archivo', archivo.value)
    if (rutaManual.value) formData.append('ruta_excel_manual', rutaManual.value)
    
    formData.append('cartera', opcionCartera.value)
    formData.append('mes_abreviado', mesSeleccionado.value)
    formData.append('anio_dos_digitos', anioSeleccionado.value)
    formData.append('nombre_tabla_personalizado', nombreTablaPersonalizado.value)
    formData.append('columnas_string', columnasString.value)
    formData.append('confirmar', 'S')

    const res = await fetch('http://127.0.0.1:8000/api/subir-asignacion', {
      method: 'POST',
      body: formData
    })

    // 🛑 CONTROL INTERMEDIO: Si el backend explota (Error 500), leemos el error real en texto
    if (!res.ok) {
      const errorTexto = await res.text()
      console.error("🚨 ERROR DETALLADO DEL BACKEND:", errorTexto)
      throw new Error('Error 500 interno del servidor. Revisa la consola (F12) para ver el detalle.')
    }

    // Si todo sale bien, procesamos el JSON normalmente
    const data = await res.json()

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
// Action directo rápido por botones inferiores
const ejecutarCargaRapida = (carteraNom) => {
  opcionCartera.value = carteraNom
  confirmarCarga()
}

</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-2 text-slate-800">

    <div class="grid grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Registros Totales</p>
          <p class="text-2xl font-black text-slate-900">{{ kpis.total.toLocaleString() }}</p>
        </div>
        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">📊</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tabla Destino</p>
          <p class="text-sm font-black text-emerald-600 truncate max-w-[200px]" :title="nombreTablaCalculado">
            {{ nombreTablaCalculado }}
          </p>
        </div>
        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-lg">🗄️</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Estructura Columnas</p>
          <p class="text-2xl font-black text-amber-600">{{ kpis.columnas }}</p>
        </div>
        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-lg">⚡</div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
      <div>
        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">📥 Importación de Asignaciones Mensuales — Excel → SQL Server</h2>
        <p class="text-xs text-slate-400 font-medium mt-0.5">El sistema creará la tabla y detectará las columnas del Excel automáticamente sin necesidad de crearlas manual.</p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div 
          @dragover.prevent 
          @drop.prevent="handleDrop"
          class="border-2 border-dashed border-slate-200 hover:border-indigo-400 rounded-xl p-6 bg-slate-50/50 text-center transition-all relative"
        >
          <input type="file" @change="handleFileChange" accept=".xlsx, .xls" id="file-upload" class="hidden" />

          <div class="flex flex-col items-center justify-center space-y-3">
            <span class="text-3xl">📁</span>
            <div>
              <span class="text-xs font-bold text-slate-700 block">Arrastra tu Cartera Mensual aquí</span>
              <span class="text-[11px] text-slate-400 mt-1 block">Soporta formatos .xlsx y .xls masivos</span>
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

        <div class="lg:col-span-2 bg-slate-50/50 border border-slate-200 border-dashed rounded-xl p-6 flex flex-col justify-between space-y-4">
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-700 block">📁 Ruta en Servidor Local (Opcional):</label>
              <input 
                v-model="rutaManual"
                type="text" 
                placeholder="Ej: //192.168.1.249/Compartido/.../Asig_convenio_junio.xlsx"
                class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-mono shadow-sm outline-none focus:border-indigo-500 transition-all placeholder-slate-400"
              />
            </div>
            <div class="space-y-1">
              <label class="text-xs font-bold text-slate-700 block">✏️ Nombre de Tabla Manual (Sufijos/Opcional):</label>
              <input 
                v-model="nombreTablaPersonalizado"
                type="text" 
                :placeholder="'Por defecto: ' + nombreTablaCalculado"
                class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-mono shadow-sm outline-none focus:border-indigo-500 transition-all placeholder-slate-400"
              />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Cartera</label>
              <select v-model="opcionCartera" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="opt in opcionesCartera" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Mes Carga</label>
              <select v-model="mesSeleccionado" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="m in meses" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Año Carga</label>
              <select v-model="anioSeleccionado" class="w-full bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold shadow-sm outline-none focus:border-indigo-500 transition-all">
                <option v-for="a in anios" :key="a.value" :value="a.value">{{ a.label }}</option>
              </select>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">🛠️ Campos Forzados a Texto Plano (Separados por Comas):</label>
            <textarea 
              v-model="columnasString" 
              rows="2"
              class="w-full bg-white border border-slate-200 rounded-xl py-1.5 px-3 text-[11px] font-mono shadow-sm outline-none focus:border-indigo-500 transition-all text-slate-600"
              placeholder="Escribe las columnas que deban conservar ceros (DNI, Operación, Celulares...)"
            ></textarea>
          </div>

          <div class="flex flex-wrap gap-2 justify-end pt-1">
            <button 
              @click="previsualizar" 
              :disabled="cargando || (!archivo && !rutaManual)" 
              class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 disabled:text-slate-400 px-4 py-2 rounded-xl text-xs font-extrabold transition-all shadow-sm"
            >
              {{ cargando ? 'Analizando...' : '👁️ Previsualizar Excel' }}
            </button>
            <button 
              @click="confirmarCarga" 
              :disabled="cargando || (!archivo && !rutaManual)" 
              class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 text-white disabled:text-slate-400 px-5 py-2 rounded-xl text-xs font-extrabold tracking-wide transition-all shadow-md shadow-indigo-100/50"
            >
              {{ cargando ? 'Procesando Lotes...' : '🚀 Crear Tabla y Cargar' }}
            </button>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-100 pt-4">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">⚡ Lanzamientos rápidos directos</p>
        <div class="flex flex-wrap gap-2">
          <button @click="ejecutarCargaRapida('Convenio')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 text-emerald-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🤝 CONVENIO QUICK
          </button>
          <button @click="ejecutarCargaRapida('Hipotecario')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-blue-50 border border-blue-200 hover:bg-blue-100 text-blue-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🏠 HIPOTECARIO QUICK
          </button>
          <button @click="ejecutarCargaRapida('Carsa')" :disabled="cargando || (!archivo && !rutaManual)" class="bg-purple-50 border border-purple-200 hover:bg-purple-100 text-purple-700 disabled:text-slate-400 px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all">
            🚗 CARSA QUICK
          </button>
        </div>
      </div>
    </div>

    <div v-if="errorMsg" class="bg-rose-50 border border-rose-100 text-rose-700 text-xs p-4 rounded-xl font-bold flex items-start gap-2.5 shadow-sm">
      <span class="text-sm">⚠️</span>
      <div>
        <p class="font-extrabold uppercase">Error de Consistencia u Origen</p>
        <p class="font-medium text-rose-600 mt-0.5">{{ errorMsg }}</p>
      </div>
    </div>

    <div v-if="mostrarModalPreview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="mostrarModalPreview = false">
      <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-200 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">👁️ Previsualización de Columnas Detectadas</h3>
            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Estructura dinámica leída en memoria antes de impactar SQL Server</p>
          </div>
          <button @click="mostrarModalPreview = false" class="text-slate-400 hover:text-slate-600 transition-colors text-xl font-bold p-1 hover:bg-slate-100 rounded-lg w-8 h-8 flex items-center justify-center">✕</button>
        </div>

        <div class="grid grid-cols-3 gap-3 text-center text-[11px] font-bold">
          <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
            <span class="text-slate-400 block">Filas en Excel</span>
            <span class="text-sm text-slate-800 font-black block mt-0.5">{{ preview?.registros_encontrados || 0 }}</span>
          </div>
          <div class="bg-indigo-50 border border-indigo-100 p-2.5 rounded-xl">
            <span class="text-indigo-600 block">Objetivo SQL Target</span>
            <span class="text-sm text-indigo-700 font-black block mt-0.5">{{ preview?.nombre_tabla_calculado }}</span>
          </div>
          <div class="bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
            <span class="text-slate-400 block">Columnas Encontradas</span>
            <span class="text-sm text-slate-800 font-black block mt-0.5">{{ preview?.columnas?.length || 0 }}</span>
          </div>
        </div>

        <div class="overflow-x-auto border border-slate-100 rounded-xl max-h-96">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-100 font-bold text-slate-500 uppercase text-[10px]">
                <th v-for="col in preview?.columnas" :key="col" class="p-2.5 whitespace-nowrap tracking-wider">
                  {{ col }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in preview?.preview" :key="idx" class="border-b border-slate-50 font-medium text-slate-600 hover:bg-slate-50/50">
                <td v-for="col in preview?.columnas" :key="col" class="p-2.5 font-mono">
                  {{ row[col] !== null && row[col] !== undefined ? row[col] : 'NULL' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-between items-center pt-4 border-t border-slate-100">
          <button @click="mostrarModalPreview = false" class="text-slate-400 text-xs font-bold hover:text-slate-600">Abortar</button>
          <button 
            @click="confirmarCarga" 
            :disabled="cargando" 
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl text-xs font-extrabold tracking-wide shadow-md shadow-emerald-100"
          >
            {{ cargando ? 'Fundando Tabla e Inyectando lotes...' : '🚀 Todo conforme, Confirmar Carga' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="mostrarModalResumen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="mostrarModalResumen = false">
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-emerald-200 p-6 space-y-4">
        <div class="flex items-center gap-3 border-b border-emerald-100 pb-4">
          <div class="w-8 h-8 bg-emerald-500 text-white rounded-full flex items-center justify-center font-bold text-xs">✓</div>
          <div>
            <h3 class="text-sm font-black text-emerald-900 uppercase tracking-wider">Estructura Fundada Exitosamente</h3>
            <p class="text-[10px] text-emerald-600 font-bold">La tabla se encuentra disponible en Asignaciones_origen</p>
          </div>
          <button @click="mostrarModalResumen = false" class="ml-auto text-slate-400 hover:text-slate-600 text-xl font-bold">✕</button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center text-[11px] font-bold">
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Registros Totales</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.registros || 0 }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Insertados OK</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.registros_insertados || 0 }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Tabla Destino</span>
            <span class="text-[10px] text-emerald-800 font-black block mt-1 truncate">{{ resumenFinal?.resumen?.nombre_tabla }}</span>
          </div>
          <div class="bg-emerald-50 border border-emerald-100 p-2.5 rounded-xl">
            <span class="text-emerald-600 block">Tiempo Proceso</span>
            <span class="text-sm text-emerald-800 font-black block mt-0.5">{{ resumenFinal?.resumen?.tiempo_segundos || 0 }}s</span>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button @click="mostrarModalResumen = false; resumenFinal = null" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-xs font-extrabold transition-all">
            Aceptar e Indexar Monitor
          </button>
        </div>
      </div>
    </div>

    <div class="border border-slate-200 rounded-2xl bg-white p-5 shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100/80 pb-4">
        <div>
          <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">🖥️ Monitor de Datos Dinámicos — Tabla: {{ nombreTablaCalculado }}</h3>
          <p class="text-[11px] text-slate-400 font-medium mt-0.5">Consulta directa en caliente sobre la base de datos de origen</p>
        </div>
        <div class="w-full sm:w-72" v-if="tablaExiste">
          <input 
            v-model="buscarTermino"
            type="text" 
            placeholder="Buscar por columnas primarias..." 
            class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 text-xs font-semibold outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-50/50 transition-all placeholder-slate-400"
          />
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-100 rounded-xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr v-if="tablaExiste && registrosSql.length > 0" class="bg-slate-50/80 border-b border-slate-100 font-bold text-slate-500 uppercase tracking-wider text-[10px]">
              <th v-for="col in columnasTabla" :key="col" class="p-3 whitespace-nowrap">
                {{ col }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!tablaExiste" class="text-center text-slate-400">
              <td class="p-10 font-bold text-xs">
                ⚠️ La tabla <span class="font-mono text-indigo-600 bg-slate-100 px-1.5 py-0.5 rounded">{{ nombreTablaCalculado }}</span> no existe en el servidor. Procede a cargar el archivo para fundarla.
              </td>
            </tr>
            <tr v-else-if="registrosSql.length === 0" class="text-center text-slate-400">
              <td :colspan="columnasTabla.length || 1" class="p-10 font-bold text-xs">No hay datos indexados o el término de búsqueda no coincide.</td>
            </tr>
            <tr v-else v-for="(row, idx) in registrosSql" :key="idx" class="border-b border-slate-50 hover:bg-slate-50/40 font-medium text-slate-600 transition-colors">
              <td v-for="col in columnasTabla" :key="col" class="p-3 font-mono whitespace-nowrap">
                {{ row[col] !== null && row[col] !== undefined ? row[col] : '-' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between pt-2 text-xs font-bold" v-if="tablaExiste && ultimaPagina > 1">
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

    <!-- ========================================================================= -->
    <!-- 🔄 OVERLAY DE CARGA: CÍRCULO PREMIUM DE DOBLE SEGMENTO                    -->
    <!-- ========================================================================= -->
    <div 
          v-if="cargando" 
          class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900/70 backdrop-blur-sm"
        >
          <div class="bg-white px-12 py-12 rounded-2xl shadow-2xl border border-slate-100 flex flex-col items-center max-w-lg w-full text-center space-y-8">
            
            <div class="relative flex items-center justify-center" style="width: 128px; height: 128px;">
              <div class="absolute inset-0 animate-spin rounded-full border-[6px] border-slate-100 border-t-indigo-600 border-b-indigo-600"></div>
            </div>
            
            <div class="space-y-2.5">
              <h4 class="text-base font-black text-slate-900 uppercase tracking-wider">Procesando Información</h4>
              <p class="text-xs text-slate-500 font-semibold leading-relaxed px-4">
                Creando tabla. Por favor, no cierres esta ventana.
              </p>
            </div>
          </div>
    </div>

  </div>
</template>
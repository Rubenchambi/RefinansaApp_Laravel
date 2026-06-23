<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import * as XLSX from 'xlsx'  // 👈 Importar la librería para XLSX

// --- CONSTANTES Y ESTADOS DE CONFIGURACIÓN ---
const opcionesFiltros = reactive({
  operador: [], peso_telefono: [], m_peso: [], ano: [], mes: [],
  unico: [], cant_predic: [], cant_gest: [], cartera_id: [],
  planes: [], detalle: [], tipo: []
})

const filtros = reactive({
  operador: [], peso_telefono: [], m_peso: [], ano: [], mes: [],
  unico: [], cant_predic: [], cant_gest: [], cartera_id: [],
  planes: [], detalle: [], tipo: []
})

const dropdowns = reactive({
  operador: false, peso_telefono: false, m_peso: false, ano: false, mes: false,
  unico: false, cant_predic: false, cant_gest: false, cartera_id: false,
  planes: false, detalle: false, tipo: false
})

const selectAll = reactive({
  operador: false, peso_telefono: false, m_peso: false, ano: false, mes: false,
  unico: false, cant_predic: false, cant_gest: false, cartera_id: false,
  planes: false, detalle: false, tipo: false
})

const archivoExcel = ref(null)
const cargando = ref(false)
const resultados = ref(null)
const dragover = ref(false)

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const porPagina = ref(10)

// FILTRADO DINÁMICO EN EL FRONT-END
const universoFiltradoEnPantalla = computed(() => {
  if (!resultados.value || !resultados.value.telefonos_planos) return []
  
  return resultados.value.telefonos_planos.filter(item => {
    for (const campo in filtros) {
      if (filtros[campo].length > 0) {
        const valorItem = item[campo] !== null && item[campo] !== undefined ? String(item[campo]) : '-'
        if (!filtros[campo].includes(valorItem)) {
          return false
        }
      }
    }
    return true
  })
})

const resultadosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina.value
  return universoFiltradoEnPantalla.value.slice(inicio, inicio + porPagina.value)
})

const totalPaginas = computed(() => {
  if (universoFiltradoEnPantalla.value.length === 0) return 1
  return Math.ceil(universoFiltradoEnPantalla.value.length / porPagina.value)
})

watch(universoFiltradoEnPantalla, () => { paginaActual.value = 1 })

// --- MODALS DE CONFIGURACIÓN Y RESUMEN ---
const modalConfig = reactive({
  mostrar: false,
  tipo: '', // 'devalix' o 'uncontac'
  nombreArchivo: '',
  formato: 'txt', // 'txt' o 'csv'
  nombrePredictivo: 'Predic_cartera',
  carteraSeleccionada: ''
})

const modalResumen = reactive({
  mostrar: false,
  nombreFinal: '',
  formatoFinal: '',
  totalRegistros: 0,
  operadores: {},
  detalle: [],  // 👈 NUEVO: estadísticas por detalle
  contenidoPreparado: ''
})

const carterasDisponibles = computed(() => {
  const lista = universoFiltradoEnPantalla.value.map(t => String(t.cartera_id || '')).filter(Boolean)
  return [...new Set(lista)]
})

// --- MÉTODOS DE FILTROS ---
const toggleDropdown = (name) => {
  Object.keys(dropdowns).forEach(k => { if (k !== name) dropdowns[k] = false })
  dropdowns[name] = !dropdowns[name]
}

const toggleSelectAll = (name) => {
  filtros[name] = selectAll[name] ? [...opcionesFiltros[name]] : []
}

const actualizarSelectAll = (name) => {
  selectAll[name] = filtros[name].length === opcionesFiltros[name].length
}

const getSelectedText = (arr, label) => {
  if (!arr || arr.length === 0) return `Todos (${label})`
  return arr.length === 1 ? arr[0] : `${arr.length} sel.`
}

const getLabelFormateado = (campo, valor) => {
  if (campo === 'm_peso' || campo === 'peso_telefono') return `Peso ${valor}`
  if (campo === 'mes') return `Mes ${valor}`
  return valor === '-' ? '📭 Vacío' : valor
}

const getOperadorBadgeClass = (op) => {
  if (!op) return 'bg-gray-400'
  const clean = op.toUpperCase()
  if (clean.includes('MOVISTAR')) return 'bg-violet-600'
  if (clean.includes('CLARO')) return 'bg-orange-600'
  if (clean.includes('ENTEL')) return 'bg-teal-700'
  if (clean.includes('BITEL')) return 'bg-red-600'
  return 'bg-gray-500'
}

// --- PETICIONES API Y CARGA DE ARCHIVOS ---
const cargarOpcionesDeSQLServer = async () => {
  try {
    const response = await fetch('http://localhost:8000/api/predictivo/opciones-filtros')
    const data = await response.json()
    Object.keys(opcionesFiltros).forEach(key => {
      if (data[key]) opcionesFiltros[key] = data[key].map(v => v !== null ? String(v) : '-')
    })
  } catch (e) { console.error("Error cargando filtros:", e) }
}

const handleFileUpload = (e) => { 
  archivoExcel.value = e.target.files[0] 
}

const handleFileDrop = (e) => {
  dragover.value = false
  if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
    const archivo = e.dataTransfer.files[0]
    const nombre = archivo.name.toLowerCase()
    if (nombre.endsWith('.xlsx') || nombre.endsWith('.xls')) {
      archivoExcel.value = archivo
    } else {
      alert('Por favor, ingresa solo archivos con formato Excel (.xlsx o .xls)')
    }
  }
}

const procesarPredictivo = async () => {
  if (!archivoExcel.value) return alert('Selecciona un archivo Excel, por favor.')
  cargando.value = true; resultados.value = null
  try {
    const formData = new FormData()
    formData.append('File', archivoExcel.value)
    
    const response = await fetch('http://localhost:8000/api/predictivo/cargar-dnis', { method: 'POST', body: formData })
    const data = await response.json()
    data.success ? resultados.value = data : alert(data.error || data.warning || 'Error procesando la base.')
  } catch (e) { alert('Error de conexión con el servidor.') }
  finally { cargando.value = false }
}

// --- FLUJO POPUPS Y GENERACIÓN ---
const abrirConfiguracion = (tipo) => {
  if (!universoFiltradoEnPantalla.value.length) return alert('No hay registros filtrados para procesar.')
  modalConfig.tipo = tipo
  modalConfig.formato = 'txt'
  modalConfig.carteraSeleccionada = carterasDisponibles.value[0] || ''
  actualizarNombrePorCartera()
  modalConfig.mostrar = true
}

const actualizarNombrePorCartera = () => {
  const fecha = new Date().toISOString().slice(0,10).replace(/-/g,'')
  modalConfig.nombreArchivo = `Predictivo_${modalConfig.carteraSeleccionada}_${fecha}`
}

const prepararPrevisualizacion = () => {
  if (!modalConfig.nombreArchivo.trim()) return alert('El nombre de archivo es obligatorio.')

  const universoFinalAExportar = universoFiltradoEnPantalla.value.filter(t => String(t.cartera_id) === String(modalConfig.carteraSeleccionada))
  
  if (!universoFinalAExportar.length) {
    return alert('No hay datos disponibles para la combinación de filtros y Cartera ID seleccionada.')
  }

  let contenidoStr = ''
  let ext = modalConfig.formato

  if (modalConfig.tipo === 'devalix') {
    if (ext === 'txt') {
      const lineas = ['telefono|cliente_id|cartera_id']
      universoFinalAExportar.forEach(t => lineas.push(`${t.telefono || ''}|${t.nro_documento || ''}|${t.cartera_id || ''}`))
      contenidoStr = lineas.join('\r\n')
    } else if (ext === 'csv') {
      const lineas = ['telefono,cliente_id,cartera_id']
      universoFinalAExportar.forEach(t => lineas.push(`"${t.telefono || ''}","${t.nro_documento || ''}","${t.cartera_id || ''}"`))
      contenidoStr = lineas.join('\r\n')
    } else { // xlsx
      contenidoStr = universoFinalAExportar.map(t => ({
        telefono: t.telefono || '',
        cliente_id: t.nro_documento || '',
        cartera_id: t.cartera_id || ''
      }))
    }
  } else {
    // UNCONTAC
    if (ext === 'txt') {
      const lineas = ['cartera\ttelefono\tcadena\tespacio\tcodigo']
      universoFinalAExportar.forEach(t => {
        lineas.push(`${modalConfig.nombrePredictivo}<-\t${t.telefono || ''}\tdocumento=${t.nro_documento || ''}:cartera=${t.cartera_id || ''}\t\t9999`)
      })
      contenidoStr = lineas.join('\r\n')
    } else if (ext === 'csv') {
      const lineas = ['cartera,telefono,cadena,espacio,codigo']
      universoFinalAExportar.forEach(t => {
        lineas.push(`"${modalConfig.nombrePredictivo}<-","${t.telefono || ''}","documento=${t.nro_documento || ''}:cartera=${t.cartera_id || ''}","","9999"`)
      })
      contenidoStr = lineas.join('\r\n')
    } else { // xlsx
      contenidoStr = universoFinalAExportar.map(t => ({
        cartera: modalConfig.nombrePredictivo + '<-',
        telefono: t.telefono || '',
        cadena: `documento=${t.nro_documento || ''}:cartera=${t.cartera_id || ''}`,
        espacio: '',
        codigo: '9999'
      }))
    }
  }

  const resumenOps = {}
  universoFinalAExportar.forEach(t => {
    const op = (t.operador || 'DESCONOCIDO').toUpperCase()
    resumenOps[op] = (resumenOps[op] || 0) + 1
  })

  // --- NUEVO: Cálculo de estadísticas por DETALLE (búsqueda, origen1, origen2, origen3, sistemas) ---
  const resumenDetalle = {}
  universoFinalAExportar.forEach(t => {
    const detalle = (t.detalle || 'SIN DETALLE')
    resumenDetalle[detalle] = (resumenDetalle[detalle] || 0) + 1
  })
  const detalleOrdenado = Object.entries(resumenDetalle).sort((a, b) => b[1] - a[1])

  let nombreLimpio = modalConfig.nombreArchivo.trim()
  if (nombreLimpio.toLowerCase().endsWith('.txt') || nombreLimpio.toLowerCase().endsWith('.csv') || nombreLimpio.toLowerCase().endsWith('.xlsx')) {
    nombreLimpio = nombreLimpio.slice(0, nombreLimpio.lastIndexOf('.'))
  }

  modalResumen.nombreFinal = `${nombreLimpio}.${ext}`
  modalResumen.formatoFinal = ext
  modalResumen.totalRegistros = universoFinalAExportar.length
  modalResumen.operadores = resumenOps
  modalResumen.detalle = detalleOrdenado  // 👈 Asignar estadísticas de detalle
  modalResumen.contenidoPreparado = contenidoStr  // Para TXT y CSV

  modalConfig.mostrar = false
  modalResumen.mostrar = true
}

const descargarArchivoConfirmado = () => {
  const contenido = modalResumen.contenidoPreparado
  const tipo = modalResumen.formatoFinal
  const nombre = modalResumen.nombreFinal

  if (tipo === 'xlsx') {
    // Generar archivo XLSX real
    const wb = XLSX.utils.book_new()
    let wsData = []

    if (modalConfig.tipo === 'devalix') {
      wsData = [
        ['telefono', 'cliente_id', 'cartera_id'],
        ...contenido.map(row => [row.telefono, row.cliente_id, row.cartera_id])
      ]
    } else {
      wsData = [
        ['cartera', 'telefono', 'cadena', 'espacio', 'codigo'],
        ...contenido.map(row => [row.cartera, row.telefono, row.cadena, row.espacio, row.codigo])
      ]
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData)
    XLSX.utils.book_append_sheet(wb, ws, 'Datos')
    const buffer = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = nombre
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } else {
    // TXT o CSV
    const tipoMime = tipo === 'csv' ? 'text/csv;charset=utf-8;' : 'text/plain;charset=utf-8;'
    const blob = tipo === 'csv'
      ? new Blob([new Uint8Array([0xEF, 0xBB, 0xBF]), contenido], { type: tipoMime })
      : new Blob([contenido], { type: tipoMime })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = nombre
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }

  modalResumen.mostrar = false
}

// Función para volver a editar (cerrar resumen y abrir configuración)
const volverAEditar = () => {
  modalResumen.mostrar = false
  modalConfig.mostrar = true
}

const cerrarDropdownsExterno = () => Object.keys(dropdowns).forEach(k => dropdowns[k] = false)

onMounted(() => {
  window.addEventListener('click', cerrarDropdownsExterno)
  cargarOpcionesDeSQLServer()
})
onUnmounted(() => window.removeEventListener('click', cerrarDropdownsExterno))
</script>

<template>
  <div class="p-6 bg-gray-50 min-h-screen text-gray-800 font-sans">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
      <div class="border-b border-gray-100 pb-2 mb-5">
        <h4 class="text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">
            📁1. Cargar Origen de Datos (Excel)
        </h4>
         <p class="text-sm text-gray-500 mt-1 py-1">Sube el archivo Excel origen, filtra dinámicamente y exporta su Base de predictivo.</p>

        <div class="max-w-4xl space-y-4">
          <div 
            @dragover.prevent="dragover = true" 
            @dragleave="dragover = false" 
            @drop.prevent="handleFileDrop"
            :class="['border-2 border-dashed rounded-2xl p-6 text-center transition-all flex flex-col items-center justify-center space-y-3 bg-slate-50/50', dragover ? 'border-red-500 bg-red-50/30 shadow-inner' : 'border-slate-200 hover:border-slate-300']"
          >
            <span class="text-3xl animate-bounce" v-if="dragover">📥</span>
            <span class="text-3xl" v-else>
                <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="50" height="50" viewBox="0 0 48 48">
                <path fill="#169154" d="M29,6H15.744C14.781,6,14,6.781,14,7.744v7.259h15V6z"></path><path fill="#18482a" d="M14,33.054v7.202C14,41.219,14.781,42,15.743,42H29v-8.946H14z"></path><path fill="#0c8045" d="M14 15.003H29V24.005000000000003H14z"></path><path fill="#17472a" d="M14 24.005H29V33.055H14z"></path><g><path fill="#29c27f" d="M42.256,6H29v9.003h15V7.744C44,6.781,43.219,6,42.256,6z"></path><path fill="#27663f" d="M29,33.054V42h13.257C43.219,42,44,41.219,44,40.257v-7.202H29z"></path><path fill="#19ac65" d="M29 15.003H44V24.005000000000003H29z"></path><path fill="#129652" d="M29 24.005H44V33.055H29z"></path></g><path fill="#0c7238" d="M22.319,34H5.681C4.753,34,4,33.247,4,32.319V15.681C4,14.753,4.753,14,5.681,14h16.638 C23.247,14,24,14.753,24,15.681v16.638C24,33.247,23.247,34,22.319,34z"></path><path fill="#fff" d="M9.807 19L12.193 19 14.129 22.754 16.175 19 18.404 19 15.333 24 18.474 29 16.123 29 14.013 25.07 11.912 29 9.526 29 12.719 23.982z"></path>
                </svg> 
            </span>
            
            <div>
              <span class="text-xs font-bold text-slate-700 block">Arrastra su archivo excel aquí</span>
              <span class="text-[11px] text-slate-400 mt-1 block">Soporta formatos .xlsx y .xls masivos</span>
            </div>

            <input type="file" id="file-upload" accept=".xlsx, .xls" @change="handleFileUpload" class="hidden" />
            
            <label for="file-upload" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs shadow-sm cursor-pointer transition-all inline-block">
              🔍 Examinar Archivo Local
            </label>

            <div v-if="archivoExcel" class="text-[11px] font-mono text-emerald-600 bg-emerald-50 border border-emerald-100 px-3 py-1 rounded-md mt-2 flex items-center gap-1">
              ✅ <b>Archivo Cargado:</b> {{ archivoExcel.name }}
            </div>
          </div>

          <div class="flex justify-end">
            <button @click="procesarPredictivo" :disabled="cargando || !archivoExcel" class="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:text-gray-500 text-white font-bold text-sm px-8 py-2.5 rounded-xl transition-all shadow-md whitespace-nowrap cursor-pointer w-full sm:w-auto">
              {{ cargando ? '🔄 Buscando...' : 'Procesar Base Seleccionada' }}
            </button>
          </div>
        </div>
      </div>

      <div class="space-y-5 w-full">
        <h4 class="text-xs font-bold text-gray-700 mb-2 uppercase tracking-wider">⚙️ 2. Filtros de Segmentación Cruzada</h4>
        
        <div class="flex flex-row gap-3 w-full">
          <div v-for="campo in ['operador', 'peso_telefono', 'm_peso', 'ano', 'mes', 'unico']" :key="campo" class="flex-1 min-w-[80px] flex flex-col gap-1 relative">
            <label class="text-xs font-bold text-gray-600 capitalize truncate">{{ campo.replace('_', ' ') }}</label>
            <div class="w-full relative">
              <div @click.stop="toggleDropdown(campo)" :class="['flex justify-between items-center text-xs border rounded-md p-2.5 cursor-pointer select-none shadow-sm', campo === 'm_peso' ? 'border-green-300 bg-green-50 text-green-800 font-semibold':'border-gray-300 bg-white text-gray-700']">
                <span class="truncate">{{ getSelectedText(filtros[campo], campo) }}</span>
                <span class="text-[9px] text-gray-400">▼</span>
              </div>
              <div v-show="dropdowns[campo]" class="absolute z-50 mt-1 left-0 right-0 min-w-[150px] max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-xl p-2 flex flex-col gap-1.5">
                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer text-xs font-bold text-gray-900 border-b border-gray-100 pb-2 mb-0.5">
                  <input type="checkbox" v-model="selectAll[campo]" @change="toggleSelectAll(campo)" class="rounded text-red-600 w-3.5 h-3.5">
                  <span>Todos</span>
                </label>
                <label v-for="opc in opcionesFiltros[campo]" :key="opc" class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 rounded cursor-pointer text-xs text-gray-700">
                  <input type="checkbox" :value="opc" v-model="filtros[campo]" @change="actualizarSelectAll(campo)" class="rounded text-red-600 w-3.5 h-3.5">
                  <span class="truncate">{{ getLabelFormateado(campo, opc) }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-row gap-3 w-full">
          <div v-for="campo in ['cant_predic', 'cant_gest', 'cartera_id', 'planes', 'detalle', 'tipo']" :key="campo" class="flex-1 min-w-[80px] flex flex-col gap-1 relative">
            <label class="text-xs font-bold text-gray-600 capitalize truncate">{{ campo.replace('_', ' ') }}</label>
            <div class="w-full relative">
              <div @click.stop="toggleDropdown(campo)" class="flex justify-between items-center text-xs border border-gray-300 text-gray-700 rounded-md p-2.5 cursor-pointer select-none bg-white shadow-sm">
                <span class="truncate">{{ getSelectedText(filtros[campo], campo) }}</span>
                <span class="text-[9px] text-gray-400">▼</span>
              </div>
              <div v-show="dropdowns[campo]" class="absolute z-50 mt-1 left-0 right-0 min-w-[170px] max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-xl p-2 flex flex-col gap-1.5">
                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer text-xs font-bold text-gray-900 border-b border-gray-100 pb-2 mb-0.5">
                  <input type="checkbox" v-model="selectAll[campo]" @change="toggleSelectAll(campo)" class="rounded text-red-600 w-3.5 h-3.5">
                  <span>Todos</span>
                </label>
                <label v-for="opc in opcionesFiltros[campo]" :key="opc" class="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 rounded cursor-pointer text-xs text-gray-700">
                  <input type="checkbox" :value="opc" v-model="filtros[campo]" @change="actualizarSelectAll(campo)" class="rounded text-red-600 w-3.5 h-3.5">
                  <span class="truncate">{{ getLabelFormateado(campo, opc) }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="resultados" class="space-y-6">
      <div class="flex flex-row gap-4 w-full">
        <div class="flex-1 bg-white border border-gray-200 rounded-xl p-4 text-center shadow-sm border-t-4 border-t-red-500">
          <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ resultados.resumen.total_dnis_excel }}</div>
          <div class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wider mt-1 truncate">Total DNI Excel</div>
        </div>
        <div class="flex-1 bg-white border border-gray-200 rounded-xl p-4 text-center shadow-sm border-t-4 border-t-green-500">
          <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ resultados.resumen.dnis_con_telefonos }}</div>
          <div class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wider mt-1 truncate">DNI con Teléfonos</div>
        </div>
        <div class="flex-1 bg-white border border-gray-200 rounded-xl p-4 text-center shadow-sm border-t-4 border-t-blue-500">
          <div class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">{{ universoFiltradoEnPantalla.length }}</div>
          <div class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wider mt-1 truncate">Telfs. Netos Filtrados</div>
        </div>
      </div>

      <div class="flex justify-center items-center gap-6 py-4 bg-white border border-gray-200 rounded-xl shadow-sm">
        <button @click="abrirConfiguracion('devalix')" class="bg-purple-700 hover:bg-purple-800 text-white font-bold text-sm px-8 py-3 rounded-lg shadow transition-colors cursor-pointer">
          💼 Configurar Base Devalix
        </button>
        <button @click="abrirConfiguracion('uncontac')" class="bg-teal-700 hover:bg-teal-800 text-white font-bold text-sm px-8 py-3 rounded-lg shadow transition-colors cursor-pointer">
          🤖 Configurar Base Uncontac
        </button>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-3 gap-2">
          <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider">📋 Registros Filtrados en Pantalla (Universo Actual: {{ universoFiltradoEnPantalla.length }})</h4>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 font-semibold">
                <th class="p-3">Documento</th>
                <th class="p-3">Teléfono</th>
                <th class="p-3">Operador</th>
                <th class="p-3 text-center">M-Peso</th>
                <th class="p-3 text-center">Único</th>
                <th class="p-3">Cartera ID</th>
                <th class="p-3">Detalle</th>
                <th class="p-3">Estado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-600">
              <tr v-for="cli in resultadosPaginados" :key="cli.id" class="hover:bg-gray-50 transition-colors">
                <td class="p-3 font-mono">{{ cli.nro_documento }}</td>
                <td class="p-3 font-bold text-gray-900">{{ cli.telefono }}</td>
                <td class="p-3"><span :class="['px-2 py-0.5 rounded text-[10px] font-bold text-white', getOperadorBadgeClass(cli.operador)]">{{ cli.operador }}</span></td>
                <td class="p-3 text-center font-semibold">{{ cli.m_peso }}</td>
                <td class="p-3 text-center"><span :class="['px-2 py-0.5 rounded text-[10px] font-bold', cli.unico === 'SI' ? 'bg-green-100 text-green-800':'bg-gray-100 text-gray-600']">{{ cli.unico }}</span></td>
                <td class="p-3 font-semibold text-indigo-600">{{ cli.cartera_id }}</td>
                <td class="p-3 text-xs">{{ cli.detalle || '-' }}</td>
                <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">{{ cli.estado }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL DE CONFIGURACIÓN -->
    <div v-if="modalConfig.mostrar" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex justify-center items-center z-50 animate-fade-in" @click="modalConfig.mostrar = false">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md border border-gray-100" @click.stop>
        <h3 class="text-lg font-bold text-gray-900 mb-2 border-b border-gray-100 pb-2 capitalize">⚙️ Configurar Base {{ modalConfig.tipo }}</h3>
        
        <div class="space-y-4 my-4">
          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">💼 Cartera ID Seleccionada:</label>
            <select v-model="modalConfig.carteraSeleccionada" @change="actualizarNombrePorCartera" class="w-full border border-gray-300 rounded-lg p-2 text-sm bg-white font-semibold text-indigo-700 focus:outline-none shadow-sm">
              <option v-for="cartera in carterasDisponibles" :key="cartera" :value="cartera">Cartera ID: {{ cartera }}</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">📊 Formato de Salida:</label>
            <div class="grid grid-cols-3 gap-3 mt-1">
              <label :class="['flex items-center justify-center p-2 border rounded-lg cursor-pointer text-xs font-bold transition-all', modalConfig.formato === 'txt' ? 'border-purple-600 bg-purple-50 text-purple-700 shadow-sm' : 'border-gray-200 text-gray-600 bg-white']">
                <input type="radio" value="txt" v-model="modalConfig.formato" class="sr-only"> <img src="https://img.icons8.com/?size=100&id=PewvBGCwMClZ&format=png&color=000000"width="40" height="40" alt=""> TXT
              </label>
              <label :class="['flex items-center justify-center p-2 border rounded-lg cursor-pointer text-xs font-bold transition-all', modalConfig.formato === 'csv' ? 'border-green-600 bg-green-50 text-green-700 shadow-sm' : 'border-gray-200 text-gray-600 bg-white']">
                <input type="radio" value="csv" v-model="modalConfig.formato" class="sr-only"> <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="40" height="40" viewBox="0 0 48 48">
<path fill="#1b5e20" d="M9,17.139c0-2.624,2.126-4.75,4.749-4.75H41v27.444C41,41.582,39.583,43,37.834,43H15.332	C11.835,43,9,40.164,9,36.667C9,36.667,9,17.139,9,17.139z"></path><path fill="#43a047" d="M9,22.75C9,20.126,11.126,18,13.749,18h14.418c-1.749,0-3.166,1.418-3.166,3.167v6	c0,1.749-1.417,3.167-3.166,3.167h-6.502c-3.497,0-6.332,2.836-6.332,6.333L9,22.75L9,22.75z"></path><path fill="#9ccc65" d="M9,11.333C9,7.836,11.835,5,15.332,5h12.941v13H15.332C11.835,18,9,20.836,9,24.333	C9,24.333,9,11.333,9,11.333z"></path><path fill="#ccff90" d="M28.166,5h9.668C39.582,5,41,6.418,41,8.166v6.668C41,16.582,39.582,18,37.834,18h-9.668	C26.417,18,25,16.582,25,14.834V8.166C25,6.418,26.417,5,28.166,5z"></path><path fill="#2e7d32" d="M7.5,23h10c1.933,0,3.5,1.567,3.5,3.5v10c0,1.933-1.567,3.5-3.5,3.5h-10C5.567,40,4,38.433,4,36.5	v-10C4,24.567,5.567,23,7.5,23z"></path><path fill="#fff" d="M16.965,36.357h-2.62L12.7,33.261c-0.059-0.109-0.104-0.194-0.135-0.258	c-0.027-0.067-0.057-0.145-0.088-0.23H12.45c-0.041,0.109-0.079,0.197-0.115,0.264c-0.036,0.068-0.079,0.151-0.129,0.251	l-1.706,3.068H8.03l2.965-4.864l-2.762-4.85h2.586l1.462,2.764c0.059,0.113,0.109,0.213,0.149,0.298	c0.045,0.081,0.09,0.178,0.135,0.291h0.027c0.063-0.131,0.112-0.235,0.149-0.311c0.041-0.076,0.095-0.178,0.162-0.304l1.516-2.736	h2.464l-2.802,4.776L16.965,36.357L16.965,36.357z"></path>
</svg> CSV
              </label>
              <label :class="['flex items-center justify-center p-2 border rounded-lg cursor-pointer text-xs font-bold transition-all', modalConfig.formato === 'xlsx' ? 'border-blue-600 bg-blue-50 text-blue-700 shadow-sm' : 'border-gray-200 text-gray-600 bg-white']">
                <input type="radio" value="xlsx" v-model="modalConfig.formato" class="sr-only"> <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="40" height="40" viewBox="0 0 48 48">
<path fill="#169154" d="M29,6H15.744C14.781,6,14,6.781,14,7.744v7.259h15V6z"></path><path fill="#18482a" d="M14,33.054v7.202C14,41.219,14.781,42,15.743,42H29v-8.946H14z"></path><path fill="#0c8045" d="M14 15.003H29V24.005000000000003H14z"></path><path fill="#17472a" d="M14 24.005H29V33.055H14z"></path><g><path fill="#29c27f" d="M42.256,6H29v9.003h15V7.744C44,6.781,43.219,6,42.256,6z"></path><path fill="#27663f" d="M29,33.054V42h13.257C43.219,42,44,41.219,44,40.257v-7.202H29z"></path><path fill="#19ac65" d="M29 15.003H44V24.005000000000003H29z"></path><path fill="#129652" d="M29 24.005H44V33.055H29z"></path></g><path fill="#0c7238" d="M22.319,34H5.681C4.753,34,4,33.247,4,32.319V15.681C4,14.753,4.753,14,5.681,14h16.638 C23.247,14,24,14.753,24,15.681v16.638C24,33.247,23.247,34,22.319,34z"></path><path fill="#fff" d="M9.807 19L12.193 19 14.129 22.754 16.175 19 18.404 19 15.333 24 18.474 29 16.123 29 14.013 25.07 11.912 29 9.526 29 12.719 23.982z"></path>
</svg> XLSX
              </label>
            </div>
          </div>

          <div>
            <label class="text-xs font-bold text-gray-600 block mb-1">📄 Nombre del Archivo:</label>
            <input type="text" v-model="modalConfig.nombreArchivo" class="w-full border border-gray-300 rounded-lg p-2 text-sm font-mono focus:outline-none">
          </div>

          <!-- Campos específicos para Uncontac -->
          <div v-if="modalConfig.tipo === 'uncontac'" class="pt-2 border-t border-gray-100">
            <label class="text-xs font-bold text-gray-600 block mb-1">🏷️ Nombre Predictivo:</label>
            <input type="text" v-model="modalConfig.nombrePredictivo" class="w-full border border-gray-300 rounded-lg p-2 text-sm font-mono focus:outline-none bg-gray-50">
            <p class="text-[10px] text-gray-400 mt-1">Prefijo que aparecerá en la columna "cartera"</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 border-t border-gray-100 pt-3">
          <button @click="modalConfig.mostrar = false" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 bg-gray-100 rounded-md cursor-pointer">Cancelar</button>
          <button @click="prepararPrevisualizacion" class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow cursor-pointer">🔄 Generar Reporte</button>
        </div>
      </div>
    </div>

<!-- ============================================================ -->
<!-- MODAL DE RESUMEN MEJORADO (COMPACTO Y CON BARRAS)            -->
<!-- ============================================================ -->
<div v-if="modalResumen.mostrar" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex justify-center items-center z-50 animate-fade-in p-4" @click="modalResumen.mostrar = false">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl border border-gray-100 max-h-[90vh] overflow-y-auto" @click.stop>
    
    <!-- HEADER FIJO -->
    <div class="sticky top-0 bg-white/95 backdrop-blur-sm border-b border-gray-200 px-6 py-3 rounded-t-2xl flex justify-between items-center">
      <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
        📊 Resumen de Exportación
        <span class="text-xs font-normal text-gray-500">({{ modalResumen.totalRegistros }} registros)</span>
      </h3>
      <button @click="modalResumen.mostrar = false" class="text-gray-400 hover:text-gray-600 text-lg cursor-pointer">✕</button>
    </div>

    <!-- CUERPO -->
    <div class="p-5 space-y-5">
      
      <!-- TARJETAS RÁPIDAS (más pequeñas) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-purple-50 rounded-lg p-3 text-center border border-purple-200">
          <div class="text-xl font-bold text-purple-700">{{ modalResumen.totalRegistros }}</div>
          <div class="text-[9px] font-bold text-purple-600 uppercase tracking-wider">Total</div>
        </div>
        <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
          <div class="text-xl font-bold text-green-700">{{ Object.keys(modalResumen.operadores).length }}</div>
          <div class="text-[9px] font-bold text-green-600 uppercase tracking-wider">Operadores</div>
        </div>
        <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
          <div class="text-xl font-bold text-blue-700">{{ modalResumen.detalle.length }}</div>
          <div class="text-[9px] font-bold text-blue-600 uppercase tracking-wider">Detalles</div>
        </div>
        <div class="bg-orange-50 rounded-lg p-3 text-center border border-orange-200">
          <div class="text-xl font-bold ">{{ modalResumen.formatoFinal.toUpperCase() }}</div>
          <div class="text-[9px] font-bold uppercase tracking-wider">Formato</div>
        </div>
      </div>

      <!-- ==================== OPERADORES (GRID 4 COLUMNAS) ==================== -->
      <div>
        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
          <span class="w-1 h-3 bg-purple-600 rounded-full"></span> Operadores
        </h4>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-1.5">
          <div v-for="(count, op) in modalResumen.operadores" :key="op" 
               class="flex justify-between items-center bg-gray-50 rounded-md px-2.5 py-1.5 border border-gray-200">
            <span class="text-[10px] font-bold">{{ op }}</span>
            <span class="text-xs font-mono font-bold text-gray-700">{{ count }}</span>
          </div>
        </div>
      </div>

      <!-- ==================== DETALLE CON BARRAS DE PROGRESO ==================== -->
      <div>
        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
          <span class="w-1 h-3 bg-blue-600 rounded-full"></span> Detalle (Búsqueda, Origen1, Sistemas, etc.)
        </h4>
        <div class="space-y-2">
          <div v-for="[detalle, count] in modalResumen.detalle" :key="detalle" class="flex items-center gap-2">
            <span class="text-[10px] font-semibold text-gray-700 w-20 truncate">{{ detalle }}</span>
            <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
              <div :style="{ width: (count / modalResumen.totalRegistros * 100) + '%' }" 
                   class="h-full bg-blue-500 rounded-full transition-all duration-500"></div>
            </div>
            <span class="text-[10px] font-mono font-bold text-gray-600 w-10 text-right">{{ count }}</span>
            <span class="text-[9px] text-gray-400 w-10">{{ (count / modalResumen.totalRegistros * 100).toFixed(1) }}%</span>
          </div>
        </div>
      </div>

      <!-- ==================== INFORMACIÓN DEL ARCHIVO ==================== -->
      <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 text-[10px] text-gray-500 flex flex-wrap gap-3 items-center">
        <span><span class="font-bold">📄 Nombre:</span> {{ modalResumen.nombreFinal }}</span>
        <span><span class="font-bold">📊 Formato:</span> {{ modalResumen.formatoFinal.toUpperCase() }}</span>
        <span><span class="font-bold">📅 Fecha:</span> {{ new Date().toLocaleDateString() }}</span>
      </div>
    </div>

    <!-- ==================== FOOTER FIJO CON BOTONES ==================== -->
    <div class="sticky bottom-0 bg-white/95 backdrop-blur-sm border-t border-gray-200 px-6 py-3 rounded-b-1xl flex flex-wrap gap-2 justify-end">
      <!-- Botón Volver a editar -->
      <button @click="modalResumen.mostrar = false; modalConfig.mostrar = true" 
              class="px-4 py-3 text-sm font-semibold text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg cursor-pointer transition-all">
        ⬅️ Volver a editar
      </button>
      <!-- Botón Descargar -->
      <button @click="descargarArchivoConfirmado" 
              class="px-6 py-3 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded-lg shadow-md cursor-pointer transition-all flex items-center gap-1.5">
        📥 Descargar {{ modalResumen.formatoFinal.toUpperCase() }}
      </button>
    </div>

  </div>
</div>
  </div>
</template>

<style scoped>
.animate-fade-in { animation: fadeIn 0.15s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
</style>